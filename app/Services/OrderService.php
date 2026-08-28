<?php

namespace App\Services;

use App\Models\CustomerStoreAccount;
use App\Models\Menu;
use App\Models\Order;
use App\Models\PointTransaction;
use App\Models\Reservation;
use App\Models\User;
use App\Models\UserCoupon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public function create(User $user, array $data): Order
    {
        return DB::transaction(function () use ($user, $data) {
            $reservation = null;
            if (! empty($data['reservation_id'])) {
                $reservation = Reservation::query()->lockForUpdate()->findOrFail($data['reservation_id']);
                abort_unless($reservation->user_id === $user->id, 403, '본인의 예약만 주문에 연결할 수 있습니다.');
                abort_unless($reservation->store_id === (int) $data['store_id'], 422, '예약 매장과 주문 매장이 다릅니다.');
                abort_if(Order::where('reservation_id', $reservation->id)->exists(), 422, '이미 주문이 연결된 예약입니다.');
            }

            $menuIds = collect($data['items'])->pluck('menu_id');
            $menus = Menu::query()->lockForUpdate()->whereIn('id', $menuIds)->get()->keyBy('id');
            abort_unless($menus->count() === $menuIds->count(), 422, '일부 메뉴를 찾을 수 없습니다.');

            $lines = collect($data['items'])->map(function (array $item) use ($menus, $data) {
                $menu = $menus->get($item['menu_id']);
                abort_unless($menu->store_id === (int) $data['store_id'], 422, '다른 매장의 메뉴는 함께 주문할 수 없습니다.');
                abort_unless($menu->is_available, 422, "현재 주문할 수 없는 메뉴입니다: {$menu->name}");
                abort_unless((float) $menu->price > 0, 422, "가격이 설정되지 않은 메뉴입니다: {$menu->name}");
                abort_if(
                    $menu->stock_quantity !== null && $menu->stock_quantity < (int) $item['quantity'],
                    422,
                    "메뉴 재고가 부족합니다: {$menu->name} (남은 수량 {$menu->stock_quantity}개)"
                );
                $amount = (float) $menu->price * (int) $item['quantity'];

                return ['menu' => $menu, 'quantity' => (int) $item['quantity'], 'amount' => $amount];
            });

            $menuAmount = $lines->sum('amount');
            $discount = 0.0;
            $userCoupon = null;
            if (! empty($data['user_coupon_id'])) {
                $userCoupon = UserCoupon::query()->with('coupon')->lockForUpdate()->findOrFail($data['user_coupon_id']);
                abort_unless($userCoupon->user_id === $user->id, 403, '본인의 쿠폰만 사용할 수 있습니다.');
                abort_unless($userCoupon->status === 'AVAILABLE' && $userCoupon->expires_at->isFuture(), 422, '사용할 수 없는 쿠폰입니다.');
                $coupon = $userCoupon->coupon;
                abort_unless($coupon->is_active && $coupon->store_id === (int) $data['store_id'] && now()->between($coupon->valid_from, $coupon->valid_until), 422, '이 주문에 적용할 수 없는 쿠폰입니다.');
                abort_unless($menuAmount >= (float) $coupon->minimum_order_amount, 422, '쿠폰 최소 주문금액을 충족하지 못했습니다.');
                $freeItemLine = $coupon->discount_type === 'FREE_ITEM'
                    ? $lines->first(fn ($line) => $line['menu']->id === $coupon->free_menu_id)
                    : null;
                abort_if($coupon->discount_type === 'FREE_ITEM' && $freeItemLine === null, 422, '무료 쿠폰 대상 메뉴가 주문에 없습니다.');
                $discount = match ($coupon->discount_type) {
                    'FIXED' => (float) $coupon->discount_value,
                    'PERCENT' => $menuAmount * ((float) $coupon->discount_value / 100),
                    'FREE_ITEM' => (float) $freeItemLine['menu']->price,
                };
                if ($coupon->maximum_discount_amount !== null) {
                    $discount = min($discount, (float) $coupon->maximum_discount_amount);
                }
                $discount = min($discount, $menuAmount);
            }

            $pointUsed = (int) ($data['point_used'] ?? 0);
            $account = null;
            if ($pointUsed > 0) {
                $account = CustomerStoreAccount::query()
                    ->where('user_id', $user->id)
                    ->where('store_id', $data['store_id'])
                    ->lockForUpdate()
                    ->first();
                abort_unless($account && $account->point_balance >= $pointUsed, 422, '포인트 잔액이 부족합니다.');
                abort_unless($pointUsed <= ($menuAmount - $discount), 422, '결제금액보다 많은 포인트를 사용할 수 없습니다.');
            }

            $finalAmount = max(0, $menuAmount - $discount - $pointUsed);
            $order = Order::create([
                'order_number' => 'ORD-'.now()->format('Ymd').'-'.Str::upper(Str::random(10)),
                'user_id' => $user->id,
                'store_id' => $data['store_id'],
                'reservation_id' => $reservation?->id,
                'total_amount' => $menuAmount,
                'menu_amount' => $menuAmount,
                'deposit_amount' => 0,
                'coupon_discount_amount' => $discount,
                'point_used' => $pointUsed,
                'final_amount' => $finalAmount,
                'status' => 'PENDING_PAYMENT',
                'customer_request' => $data['customer_request'] ?? null,
            ]);

            if ($finalAmount > 0) {
                $order->payment()->create([
                    'provider' => 'TOSS',
                    'toss_order_id' => $order->order_number,
                    'amount' => $finalAmount,
                    'cancelled_amount' => 0,
                    'status' => 'READY',
                ]);
            }

            foreach ($lines as $line) {
                $order->items()->create([
                    'menu_id' => $line['menu']->id,
                    'menu_name' => $line['menu']->name,
                    'unit_price' => $line['menu']->price,
                    'quantity' => $line['quantity'],
                    'line_amount' => $line['amount'],
                ]);

                $menu = $line['menu'];
                if ($menu->stock_quantity !== null) {
                    $remainingStock = $menu->stock_quantity - $line['quantity'];
                    $menu->forceFill([
                        'stock_quantity' => $remainingStock,
                        'is_available' => $remainingStock > 0,
                    ])->save();
                }
            }

            if ($userCoupon) {
                $userCoupon->forceFill(['status' => 'USED', 'used_order_id' => $order->id, 'used_at' => now()])->save();
            }
            if ($account && $pointUsed > 0) {
                $account->decrement('point_balance', $pointUsed);
                PointTransaction::create([
                    'customer_store_account_id' => $account->id,
                    'type' => 'USE',
                    'amount' => -$pointUsed,
                    'balance_after' => $account->fresh()->point_balance,
                    'reason' => 'ORDER_PAYMENT',
                    'reference_type' => Order::class,
                    'reference_id' => $order->id,
                    'idempotency_key' => 'order-point-use-'.$order->id,
                    'created_at' => now(),
                ]);
            }

            return $order->load(['store:id,name,slug', 'items.menu:id,name,image_url', 'payment']);
        });
    }

    public function cancel(User $user, Order $order): Order
    {
        return DB::transaction(function () use ($user, $order) {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            abort_unless($lockedOrder->user_id === $user->id, 404);
            abort_unless($lockedOrder->status === 'PENDING_PAYMENT', 422, '결제 대기 주문만 취소할 수 있습니다.');

            $orderItems = $lockedOrder->items()->get();
            $stockMenus = Menu::query()->withTrashed()
                ->whereIn('id', $orderItems->pluck('menu_id')->filter())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($orderItems as $item) {
                $menu = $stockMenus->get($item->menu_id);
                if (! $menu || $menu->stock_quantity === null) {
                    continue;
                }

                $wasAutomaticallySoldOut = $menu->stock_quantity === 0;
                $menu->forceFill([
                    'stock_quantity' => $menu->stock_quantity + $item->quantity,
                    'is_available' => $wasAutomaticallySoldOut ? true : $menu->is_available,
                ])->save();
            }

            if ($lockedOrder->point_used > 0) {
                $account = CustomerStoreAccount::query()
                    ->where('user_id', $lockedOrder->user_id)
                    ->where('store_id', $lockedOrder->store_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $idempotencyKey = 'order-point-cancel-'.$lockedOrder->id;

                if (! PointTransaction::where('idempotency_key', $idempotencyKey)->exists()) {
                    $account->increment('point_balance', $lockedOrder->point_used);
                    PointTransaction::create([
                        'customer_store_account_id' => $account->id,
                        'type' => 'CANCEL',
                        'amount' => $lockedOrder->point_used,
                        'balance_after' => $account->fresh()->point_balance,
                        'reason' => 'ORDER_CANCEL',
                        'reference_type' => Order::class,
                        'reference_id' => $lockedOrder->id,
                        'idempotency_key' => $idempotencyKey,
                        'created_at' => now(),
                    ]);
                }
            }

            $userCoupon = UserCoupon::query()
                ->where('used_order_id', $lockedOrder->id)
                ->lockForUpdate()
                ->first();

            if ($userCoupon) {
                $couponAvailable = $userCoupon->expires_at->isFuture()
                    && $userCoupon->coupon()->where('is_active', true)
                        ->where('valid_from', '<=', now())
                        ->where('valid_until', '>', now())
                        ->exists();

                $userCoupon->forceFill([
                    'status' => $couponAvailable ? 'AVAILABLE' : 'EXPIRED',
                    'used_order_id' => null,
                    'used_at' => null,
                ])->save();
            }

            $lockedOrder->payment()->where('status', 'READY')->update([
                'status' => 'CANCELLED',
                'cancel_reason' => '결제 전 주문 취소',
                'cancelled_at' => now(),
            ]);

            $lockedOrder->forceFill(['status' => 'CANCELLED', 'cancelled_at' => now()])->save();

            return $lockedOrder->fresh();
        });
    }
}
