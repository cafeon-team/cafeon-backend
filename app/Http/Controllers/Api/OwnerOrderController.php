<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerVisit;
use App\Models\Order;
use App\Models\Store;
use App\Models\UserNotification;
use App\Services\TossPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OwnerOrderController extends Controller
{
    private const STATUSES = [
        'PENDING_PAYMENT', 'PAID', 'PREPARING', 'READY',
        'COMPLETED', 'CANCELLED', 'REFUNDED',
    ];

    private const NEXT_STATUS = [
        'PAID' => 'PREPARING',
        'PREPARING' => 'READY',
        'READY' => 'COMPLETED',
    ];

    public function index(Request $request, Store $store): JsonResponse
    {
        $this->authorizeStore($request, $store);
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'keyword' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $orders = Order::query()
            ->with(['user:id,name,email,phone,profile_image_url,profile_thumbnail_url', 'items.menu:id,name,image_url', 'payment'])
            ->where('store_id', $store->id)
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['date'] ?? null, fn ($query, $date) => $query->whereDate('created_at', $date))
            ->when($validated['keyword'] ?? null, fn ($query, $keyword) => $query->where(function ($query) use ($keyword) {
                $query->where('order_number', 'like', "%{$keyword}%")
                    ->orWhereHas('user', fn ($query) => $query->where('name', 'like', "%{$keyword}%"));
            }))
            ->latest()
            ->paginate($validated['per_page'] ?? 30);

        $orders->getCollection()->each(function (Order $order) {
            $displayUrl = $order->user?->profile_thumbnail_url ?? $order->user?->profile_image_url;
            $order->setAttribute('profile_image_url', $displayUrl);
            $order->user?->setAttribute('profile_image_url', $displayUrl);
        });

        return response()->json($orders);
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $order->loadMissing('store');
        $this->authorizeStore($request, $order->store);
        $validated = $request->validate([
            'status' => ['required', Rule::in(['PREPARING', 'READY', 'COMPLETED'])],
        ]);

        $order = DB::transaction(function () use ($request, $order, $validated) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            $expected = self::NEXT_STATUS[$locked->status] ?? null;

            if ($expected !== $validated['status']) {
                throw ValidationException::withMessages([
                    'status' => "현재 상태({$locked->status})에서는 {$validated['status']} 상태로 변경할 수 없습니다.",
                ]);
            }

            $timestampColumn = match ($validated['status']) {
                'PREPARING' => 'preparing_at',
                'READY' => 'ready_at',
                'COMPLETED' => 'completed_at',
            };
            $changedAt = now();
            $locked->update([
                'status' => $validated['status'],
                $timestampColumn => $changedAt,
            ]);

            $preference = $locked->user->preference;
            if (! $preference || $preference->order_notifications) {
                $labels = ['PREPARING' => '제조 중', 'READY' => '제조 완료', 'COMPLETED' => '수령 완료'];
                UserNotification::create([
                    'user_id' => $locked->user_id,
                    'type' => 'ORDER_STATUS',
                    'title' => '주문 상태가 변경되었습니다.',
                    'message' => "주문 {$locked->order_number}이(가) {$labels[$validated['status']]} 상태입니다.",
                    'data' => ['order_id' => $locked->id, 'status' => $validated['status']],
                ]);
            }

            if ($validated['status'] === 'COMPLETED') {
                CustomerVisit::firstOrCreate(
                    ['order_id' => $locked->id],
                    [
                        'user_id' => $locked->user_id,
                        'store_id' => $locked->store_id,
                        'type' => 'PURCHASE',
                        'visited_at' => $changedAt,
                        'confirmed_by' => $request->user()->id,
                        'idempotency_key' => "order:{$locked->id}:completed",
                    ]
                );
            }

            $updated = $locked->fresh()->load(['user:id,name,email,phone,profile_image_url,profile_thumbnail_url', 'items.menu:id,name,image_url', 'payment']);
            $displayUrl = $updated->user?->profile_thumbnail_url ?? $updated->user?->profile_image_url;
            $updated->setAttribute('profile_image_url', $displayUrl);
            $updated->user?->setAttribute('profile_image_url', $displayUrl);

            return $updated;
        });

        return response()->json([
            'message' => '주문 상태가 변경되었습니다.',
            'order' => $order,
        ]);
    }

    public function cancel(Request $request, Order $order, TossPaymentService $payments): JsonResponse
    {
        $order->loadMissing('store');
        $this->authorizeStore($request, $order->store);
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        $result = $payments->refundForOwner(
            $order,
            $validated['reason'] ?? '매장 사정으로 주문이 거절되었습니다.'
        );

        if ($result['status'] >= 400) {
            return response()->json($result['body'], $result['status']);
        }

        $cancelledOrder = $result['body']['order'];
        $preference = $cancelledOrder->user->preference;
        if (! $preference || $preference->order_notifications) {
            UserNotification::firstOrCreate(
                [
                    'user_id' => $cancelledOrder->user_id,
                    'type' => 'ORDER_STATUS',
                    'data->order_id' => $cancelledOrder->id,
                    'data->status' => 'REFUNDED',
                ],
                [
                    'title' => '주문이 거절되었습니다.',
                    'message' => "주문 {$cancelledOrder->order_number}이(가) 매장에서 거절되어 결제가 전액 환불되었습니다.",
                    'data' => ['order_id' => $cancelledOrder->id, 'status' => 'REFUNDED'],
                ]
            );
        }

        return response()->json([
            'message' => '주문이 거절되고 결제가 전액 환불되었습니다.',
            'order' => $cancelledOrder->fresh()->load(['user:id,name,email,phone,profile_image_url,profile_thumbnail_url', 'items.menu:id,name,image_url', 'payment']),
            'idempotent' => $result['body']['idempotent'] ?? false,
        ]);
    }

    private function authorizeStore(Request $request, Store $store): void
    {
        $user = $request->user();
        $isAdmin = strtoupper((string) $user->role) === 'ADMIN';
        $isManager = $store->members()->where('user_id', $user->id)->where('is_active', true)
            ->whereIn('role', ['OWNER', 'MANAGER'])->exists();

        abort_unless($isAdmin || $isManager, 403, '주문을 관리할 권한이 없습니다.');
    }
}
