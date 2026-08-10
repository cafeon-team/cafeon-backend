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
                'menu_amount' => $menuAmount,
                'deposit_amount' => 0,
                'coupon_discount_amount' => $discount,
                'point_used' => $pointUsed,
                'final_amount' => $finalAmount,
                'status' => 'PENDING_PAYMENT',
                'customer_request' => $data['customer_request'] ?? null,
            ]);

            foreach ($lines as $line) {
                $order->items()->create([
                    'menu_id' => $line['menu']->id,
                    'menu_name' => $line['menu']->name,
                    'unit_price' => $line['menu']->price,
                    'quantity' => $line['quantity'],
                    'line_amount' => $line['amount'],
                ]);
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

            return $order->load(['store:id,name,slug', 'items.menu:id,name,image_url']);
        });
    }
}
