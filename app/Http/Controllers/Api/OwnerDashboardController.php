<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Store;
use App\Services\OwnerStoreAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OwnerDashboardController extends Controller
{
    public function __construct(private readonly OwnerStoreAccessService $storeAccess) {}

    public function showMine(Request $request): JsonResponse
    {
        return $this->show($request, $this->storeAccess->primary($request->user()));
    }

    public function show(Request $request, Store $store): JsonResponse
    {
        $this->storeAccess->authorize($request->user(), $store);

        $orders = Order::query()->with(['user:id,name,profile_image_url,profile_thumbnail_url', 'items.menu:id,name'])
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

        $todaySalesOrders = Order::query()
            ->where('store_id', $store->id)
            ->whereIn('status', ['PAID', 'PREPARING', 'READY', 'COMPLETED'])
            ->whereDate('paid_at', today())
            ->get();
        $hours = collect(range(9, max(9, now()->hour)));
        $sales = $hours->map(fn (int $hour) => (int) $todaySalesOrders
            ->filter(fn ($order) => $order->paid_at && $order->paid_at->hour <= $hour)
            ->sum('final_amount'));

        $menuItems = Menu::query()->with('category:id,name')->where('store_id', $store->id)->orderBy('id')->get();
        $ranking = $orders->flatMap->items->groupBy('menu_id')->map(function ($items) {
            $first = $items->first();

            return ['id' => $first->menu_id, 'name' => $first->menu?->name ?? $first->menu_name_snapshot, 'unit' => '개', 'sold' => $items->sum('quantity')];
        })->sortByDesc('sold')->values();
        $noshowPolicy = $store->noshowPolicy;
        $penaltyMap = ['NONE' => 'none', 'POINT' => 'point', 'RESERVATION_BLOCK' => 'block'];

        $reservations = Reservation::query()->with('slot')->where('store_id', $store->id)->latest()->limit(50)->get();
        $pendingReservations = Reservation::query()->with('slot')
            ->where('store_id', $store->id)
            ->where('status', 'PENDING_APPROVAL')
            ->latest()
            ->limit(50)
            ->get();
        $activeSeats = $store->seats()->where('is_active', true)->get();

        return response()->json([
            'store' => $store->makeVisible('business_info')->loadMissing('businessHours'),
            'summary' => [
                'is_open' => (bool) $store->is_open,
                'today_sales' => (int) $todaySalesOrders->sum('final_amount'),
                'seat_count' => $activeSeats->count(),
                'seat_capacity' => (int) $activeSeats->sum('capacity'),
                'reservation_count' => Reservation::where('store_id', $store->id)->count(),
                'pending_reservation_count' => Reservation::where('store_id', $store->id)->where('status', 'PENDING_APPROVAL')->count(),
            ],
            'noshow' => [
                'deposit' => (bool) ($noshowPolicy?->deposit_required ?? false),
                'freeCancel' => (string) ($noshowPolicy?->free_cancellation_minutes ?? 60),
                'penalty' => $penaltyMap[$noshowPolicy?->penalty_type ?? 'NONE'] ?? 'none',
            ],
            'sales' => $sales,
            'sales_meta' => [
                'date' => today()->toDateString(),
                'start_hour' => 9,
                'interval' => 'hour',
                'aggregation' => 'cumulative',
                'hours' => $hours,
                'currency' => 'KRW',
            ],
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
                'profile_image_url' => $order->user?->profile_thumbnail_url ?? $order->user?->profile_image_url,
                'customerProfileImageUrl' => $order->user?->profile_thumbnail_url ?? $order->user?->profile_image_url,
                'items' => $order->items->map(fn ($item) => ($item->menu?->name ?? $item->menu_name_snapshot).' x'.$item->quantity)->join(', '),
                'amount' => (int) $order->final_amount, 'status' => $orderStatus[$order->status] ?? $order->status,
                'time' => $order->created_at->format('Y-m-d H:i'),
            ]),
            'reservations' => $reservations->map(fn ($reservation) => [
                'id' => $reservation->id, 'name' => $reservation->customer_name, 'people' => $reservation->guest_count,
                'date' => $reservation->slot?->slot_date?->format('Y-m-d') ?? $reservation->created_at->format('Y-m-d'),
                'time' => $reservation->slot?->start_time ?? '', 'status' => $reservationStatus[$reservation->status] ?? $reservation->status,
            ]),
            'pendingReservations' => $pendingReservations->map(fn ($reservation) => [
                'id' => $reservation->id,
                'name' => $reservation->customer_name,
                'people' => $reservation->guest_count,
                'date' => $reservation->slot?->slot_date?->format('Y-m-d') ?? $reservation->created_at->format('Y-m-d'),
                'time' => $reservation->slot?->start_time ?? '',
                'status' => '대기',
            ]),
        ]);
    }
}
