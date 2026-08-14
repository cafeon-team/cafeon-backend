<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_get_checkout_data_and_confirm_toss_payment(): void
    {
        [$user, $order] = $this->orderFixture();
        config([
            'services.toss_payments.client_key' => 'test_ck_demo',
            'services.toss_payments.secret_key' => 'test_sk_demo',
        ]);
        Http::fake([
            '*/v1/payments/confirm' => Http::response([
                'paymentKey' => 'payment-key-123',
                'orderId' => $order->payment->toss_order_id,
                'totalAmount' => 10000,
                'status' => 'DONE',
                'method' => '카드',
                'approvedAt' => now()->toIso8601String(),
            ]),
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/payments/orders/{$order->id}/checkout")
            ->assertOk()
            ->assertJsonPath('client_key', 'test_ck_demo')
            ->assertJsonPath('amount', 10000)
            ->assertJsonPath('toss_order_id', $order->payment->toss_order_id);

        $this->postJson('/api/payments/confirm', [
            'order_id' => $order->id,
            'payment_key' => 'payment-key-123',
            'toss_order_id' => $order->payment->toss_order_id,
            'amount' => 10000,
        ])->assertOk()
            ->assertJsonPath('order.status', 'PAID')
            ->assertJsonPath('payment.status', 'DONE')
            ->assertJsonPath('payment.method', '카드');

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id, 'payment_key' => 'payment-key-123', 'status' => 'DONE',
        ]);
    }

    public function test_tampered_amount_is_rejected_before_calling_provider(): void
    {
        [$user, $order] = $this->orderFixture();
        config(['services.toss_payments.secret_key' => 'test_sk_demo']);
        Http::fake();

        $this->actingAs($user, 'sanctum')->postJson('/api/payments/confirm', [
            'order_id' => $order->id,
            'payment_key' => 'tampered-key',
            'toss_order_id' => $order->payment->toss_order_id,
            'amount' => 100,
        ])->assertUnprocessable();

        Http::assertNothingSent();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'PENDING_PAYMENT']);
    }

    public function test_another_user_cannot_confirm_payment(): void
    {
        [, $order] = $this->orderFixture();
        config(['services.toss_payments.secret_key' => 'test_sk_demo']);
        Http::fake();

        $this->actingAs(User::factory()->create(), 'sanctum')->postJson('/api/payments/confirm', [
            'order_id' => $order->id,
            'payment_key' => 'other-user-key',
            'toss_order_id' => $order->payment->toss_order_id,
            'amount' => 10000,
        ])->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_provider_failure_is_recorded_without_marking_order_paid(): void
    {
        [$user, $order] = $this->orderFixture();
        config(['services.toss_payments.secret_key' => 'test_sk_demo']);
        Http::fake(['*/v1/payments/confirm' => Http::response([
            'code' => 'INVALID_PAYMENT_KEY', 'message' => '잘못된 결제키입니다.',
        ], 400)]);

        $this->actingAs($user, 'sanctum')->postJson('/api/payments/confirm', [
            'order_id' => $order->id,
            'payment_key' => 'bad-key',
            'toss_order_id' => $order->payment->toss_order_id,
            'amount' => 10000,
        ])->assertUnprocessable();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'PENDING_PAYMENT']);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id, 'status' => 'FAILED', 'failure_code' => 'INVALID_PAYMENT_KEY',
        ]);
    }

    public function test_same_successful_confirmation_is_idempotent(): void
    {
        [$user, $order] = $this->orderFixture();
        config(['services.toss_payments.secret_key' => 'test_sk_demo']);
        Http::fake(['*/v1/payments/confirm' => Http::response([
            'paymentKey' => 'same-key', 'orderId' => $order->payment->toss_order_id,
            'totalAmount' => 10000, 'status' => 'DONE', 'method' => '카드',
            'approvedAt' => now()->toIso8601String(),
        ])]);
        $payload = [
            'order_id' => $order->id, 'payment_key' => 'same-key',
            'toss_order_id' => $order->payment->toss_order_id, 'amount' => 10000,
        ];

        $this->actingAs($user, 'sanctum')->postJson('/api/payments/confirm', $payload)
            ->assertOk()->assertJsonPath('idempotent', false);
        $this->postJson('/api/payments/confirm', $payload)
            ->assertOk()->assertJsonPath('idempotent', true);
        Http::assertSentCount(1);
    }

    public function test_customer_can_refund_paid_order_and_duplicate_request_is_safe(): void
    {
        [$user, $order] = $this->orderFixture();
        config(['services.toss_payments.secret_key' => 'test_sk_demo']);
        $order->update(['status' => 'PAID', 'paid_at' => now()]);
        $order->payment->update(['status' => 'DONE', 'payment_key' => 'refund-key', 'approved_at' => now()]);
        Http::fake(['*/v1/payments/refund-key/cancel' => Http::response([
            'paymentKey' => 'refund-key',
            'status' => 'CANCELED',
            'cancels' => [['cancelAmount' => 10000, 'cancelReason' => '고객 요청']],
        ])]);

        $this->actingAs($user, 'sanctum')->postJson("/api/payments/orders/{$order->id}/refund", [
            'reason' => '고객 요청',
        ])->assertOk()
            ->assertJsonPath('idempotent', false)
            ->assertJsonPath('order.status', 'REFUNDED')
            ->assertJsonPath('payment.status', 'CANCELLED');

        $this->postJson("/api/payments/orders/{$order->id}/refund", ['reason' => '다시 요청'])
            ->assertOk()->assertJsonPath('idempotent', true);
        Http::assertSentCount(1);
    }

    public function test_refund_provider_failure_does_not_change_local_payment(): void
    {
        [$user, $order] = $this->orderFixture();
        config(['services.toss_payments.secret_key' => 'test_sk_demo']);
        $order->update(['status' => 'PAID', 'paid_at' => now()]);
        $order->payment->update(['status' => 'DONE', 'payment_key' => 'failure-key', 'approved_at' => now()]);
        Http::fake(['*/v1/payments/failure-key/cancel' => Http::response(['code' => 'NOT_CANCELABLE', 'message' => '취소 불가'], 400)]);

        $this->actingAs($user, 'sanctum')->postJson("/api/payments/orders/{$order->id}/refund", [
            'reason' => '취소 요청',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'PAID']);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'DONE']);
    }

    public function test_verified_webhook_updates_payment_and_is_idempotent(): void
    {
        [, $order] = $this->orderFixture();
        config(['services.toss_payments.secret_key' => 'test_sk_demo']);
        $order->payment->update(['payment_key' => 'webhook-key']);
        Http::fake(['*/v1/payments/webhook-key' => Http::response([
            'paymentKey' => 'webhook-key',
            'orderId' => $order->payment->toss_order_id,
            'totalAmount' => 10000,
            'status' => 'DONE',
            'method' => '간편결제',
            'approvedAt' => now()->toIso8601String(),
        ])]);
        $event = [
            'eventType' => 'PAYMENT_STATUS_CHANGED',
            'data' => ['paymentKey' => 'webhook-key', 'orderId' => $order->payment->toss_order_id],
        ];
        $headers = ['tosspayments-webhook-transmission-id' => 'transmission-123'];

        $this->postJson('/api/webhooks/toss-payments', $event, $headers)
            ->assertOk()->assertJsonPath('idempotent', false);
        $this->postJson('/api/webhooks/toss-payments', $event, $headers)
            ->assertOk()->assertJsonPath('idempotent', true);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'PAID']);
        $this->assertDatabaseHas('payment_webhook_events', [
            'transmission_id' => 'transmission-123', 'status' => 'PROCESSED',
        ]);
        Http::assertSentCount(1);
    }

    public function test_webhook_with_mismatched_provider_amount_is_rejected(): void
    {
        [, $order] = $this->orderFixture();
        config(['services.toss_payments.secret_key' => 'test_sk_demo']);
        $order->payment->update(['payment_key' => 'mismatch-key']);
        Http::fake(['*/v1/payments/mismatch-key' => Http::response([
            'paymentKey' => 'mismatch-key', 'orderId' => $order->payment->toss_order_id,
            'totalAmount' => 1, 'status' => 'DONE',
        ])]);

        $this->postJson('/api/webhooks/toss-payments', [
            'eventType' => 'PAYMENT_STATUS_CHANGED',
            'data' => ['paymentKey' => 'mismatch-key', 'orderId' => $order->payment->toss_order_id],
        ], ['tosspayments-webhook-transmission-id' => 'transmission-bad'])
            ->assertUnprocessable();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'PENDING_PAYMENT']);
        $this->assertDatabaseHas('payment_webhook_events', ['transmission_id' => 'transmission-bad', 'status' => 'FAILED']);
    }

    private function orderFixture(): array
    {
        $user = User::factory()->create();
        $store = Store::create(['name' => 'Payment Cafe', 'slug' => 'payment-'.uniqid(), 'address' => 'Seoul', 'is_active' => true]);
        $menu = Menu::create(['store_id' => $store->id, 'name' => 'Latte', 'price' => 10000, 'is_available' => true]);
        $orderId = $this->actingAs($user, 'sanctum')->postJson('/api/orders', [
            'store_id' => $store->id,
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertCreated()->json('order.id');

        return [$user, Order::with('payment')->findOrFail($orderId)];
    }
}
