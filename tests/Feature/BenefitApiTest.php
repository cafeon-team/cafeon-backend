<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\CustomerStoreAccount;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Models\UserCoupon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BenefitApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_coupon_and_points_are_applied_atomically_to_order(): void
    {
        $user = User::factory()->create();
        $store = Store::create(['name' => 'CafeON', 'slug' => 'cafeon', 'address' => 'Seoul', 'is_active' => true]);
        $menu = Menu::create(['store_id' => $store->id, 'name' => 'Latte', 'price' => 10000, 'is_available' => true]);
        $account = CustomerStoreAccount::create(['user_id' => $user->id, 'store_id' => $store->id, 'point_balance' => 2000]);
        $coupon = Coupon::create([
            'store_id' => $store->id, 'created_by' => $user->id, 'name' => 'Discount',
            'discount_type' => 'FIXED', 'discount_value' => 1000, 'minimum_order_amount' => 0,
            'valid_from' => now()->subDay(), 'valid_until' => now()->addDay(), 'is_active' => true,
        ]);
        $userCoupon = UserCoupon::create([
            'coupon_id' => $coupon->id, 'user_id' => $user->id, 'coupon_code' => 'TEST1000',
            'status' => 'AVAILABLE', 'issued_at' => now(), 'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($user, 'sanctum')->postJson('/api/orders', [
            'store_id' => $store->id,
            'user_coupon_id' => $userCoupon->id,
            'point_used' => 500,
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertCreated()
            ->assertJsonPath('order.coupon_discount_amount', '1000.00')
            ->assertJsonPath('order.point_used', 500)
            ->assertJsonPath('order.final_amount', '8500.00');

        $this->assertDatabaseHas('user_coupons', ['id' => $userCoupon->id, 'status' => 'USED']);
        $this->assertDatabaseHas('customer_store_accounts', ['id' => $account->id, 'point_balance' => 1500]);
    }

    public function test_user_can_view_coupons_and_membership(): void
    {
        $user = User::factory()->create();
        $store = Store::create(['name' => 'CafeON', 'slug' => 'cafeon', 'address' => 'Seoul', 'is_active' => true]);
        CustomerStoreAccount::create(['user_id' => $user->id, 'store_id' => $store->id, 'point_balance' => 1200]);

        $this->actingAs($user, 'sanctum')->getJson('/api/users/me/membership')
            ->assertOk()->assertJsonPath('total_points', 1200);
        $this->getJson('/api/users/me/coupons')->assertOk();
    }

    public function test_cancelling_pending_order_restores_used_points_with_history(): void
    {
        $user = User::factory()->create();
        $store = Store::create(['name' => 'Point Cafe', 'slug' => 'point-cafe', 'address' => 'Seoul', 'is_active' => true]);
        $menu = Menu::create(['store_id' => $store->id, 'name' => 'Coffee', 'price' => 5000, 'is_available' => true]);
        $account = CustomerStoreAccount::create(['user_id' => $user->id, 'store_id' => $store->id, 'point_balance' => 2000]);

        $orderId = $this->actingAs($user, 'sanctum')->postJson('/api/orders', [
            'store_id' => $store->id,
            'point_used' => 1000,
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertCreated()->json('order.id');

        $this->assertDatabaseHas('customer_store_accounts', ['id' => $account->id, 'point_balance' => 1000]);
        $this->postJson("/api/users/me/orders/{$orderId}/cancel")
            ->assertOk()->assertJsonPath('order.status', 'CANCELLED');

        $this->assertDatabaseHas('customer_store_accounts', ['id' => $account->id, 'point_balance' => 2000]);
        $this->assertDatabaseHas('point_transactions', [
            'customer_store_account_id' => $account->id,
            'type' => 'CANCEL',
            'amount' => 1000,
            'balance_after' => 2000,
            'reason' => 'ORDER_CANCEL',
            'reference_id' => $orderId,
        ]);
    }

    public function test_order_rejects_points_above_balance_without_changing_account(): void
    {
        $user = User::factory()->create();
        $store = Store::create(['name' => 'Balance Cafe', 'slug' => 'balance-cafe', 'address' => 'Seoul', 'is_active' => true]);
        $menu = Menu::create(['store_id' => $store->id, 'name' => 'Coffee', 'price' => 5000, 'is_available' => true]);
        $account = CustomerStoreAccount::create(['user_id' => $user->id, 'store_id' => $store->id, 'point_balance' => 500]);

        $this->actingAs($user, 'sanctum')->postJson('/api/orders', [
            'store_id' => $store->id,
            'point_used' => 1000,
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertUnprocessable();

        $this->assertDatabaseHas('customer_store_accounts', ['id' => $account->id, 'point_balance' => 500]);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('point_transactions', 0);
    }

    public function test_order_rejects_points_above_payable_amount(): void
    {
        $user = User::factory()->create();
        $store = Store::create(['name' => 'Payable Cafe', 'slug' => 'payable-cafe', 'address' => 'Seoul', 'is_active' => true]);
        $menu = Menu::create(['store_id' => $store->id, 'name' => 'Coffee', 'price' => 3000, 'is_available' => true]);
        CustomerStoreAccount::create(['user_id' => $user->id, 'store_id' => $store->id, 'point_balance' => 5000]);

        $this->actingAs($user, 'sanctum')->postJson('/api/orders', [
            'store_id' => $store->id,
            'point_used' => 4000,
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_cancelling_pending_order_restores_coupon(): void
    {
        [$user, $store, $menu, $userCoupon] = $this->couponFixture();

        $orderId = $this->actingAs($user, 'sanctum')->postJson('/api/orders', [
            'store_id' => $store->id,
            'user_coupon_id' => $userCoupon->id,
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertCreated()->json('order.id');

        $this->assertDatabaseHas('user_coupons', [
            'id' => $userCoupon->id, 'status' => 'USED', 'used_order_id' => $orderId,
        ]);

        $this->postJson("/api/users/me/orders/{$orderId}/cancel")->assertOk();
        $this->assertDatabaseHas('user_coupons', [
            'id' => $userCoupon->id, 'status' => 'AVAILABLE', 'used_order_id' => null, 'used_at' => null,
        ]);
    }

    public function test_expired_coupon_is_rejected_and_marked_expired_when_listed(): void
    {
        [$user, $store, $menu, $userCoupon] = $this->couponFixture(expiresAt: now()->subMinute());

        $this->actingAs($user, 'sanctum')->postJson('/api/orders', [
            'store_id' => $store->id,
            'user_coupon_id' => $userCoupon->id,
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertUnprocessable();

        $this->getJson('/api/users/me/coupons')->assertOk();
        $this->assertDatabaseHas('user_coupons', ['id' => $userCoupon->id, 'status' => 'EXPIRED']);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_coupon_from_another_store_is_rejected_without_being_consumed(): void
    {
        [$user, , , $userCoupon] = $this->couponFixture();
        $otherStore = Store::create(['name' => 'Other Cafe', 'slug' => 'other-cafe', 'address' => 'Seoul', 'is_active' => true]);
        $otherMenu = Menu::create(['store_id' => $otherStore->id, 'name' => 'Tea', 'price' => 10000, 'is_available' => true]);

        $this->actingAs($user, 'sanctum')->postJson('/api/orders', [
            'store_id' => $otherStore->id,
            'user_coupon_id' => $userCoupon->id,
            'items' => [['menu_id' => $otherMenu->id, 'quantity' => 1]],
        ])->assertUnprocessable();

        $this->assertDatabaseHas('user_coupons', ['id' => $userCoupon->id, 'status' => 'AVAILABLE']);
    }

    public function test_coupon_cannot_be_used_for_two_orders(): void
    {
        [$user, $store, $menu, $userCoupon] = $this->couponFixture();
        $payload = [
            'store_id' => $store->id,
            'user_coupon_id' => $userCoupon->id,
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ];

        $this->actingAs($user, 'sanctum')->postJson('/api/orders', $payload)->assertCreated();
        $this->postJson('/api/orders', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_full_payment_refund_restores_points_and_coupon(): void
    {
        [$user, $store, $menu, $userCoupon] = $this->couponFixture();
        $account = CustomerStoreAccount::create([
            'user_id' => $user->id, 'store_id' => $store->id, 'point_balance' => 2000,
        ]);
        $orderId = $this->actingAs($user, 'sanctum')->postJson('/api/orders', [
            'store_id' => $store->id,
            'user_coupon_id' => $userCoupon->id,
            'point_used' => 1000,
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertCreated()->json('order.id');
        $order = Order::with('payment')->findOrFail($orderId);
        $order->update(['status' => 'PAID', 'paid_at' => now()]);
        $order->payment->update(['status' => 'DONE', 'payment_key' => 'benefit-refund-key', 'approved_at' => now()]);
        config(['services.toss_payments.secret_key' => 'test_sk_demo']);
        Http::fake(['*/v1/payments/benefit-refund-key/cancel' => Http::response([
            'paymentKey' => 'benefit-refund-key',
            'status' => 'CANCELED',
            'cancels' => [['cancelAmount' => 8000]],
        ])]);

        $this->postJson("/api/payments/orders/{$orderId}/refund", ['reason' => '고객 요청'])->assertOk();

        $this->assertDatabaseHas('customer_store_accounts', ['id' => $account->id, 'point_balance' => 2000]);
        $this->assertDatabaseHas('user_coupons', [
            'id' => $userCoupon->id, 'status' => 'AVAILABLE', 'used_order_id' => null,
        ]);
        $this->assertDatabaseHas('point_transactions', [
            'customer_store_account_id' => $account->id,
            'type' => 'CANCEL',
            'amount' => 1000,
            'reason' => 'PAYMENT_REFUND',
            'reference_id' => $orderId,
        ]);
    }

    private function couponFixture($expiresAt = null): array
    {
        $user = User::factory()->create();
        $store = Store::create(['name' => 'Coupon Cafe', 'slug' => 'coupon-'.uniqid(), 'address' => 'Seoul', 'is_active' => true]);
        $menu = Menu::create(['store_id' => $store->id, 'name' => 'Latte', 'price' => 10000, 'is_available' => true]);
        $coupon = Coupon::create([
            'store_id' => $store->id,
            'created_by' => $user->id,
            'name' => '1000원 할인',
            'discount_type' => 'FIXED',
            'discount_value' => 1000,
            'minimum_order_amount' => 5000,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDay(),
            'is_active' => true,
        ]);
        $userCoupon = UserCoupon::create([
            'coupon_id' => $coupon->id,
            'user_id' => $user->id,
            'coupon_code' => 'COUPON-'.uniqid(),
            'status' => 'AVAILABLE',
            'issued_at' => now()->subDay(),
            'expires_at' => $expiresAt ?? now()->addDay(),
        ]);

        return [$user, $store, $menu, $userCoupon];
    }
}
