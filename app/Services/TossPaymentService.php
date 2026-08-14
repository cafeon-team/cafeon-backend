<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Models\PointTransaction;
use App\Models\User;
use App\Models\UserCoupon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class TossPaymentService
{
    public function confirm(User $user, array $data): array
    {
        return DB::transaction(function () use ($user, $data) {
            $order = Order::query()->lockForUpdate()->findOrFail($data['order_id']);
            abort_unless($order->user_id === $user->id, 404);
            abort_unless((int) $order->final_amount > 0, 422, '결제할 금액이 없는 주문입니다.');
            abort_unless((int) $order->final_amount === (int) $data['amount'], 422, '주문 결제금액이 일치하지 않습니다.');

            $payment = Payment::query()->where('order_id', $order->id)->lockForUpdate()->firstOrFail();
            abort_unless(hash_equals($payment->toss_order_id, $data['toss_order_id']), 422, '결제 주문번호가 일치하지 않습니다.');

            if ($payment->status === 'DONE') {
                abort_unless(hash_equals((string) $payment->payment_key, $data['payment_key']), 422, '이미 다른 결제키로 승인된 주문입니다.');

                return ['ok' => true, 'order' => $order, 'payment' => $payment, 'idempotent' => true];
            }
            abort_unless($order->status === 'PENDING_PAYMENT' && in_array($payment->status, ['READY', 'FAILED'], true), 422, '승인할 수 없는 결제 상태입니다.');

            $secretKey = config('services.toss_payments.secret_key');
            abort_unless(filled($secretKey), 503, '토스페이먼츠 시크릿 키가 설정되지 않았습니다.');

            try {
                $response = Http::withBasicAuth($secretKey, '')
                    ->acceptJson()
                    ->timeout(10)
                    ->retry(2, 200, throw: false)
                    ->post(rtrim(config('services.toss_payments.base_url'), '/').'/v1/payments/confirm', [
                        'paymentKey' => $data['payment_key'],
                        'orderId' => $data['toss_order_id'],
                        'amount' => (int) $data['amount'],
                    ]);
            } catch (ConnectionException) {
                return ['ok' => false, 'status' => 502, 'message' => '결제 승인 서버에 연결할 수 없습니다.'];
            }

            $body = $response->json() ?? [];
            if ($response->failed()) {
                $payment->forceFill([
                    'status' => 'FAILED',
                    'failure_code' => $body['code'] ?? 'PAYMENT_CONFIRM_FAILED',
                    'failure_message' => $body['message'] ?? '결제 승인에 실패했습니다.',
                ])->save();

                return ['ok' => false, 'status' => 422, 'message' => '결제 승인에 실패했습니다.', 'provider' => $body];
            }

            abort_unless(($body['orderId'] ?? null) === $payment->toss_order_id, 502, '결제사의 주문번호 응답이 일치하지 않습니다.');
            abort_unless((int) ($body['totalAmount'] ?? -1) === (int) $order->final_amount, 502, '결제사의 승인 금액이 일치하지 않습니다.');
            abort_unless(($body['status'] ?? null) === 'DONE', 502, '결제가 완료 상태가 아닙니다.');

            $payment->forceFill([
                'payment_key' => $body['paymentKey'] ?? $data['payment_key'],
                'method' => $body['method'] ?? null,
                'status' => 'DONE',
                'failure_code' => null,
                'failure_message' => null,
                'approved_at' => $body['approvedAt'] ?? now(),
            ])->save();
            $order->forceFill(['status' => 'PAID', 'paid_at' => $payment->approved_at])->save();

            return ['ok' => true, 'order' => $order->fresh(), 'payment' => $payment->fresh(), 'idempotent' => false];
        });
    }

    public function refund(User $user, Order $order, string $reason): array
    {
        return DB::transaction(function () use ($user, $order, $reason) {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            abort_unless($lockedOrder->user_id === $user->id, 404);
            $payment = Payment::query()->where('order_id', $lockedOrder->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->status === 'REFUNDED' && $payment->status === 'CANCELLED') {
                return ['status' => 200, 'body' => ['message' => '이미 환불된 주문입니다.', 'idempotent' => true, 'order' => $lockedOrder, 'payment' => $payment]];
            }
            abort_unless($lockedOrder->status === 'PAID' && $payment->status === 'DONE', 422, '환불할 수 없는 주문 상태입니다.');
            abort_unless(filled($payment->payment_key), 422, '결제키가 없는 주문입니다.');

            $response = $this->request('POST', '/v1/payments/'.rawurlencode($payment->payment_key).'/cancel', [
                'cancelReason' => $reason,
            ], ['Idempotency-Key' => 'cafeon-refund-'.$lockedOrder->id]);
            if (! $response['ok']) {
                return ['status' => $response['status'], 'body' => ['message' => '결제 취소에 실패했습니다.', 'provider' => $response['body']]];
            }

            $body = $response['body'];
            $cancelledAmount = collect($body['cancels'] ?? [])->sum(fn ($cancel) => (int) ($cancel['cancelAmount'] ?? 0));
            abort_unless(($body['paymentKey'] ?? null) === $payment->payment_key, 502, '결제 취소 응답의 결제키가 일치하지 않습니다.');
            abort_unless($cancelledAmount >= (int) $payment->amount, 502, '전액 취소가 확인되지 않았습니다.');

            $payment->forceFill([
                'status' => 'CANCELLED',
                'cancelled_amount' => $cancelledAmount,
                'cancel_reason' => $reason,
                'cancelled_at' => now(),
            ])->save();
            $lockedOrder->forceFill(['status' => 'REFUNDED', 'refunded_at' => now()])->save();
            $this->restoreBenefits($lockedOrder);

            return ['status' => 200, 'body' => ['message' => '결제가 전액 환불되었습니다.', 'idempotent' => false, 'order' => $lockedOrder->fresh(), 'payment' => $payment->fresh()]];
        });
    }

    public function handleWebhook(string $transmissionId, array $event): array
    {
        $existing = PaymentWebhookEvent::where('transmission_id', $transmissionId)->first();
        if ($existing?->status === 'PROCESSED') {
            return ['status' => 200, 'body' => ['message' => '이미 처리된 웹훅입니다.', 'idempotent' => true]];
        }

        $record = $existing ?? PaymentWebhookEvent::create([
            'transmission_id' => $transmissionId,
            'event_type' => $event['eventType'],
            'payment_key' => $event['data']['paymentKey'],
            'payload' => $event,
            'status' => 'RECEIVED',
        ]);

        $verified = $this->request('GET', '/v1/payments/'.rawurlencode($event['data']['paymentKey']));
        if (! $verified['ok']) {
            $record->update(['status' => 'FAILED', 'failure_message' => '결제 조회 검증 실패']);

            return ['status' => 502, 'body' => ['message' => '웹훅 결제 검증에 실패했습니다.']];
        }

        $provider = $verified['body'];
        $result = DB::transaction(function () use ($record, $provider) {
            $payment = Payment::query()->where('toss_order_id', $provider['orderId'] ?? '')->lockForUpdate()->first();
            if (! $payment || ! hash_equals((string) $payment->payment_key, (string) ($provider['paymentKey'] ?? ''))) {
                return false;
            }
            $order = Order::query()->lockForUpdate()->findOrFail($payment->order_id);
            if ((int) $payment->amount !== (int) ($provider['totalAmount'] ?? -1)) {
                return false;
            }

            $status = $provider['status'] ?? null;
            if ($status === 'DONE') {
                $payment->update(['status' => 'DONE', 'method' => $provider['method'] ?? $payment->method, 'approved_at' => $provider['approvedAt'] ?? $payment->approved_at]);
                $order->update(['status' => 'PAID', 'paid_at' => $payment->fresh()->approved_at ?? now()]);
            } elseif (in_array($status, ['CANCELED', 'PARTIAL_CANCELED'], true)) {
                $cancelledAmount = collect($provider['cancels'] ?? [])->sum(fn ($cancel) => (int) ($cancel['cancelAmount'] ?? 0));
                $full = $status === 'CANCELED' || $cancelledAmount >= (int) $payment->amount;
                $payment->update(['status' => $full ? 'CANCELLED' : 'PARTIAL_CANCELLED', 'cancelled_amount' => $cancelledAmount, 'cancelled_at' => $full ? now() : null]);
                if ($full) {
                    $order->update(['status' => 'REFUNDED', 'refunded_at' => now()]);
                    $this->restoreBenefits($order);
                }
            }

            $record->update(['status' => 'PROCESSED', 'failure_message' => null, 'processed_at' => now()]);

            return true;
        });

        if (! $result) {
            $record->update(['status' => 'FAILED', 'failure_message' => '주문번호, 결제키 또는 금액 불일치']);

            return ['status' => 422, 'body' => ['message' => '웹훅 결제정보가 일치하지 않습니다.']];
        }

        return ['status' => 200, 'body' => ['message' => '웹훅이 처리되었습니다.', 'idempotent' => false]];
    }

    private function request(string $method, string $path, array $body = [], array $headers = []): array
    {
        $secretKey = config('services.toss_payments.secret_key');
        abort_unless(filled($secretKey), 503, '토스페이먼츠 시크릿 키가 설정되지 않았습니다.');

        try {
            $pending = Http::withBasicAuth($secretKey, '')->withHeaders($headers)->acceptJson()->timeout(10)->retry(2, 200, throw: false);
            $response = $method === 'GET'
                ? $pending->get(rtrim(config('services.toss_payments.base_url'), '/').$path)
                : $pending->post(rtrim(config('services.toss_payments.base_url'), '/').$path, $body);
        } catch (ConnectionException) {
            return ['ok' => false, 'status' => 502, 'body' => ['code' => 'CONNECTION_FAILED']];
        }

        return ['ok' => $response->successful(), 'status' => $response->failed() ? 422 : 200, 'body' => $response->json() ?? []];
    }

    private function restoreBenefits(Order $order): void
    {
        if ($order->point_used > 0) {
            $account = $order->user->customerStoreAccounts()->where('store_id', $order->store_id)->lockForUpdate()->firstOrFail();
            $key = 'order-point-refund-'.$order->id;
            if (! PointTransaction::where('idempotency_key', $key)->exists()) {
                $account->increment('point_balance', $order->point_used);
                PointTransaction::create([
                    'customer_store_account_id' => $account->id, 'type' => 'CANCEL', 'amount' => $order->point_used,
                    'balance_after' => $account->fresh()->point_balance, 'reason' => 'PAYMENT_REFUND',
                    'reference_type' => Order::class, 'reference_id' => $order->id,
                    'idempotency_key' => $key, 'created_at' => now(),
                ]);
            }
        }

        $userCoupon = UserCoupon::where('used_order_id', $order->id)->lockForUpdate()->first();
        if ($userCoupon) {
            $available = $userCoupon->expires_at->isFuture() && $userCoupon->coupon()->where('is_active', true)->where('valid_until', '>', now())->exists();
            $userCoupon->update(['status' => $available ? 'AVAILABLE' : 'EXPIRED', 'used_order_id' => null, 'used_at' => null]);
        }
    }
}
