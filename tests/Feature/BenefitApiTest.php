<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\CustomerStoreAccount;
use App\Models\Menu;
use App\Models\Store;
use App\Models\User;
use App\Models\UserCoupon;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
