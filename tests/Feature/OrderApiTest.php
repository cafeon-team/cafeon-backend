<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_create_view_and_cancel_an_order(): void
    {
        $user = User::factory()->create();
        $store = Store::create(['name' => 'CafeON', 'slug' => 'cafeon', 'address' => 'Seoul', 'is_active' => true]);
        $menu = Menu::create(['store_id' => $store->id, 'name' => 'Latte', 'price' => 5000, 'is_available' => true]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/orders', [
            'store_id' => $store->id,
            'items' => [['menu_id' => $menu->id, 'quantity' => 2]],
        ])->assertCreated()
            ->assertJsonPath('order.total_amount', '10000.00')
            ->assertJsonPath('order.menu_amount', '10000.00')
            ->assertJsonPath('order.final_amount', '10000.00')
            ->assertJsonPath('order.items.0.menu_name', 'Latte');

        $orderId = $response->json('order.id');
        $this->assertDatabaseHas((new Order)->getTable(), [
            'id' => $orderId,
            'total_amount' => 10000,
        ]);
        $this->getJson('/api/users/me/orders')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson("/api/users/me/orders/{$orderId}/cancel")
            ->assertOk()
            ->assertJsonPath('order.status', 'CANCELLED');
    }

    public function test_order_rejects_menu_from_another_store(): void
    {
        $user = User::factory()->create();
        $store = Store::create(['name' => 'One', 'slug' => 'one', 'address' => 'Seoul', 'is_active' => true]);
        $other = Store::create(['name' => 'Two', 'slug' => 'two', 'address' => 'Seoul', 'is_active' => true]);
        $menu = Menu::create(['store_id' => $other->id, 'name' => 'Latte', 'price' => 5000, 'is_available' => true]);

        $this->actingAs($user, 'sanctum')->postJson('/api/orders', [
            'store_id' => $store->id,
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertUnprocessable();

        $this->assertDatabaseCount((new Order)->getTable(), 0);
    }
}
