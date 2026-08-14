<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\TossPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function checkout(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);
        abort_unless($order->status === 'PENDING_PAYMENT' && (int) $order->final_amount > 0, 422, '결제를 요청할 수 없는 주문입니다.');
        $payment = $order->payment()->firstOrFail();
        $clientKey = config('services.toss_payments.client_key');
        abort_unless(filled($clientKey), 503, '토스페이먼츠 클라이언트 키가 설정되지 않았습니다.');

        return response()->json([
            'client_key' => $clientKey,
            'order_id' => $order->id,
            'toss_order_id' => $payment->toss_order_id,
            'order_name' => 'CafeON 주문 '.$order->order_number,
            'amount' => (int) $order->final_amount,
            'customer' => ['name' => $request->user()->name, 'email' => $request->user()->email],
        ]);
    }

    public function confirm(Request $request, TossPaymentService $payments): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'payment_key' => ['required', 'string', 'max:200'],
            'toss_order_id' => ['required', 'string', 'max:64'],
            'amount' => ['required', 'integer', 'min:1'],
        ]);
        $result = $payments->confirm($request->user(), $validated);

        if (! $result['ok']) {
            return response()->json([
                'message' => $result['message'],
                'provider' => $result['provider'] ?? null,
            ], $result['status']);
        }

        return response()->json([
            'message' => '결제가 승인되었습니다.',
            'idempotent' => $result['idempotent'],
            'order' => $result['order'],
            'payment' => $result['payment'],
        ]);
    }

    public function refund(Request $request, Order $order, TossPaymentService $payments): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:200'],
        ]);
        $result = $payments->refund($request->user(), $order, $validated['reason']);

        return response()->json($result['body'], $result['status']);
    }

    public function webhook(Request $request, TossPaymentService $payments): JsonResponse
    {
        $validated = $request->validate([
            'eventType' => ['required', 'string', 'in:PAYMENT_STATUS_CHANGED'],
            'data' => ['required', 'array'],
            'data.paymentKey' => ['required', 'string', 'max:200'],
            'data.orderId' => ['required', 'string', 'max:64'],
        ]);
        $transmissionId = $request->header('tosspayments-webhook-transmission-id');
        abort_unless(is_string($transmissionId) && $transmissionId !== '', 400, '웹훅 전송 ID가 없습니다.');

        $result = $payments->handleWebhook($transmissionId, $validated);

        return response()->json($result['body'], $result['status']);
    }
}
