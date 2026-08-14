<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->with(['store:id,name,slug,thumbnail_url', 'items.menu:id,name,image_url'])
            ->where('user_id', $request->user()->id)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate(10);

        return response()->json($orders);
    }

    public function store(StoreOrderRequest $request, OrderService $orders): JsonResponse
    {
        return response()->json([
            'message' => '주문이 생성되었습니다.',
            'order' => $orders->create($request->user(), $request->validated()),
        ], 201);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        return response()->json(['order' => $order->load(['store:id,name,slug,address,phone', 'items.menu:id,name,image_url', 'payment'])]);
    }

    public function cancel(Request $request, Order $order, OrderService $orders): JsonResponse
    {
        return response()->json([
            'message' => '주문이 취소되었습니다.',
            'order' => $orders->cancel($request->user(), $order),
        ]);
    }
}
