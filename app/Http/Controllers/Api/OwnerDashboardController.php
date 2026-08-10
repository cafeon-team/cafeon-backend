<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OwnerDashboardController extends Controller
{
    public function show(Request $request, Store $store): JsonResponse
    {
        $user = $request->user();
        $isAdmin = strtoupper((string) $user->role) === 'ADMIN';
        $isMember = $store->members()->where('user_id', $user->id)->where('is_active', true)->exists();

        abort_unless($isAdmin || $isMember, 403, '이 매장의 관리자 권한이 없습니다.');

        $orders = Order::query()->with(['user:id,name', 'items.menu:id,name'])
            ->where('store_id', $store->id)->latest()->limit(50)->get();

        $orderStatus = [
            'PENDING_PAYMENT' => '결제대기', 'PAID' => '접수', 'PREPARING' => '제조중',
            'READY' => '제조완료', 'COMPLETED' => '완료', 'CANCELLED' => '취소', 'REFUNDED' => '환불',
        ];
        $reservationStatus = [
            'PENDING_APPROVAL' => '대기', 'AWAITING_PAYMENT' => '결제대기', 'CONFIRMED' => '승인',
            'REJECTED' => '거절', 'CANCELLED' => '취소', 'COMPLETED' => '완료',
            'NO_SHOW' => '노쇼', 'PAYMENT_FAILED' => '결제실패', 'EXPIRED' => '만료',
        ];

        $salesByDay = $orders->filter(fn ($order) => ! in_array($order->status, ['CANCELLED', 'REFUNDED'], true))
            ->groupBy(fn ($order) => $order->created_at->format('Y-m-d'))
            ->map(fn ($items) => (int) $items->sum('final_amount'));
        $sales = collect(range(6, 0))->map(fn ($days) => (int) ($salesByDay[now()->subDays($days)->format('Y-m-d')] ?? 0))->values();

        $menuItems = Menu::query()->with('category:id,name')->where('store_id', $store->id)->orderBy('id')->get();
        $ranking = $orders->flatMap->items->groupBy('menu_id')->map(function ($items) {
            $first = $items->first();
            return ['id' => $first->menu_id, 'name' => $first->menu?->name ?? $first->menu_name_snapshot, 'unit' => '개', 'sold' => $items->sum('quantity')];
        })->sortByDesc('sold')->values();
        $noshowPolicy = $store->noshowPolicy;
        $penaltyMap = ['NONE' => 'none', 'POINT' => 'point', 'RESERVATION_BLOCK' => 'block'];

        return response()->json([
            'store' => $store,
            'noshow' => [
                'deposit' => (bool) ($noshowPolicy?->deposit_required ?? false),
                'freeCancel' => (string) ($noshowPolicy?->free_cancellation_minutes ?? 60),
                'penalty' => $penaltyMap[$noshowPolicy?->penalty_type ?? 'NONE'] ?? 'none',
            ],
            'sales' => $sales,
            'inventory' => Inventory::query()->where('store_id', $store->id)->where('is_active', true)->orderBy('id')->get()->map(fn ($item) => [
                'id' => $item->id, 'name' => $item->ingredient_name, 'tag' => $item->unit,
                'qty' => (float) $item->quantity, 'threshold' => (float) $item->low_stock_threshold,
            ]),
            'menuItems' => $menuItems->map(fn ($menu) => [
                'id' => $menu->id, 'name' => $menu->name, 'category' => $menu->category?->name ?? '기타',
                'price' => (int) $menu->price, 'cost' => 0, 'soldOut' => ! $menu->is_available,
            ]),
            'menuRanking' => $ranking,
            'orders' => $orders->map(fn ($order) => [
                'id' => $order->id, 'code' => $order->order_number, 'customerName' => $order->user?->name ?? '고객',
                'items' => $order->items->map(fn ($item) => ($item->menu?->name ?? $item->menu_name_snapshot).' x'.$item->quantity)->join(', '),
                'amount' => (int) $order->final_amount, 'status' => $orderStatus[$order->status] ?? $order->status,
                'time' => $order->created_at->format('Y-m-d H:i'),
            ]),
            'reservations' => Reservation::query()->with('slot')->where('store_id', $store->id)->latest()->limit(50)->get()->map(fn ($reservation) => [
                'id' => $reservation->id, 'name' => $reservation->customer_name, 'people' => $reservation->guest_count,
                'date' => $reservation->slot?->slot_date?->format('Y-m-d') ?? $reservation->created_at->format('Y-m-d'),
                'time' => $reservation->slot?->start_time ?? '', 'status' => $reservationStatus[$reservation->status] ?? $reservation->status,
            ]),
        ]);
    }
}
