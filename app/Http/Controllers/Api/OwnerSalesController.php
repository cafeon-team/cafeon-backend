<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OwnerSalesController extends Controller
{
    public function index(Request $request, Store $store): JsonResponse
    {
        $this->authorizeStore($request, $store);
        $data = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $from = Carbon::parse($data['from'] ?? now()->subDays(29)->toDateString())->startOfDay();
        $to = Carbon::parse($data['to'] ?? now()->toDateString())->endOfDay();
        abort_if($from->diffInDays($to) > 366, 422, '조회 기간은 최대 1년입니다.');
        $statuses = ['PAID', 'PREPARING', 'READY', 'COMPLETED'];
        $orders = Order::where('store_id', $store->id)->whereIn('status', $statuses)->whereBetween('paid_at', [$from, $to])->get();
        $trend = $orders->groupBy(fn ($o) => $o->paid_at->format('Y-m-d'))->map(fn ($rows, $day) => ['date' => $day, 'sales' => (int) $rows->sum('final_amount'), 'orders' => $rows->count()])->values();
        $items = OrderItem::query()->whereHas('order', fn ($q) => $q->where('store_id', $store->id)->whereIn('status', $statuses)->whereBetween('paid_at', [$from, $to]))
            ->selectRaw('menu_id, menu_name, SUM(quantity) as quantity, SUM(line_amount) as sales')->groupBy('menu_id', 'menu_name')->orderByDesc('sales')->limit(20)->get();

        return response()->json(['period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()], 'summary' => ['total_sales' => (int) $orders->sum('final_amount'), 'order_count' => $orders->count(), 'average_order_amount' => $orders->count() ? (int) round($orders->avg('final_amount')) : 0], 'trend' => $trend, 'menu_ranking' => $items]);
    }

    private function authorizeStore(Request $request, Store $store): void
    {
        $user = $request->user();
        $allowed = strtoupper((string) $user->role) === 'ADMIN' || $store->members()->where('user_id', $user->id)->where('is_active', true)->whereIn('role', ['OWNER', 'MANAGER'])->exists();
        abort_unless($allowed, 403, '매출을 조회할 권한이 없습니다.');
    }
}
