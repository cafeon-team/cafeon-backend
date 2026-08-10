<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_available_menus_can_be_searched_filtered_and_sorted(): void
    {
        $store = Store::create(['name' => 'CafeON', 'slug' => 'cafeon', 'address' => 'Seoul', 'is_active' => true]);
        $coffee = MenuCategory::create(['store_id' => $store->id, 'name' => 'Coffee', 'is_active' => true]);
        $dessert = MenuCategory::create(['store_id' => $store->id, 'name' => 'Dessert', 'is_active' => true]);

        Menu::create(['store_id' => $store->id, 'category_id' => $coffee->id, 'name' => 'Vanilla Latte', 'description' => 'Sweet latte', 'price' => 5000, 'is_available' => true]);
        Menu::create(['store_id' => $store->id, 'category_id' => $coffee->id, 'name' => 'Cafe Latte', 'description' => 'Milk coffee', 'price' => 4500, 'is_available' => true]);
        Menu::create(['store_id' => $store->id, 'category_id' => $dessert->id, 'name' => 'Croissant', 'price' => 4000, 'is_available' => true]);
        Menu::create(['store_id' => $store->id, 'category_id' => $coffee->id, 'name' => 'Hidden Latte', 'price' => 3000, 'is_available' => false]);

        $this->getJson("/api/stores/{$store->id}/menus?keyword=Latte&category_id={$coffee->id}&sort=price_asc")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Cafe Latte')
            ->assertJsonPath('data.1.name', 'Vanilla Latte');
    }

    public function test_menu_detail_returns_store_and_category(): void
    {
        $store = Store::create(['name' => 'CafeON', 'slug' => 'cafeon', 'address' => 'Seoul', 'is_active' => true]);
        $category = MenuCategory::create(['store_id' => $store->id, 'name' => 'Coffee', 'is_active' => true]);
        $menu = Menu::create(['store_id' => $store->id, 'category_id' => $category->id, 'name' => 'Americano', 'price' => 4000, 'is_available' => true]);

        $this->getJson("/api/menus/{$menu->id}")
            ->assertOk()
            ->assertJsonPath('menu.name', 'Americano')
            ->assertJsonPath('menu.store.slug', 'cafeon')
            ->assertJsonPath('menu.category.name', 'Coffee');
    }
}
