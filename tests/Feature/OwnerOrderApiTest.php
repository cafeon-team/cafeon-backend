<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Store;
use App\Models\StoreMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OwnerOrderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_list_store_orders_with_filters(): void
    {
        [$owner, $store] = $this->fixture();
        $customer = User::factory()->create([
            'name' => '검색 고객',
            'profile_image_url' => 'https://cdn.example.com/customers/search-customer.jpg',
            'profile_thumbnail_url' => 'https://cdn.example.com/customers/search-customer-thumb.webp',
        ]);
        $order = $this->order($customer, $store, 'PAID');
        Sanctum::actingAs($owner);

        $this->getJson("/api/owner/stores/{$store->id}/orders?status=PAID&keyword=검색")
            ->assertOk()
            ->assertJsonPath('data.0.id', $order->id)
            ->assertJsonPath('data.0.user.name', '검색 고객')
            ->assertJsonPath('data.0.user.profile_image_url', 'https://cdn.example.com/customers/search-customer-thumb.webp')
            ->assertJsonPath('data.0.profile_image_url', 'https://cdn.example.com/customers/search-customer-thumb.webp');

        $this->getJson("/api/owner/stores/{$store->id}/dashboard")
            ->assertOk()
            ->assertJsonPath('orders.0.profile_image_url', 'https://cdn.example.com/customers/search-customer-thumb.webp')
            ->assertJsonPath('orders.0.customerProfileImageUrl', 'https://cdn.example.com/customers/search-customer-thumb.webp');
    }

    public function test_owner_can_advance_paid_order_until_completed(): void
    {
        [$owner, $store] = $this->fixture();
        $customer = User::factory()->create();
        $order = $this->order($customer, $store, 'PAID');
        Sanctum::actingAs($owner);

        $this->patchJson("/api/owner/orders/{$order->id}/status", ['status' => 'PREPARING'])
            ->assertOk()->assertJsonPath('order.status', 'PREPARING');
        $this->patchJson("/api/owner/orders/{$order->id}/status", ['status' => 'READY'])
            ->assertOk()->assertJsonPath('order.status', 'READY');
        $this->patchJson("/api/owner/orders/{$order->id}/status", ['status' => 'COMPLETED'])
            ->assertOk()->assertJsonPath('order.status', 'COMPLETED');

        $this->assertDatabaseHas('customer_visits', [
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'store_id' => $store->id,
            'type' => 'PURCHASE',
            'confirmed_by' => $owner->id,
        ]);
        $this->assertNotNull($order->fresh()->preparing_at);
        $this->assertNotNull($order->fresh()->ready_at);
        $this->assertNotNull($order->fresh()->completed_at);
    }

    public function test_order_status_cannot_skip_steps_or_be_cancelled_by_status_api(): void
    {
        [$owner, $store] = $this->fixture();
        $order = $this->order(User::factory()->create(), $store, 'PAID');
        Sanctum::actingAs($owner);

        $this->patchJson("/api/owner/orders/{$order->id}/status", ['status' => 'READY'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->patchJson("/api/owner/orders/{$order->id}/status", ['status' => 'REFUNDED'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'PAID']);
    }

    public function test_owner_can_reject_a_paid_order_and_refund_its_payment(): void
    {
        config(['services.toss_payments.secret_key' => 'test-secret']);
        Http::fake([
            '*/v1/payments/payment-key/cancel' => Http::response([
                'paymentKey' => 'payment-key',
                'cancels' => [['cancelAmount' => 5000]],
            ]),
        ]);

        [$owner, $store] = $this->fixture();
        $customer = User::factory()->create();
        $order = $this->order($customer, $store, 'PAID');
        Payment::create([
            'order_id' => $order->id,
            'toss_order_id' => 'toss-'.$order->id,
            'payment_key' => 'payment-key',
            'amount' => 5000,
            'status' => 'DONE',
            'approved_at' => now(),
        ]);
        Sanctum::actingAs($owner);

        $this->postJson("/api/owner/orders/{$order->id}/cancel")
            ->assertOk()
            ->assertJsonPath('order.status', 'REFUNDED');

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'status' => 'CANCELLED',
            'cancelled_amount' => 5000,
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $customer->id,
            'title' => '주문이 거절되었습니다.',
        ]);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/v1/payments/payment-key/cancel'));
    }

    public function test_owner_can_cancel_an_order_that_is_already_being_prepared(): void
    {
        config(['services.toss_payments.secret_key' => 'test-secret']);
        Http::fake([
            '*/v1/payments/preparing-payment-key/cancel' => Http::response([
                'paymentKey' => 'preparing-payment-key',
                'cancels' => [['cancelAmount' => 5000]],
            ]),
        ]);

        [$owner, $store] = $this->fixture();
        $order = $this->order(User::factory()->create(), $store, 'PREPARING');
        Payment::create([
            'order_id' => $order->id,
            'toss_order_id' => 'toss-'.$order->id,
            'payment_key' => 'preparing-payment-key',
            'amount' => 5000,
            'status' => 'DONE',
            'approved_at' => now(),
        ]);
        Sanctum::actingAs($owner);

        $this->postJson("/api/owner/orders/{$order->id}/cancel")
            ->assertOk()
            ->assertJsonPath('order.status', 'REFUNDED');
    }

    public function test_unrelated_user_cannot_manage_orders(): void
    {
        [, $store] = $this->fixture();
        $order = $this->order(User::factory()->create(), $store, 'PAID');
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/owner/stores/{$store->id}/orders")->assertForbidden();
        $this->patchJson("/api/owner/orders/{$order->id}/status", ['status' => 'PREPARING'])->assertForbidden();
    }

    private function fixture(): array
    {
        $owner = User::factory()->create(['role' => 'OWNER']);
        $store = Store::create([
            'name' => '주문 관리 매장',
            'slug' => 'owner-orders-'.uniqid(),
            'address' => '서울',
            'is_active' => true,
        ]);
        StoreMember::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'role' => 'OWNER',
            'is_active' => true,
        ]);

        return [$owner, $store];
    }

    private function order(User $customer, Store $store, string $status): Order
    {
        return Order::create([
            'order_number' => 'ORD-'.uniqid(),
            'user_id' => $customer->id,
            'store_id' => $store->id,
            'menu_amount' => 5000,
            'final_amount' => 5000,
            'status' => $status,
            'paid_at' => $status === 'PAID' ? now() : null,
        ]);
    }
}
