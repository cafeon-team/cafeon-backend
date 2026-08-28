<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Store;
use App\Models\StoreMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OwnerMenuApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_manage_category_and_menu(): void
    {
        [$owner, $store] = $this->fixture();
        Sanctum::actingAs($owner);

        $categoryId = $this->postJson("/api/owner/stores/{$store->id}/menu-categories", [
            'name' => '커피', 'sort_order' => 1,
        ])->assertCreated()->json('category.id');

        $menuId = $this->postJson("/api/owner/stores/{$store->id}/menus", [
            'category_id' => $categoryId,
            'name' => '카페라떼',
            'description' => '고소한 라떼',
            'price' => 5000,
            'image_url' => 'https://example.com/latte.jpg',
        ])->assertCreated()
            ->assertJsonPath('menu.name', '카페라떼')
            ->assertJsonPath('menu.category.name', '커피')
            ->json('menu.id');

        $this->putJson("/api/owner/menus/{$menuId}", ['name' => '바닐라라떼', 'price' => 5500])
            ->assertOk()->assertJsonPath('menu.name', '바닐라라떼')
            ->assertJsonPath('menu.price', '5500.00');
        $this->getJson("/api/owner/stores/{$store->id}/menus")
            ->assertOk()->assertJsonPath('menus.data.0.id', $menuId);
        $this->deleteJson("/api/owner/menus/{$menuId}")->assertNoContent();
        $this->assertSoftDeleted('menus', ['id' => $menuId]);
    }

    public function test_owner_can_mark_menu_sold_out_and_public_api_still_shows_it(): void
    {
        [$owner, $store] = $this->fixture();
        $menu = Menu::create(['store_id' => $store->id, 'name' => '아메리카노', 'price' => 4500, 'is_available' => true]);
        Sanctum::actingAs($owner);

        $this->patchJson("/api/owner/menus/{$menu->id}/availability", ['is_available' => false])
            ->assertOk()->assertJsonPath('menu.is_available', false);
        $this->getJson("/api/stores/{$store->id}/menus")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $menu->id)
            ->assertJsonPath('data.0.is_available', false);
        $this->getJson("/api/owner/stores/{$store->id}/menus?is_available=0")
            ->assertOk()->assertJsonPath('menus.data.0.id', $menu->id);
    }

    public function test_owner_can_set_numeric_stock_and_zero_stock_cannot_be_reactivated(): void
    {
        [$owner, $store] = $this->fixture();
        Sanctum::actingAs($owner);

        $menuId = $this->postJson("/api/owner/stores/{$store->id}/menus", [
            'name' => '한정 메뉴',
            'price' => 7000,
            'stockQuantity' => 1,
        ])->assertCreated()
            ->assertJsonPath('menu.stock_quantity', 1)
            ->assertJsonPath('menu.is_available', true)
            ->json('menu.id');

        $this->putJson("/api/owner/menus/{$menuId}", ['stock_quantity' => 0])
            ->assertOk()
            ->assertJsonPath('menu.stock_quantity', 0)
            ->assertJsonPath('menu.is_available', false);

        $this->patchJson("/api/owner/menus/{$menuId}/availability", ['is_available' => true])
            ->assertUnprocessable();
    }

    public function test_menu_rejects_category_from_another_store(): void
    {
        [$owner, $store] = $this->fixture();
        $otherStore = Store::create(['name' => '다른 매장', 'slug' => 'other-'.uniqid(), 'address' => '서울']);
        $category = MenuCategory::create(['store_id' => $otherStore->id, 'name' => '다른 카테고리']);
        Sanctum::actingAs($owner);

        $this->postJson("/api/owner/stores/{$store->id}/menus", [
            'category_id' => $category->id, 'name' => '잘못된 메뉴', 'price' => 5000,
        ])->assertUnprocessable();
        $this->assertDatabaseMissing('menus', ['name' => '잘못된 메뉴']);
    }

    public function test_owner_cannot_create_or_update_a_zero_price_menu(): void
    {
        [$owner, $store] = $this->fixture();
        Sanctum::actingAs($owner);

        $this->postJson("/api/owner/stores/{$store->id}/menus", [
            'name' => '가격 미입력 메뉴',
            'price' => 0,
        ])->assertUnprocessable()->assertJsonValidationErrors('price');

        $menu = Menu::create(['store_id' => $store->id, 'name' => '기존 메뉴', 'price' => 4000]);
        $this->putJson("/api/owner/menus/{$menu->id}", ['price' => 0])
            ->assertUnprocessable()->assertJsonValidationErrors('price');
    }

    public function test_mobile_owner_can_create_menu_with_data_url_image(): void
    {
        Storage::fake('public');
        [$owner] = $this->fixture();
        Sanctum::actingAs($owner);

        $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

        $imageUrl = $this->postJson('/api/owner/menus', [
            'name' => '이미지 메뉴',
            'price' => 4500,
            'category' => '커피',
            'image_url' => 'data:image/png;base64,'.$png,
        ])->assertCreated()->json('menu.image_url');

        $path = ltrim((string) parse_url($imageUrl, PHP_URL_PATH), '/');
        Storage::disk('public')->assertExists(Str::after($path, 'storage/'));
    }

    public function test_unrelated_user_cannot_manage_menu(): void
    {
        [, $store] = $this->fixture();
        $menu = Menu::create(['store_id' => $store->id, 'name' => '보호 메뉴', 'price' => 5000]);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/owner/stores/{$store->id}/menus")->assertForbidden();
        $this->putJson("/api/owner/menus/{$menu->id}", ['price' => 1])->assertForbidden();
        $this->deleteJson("/api/owner/menus/{$menu->id}")->assertForbidden();
    }

    public function test_manager_can_manage_menu_but_unrelated_admin_cannot(): void
    {
        [, $store] = $this->fixture();
        $manager = User::factory()->create();
        StoreMember::create(['store_id' => $store->id, 'user_id' => $manager->id, 'role' => 'MANAGER', 'is_active' => true]);
        Sanctum::actingAs($manager);
        $menuId = $this->postJson("/api/owner/stores/{$store->id}/menus", ['name' => '매니저 메뉴', 'price' => 4000])
            ->assertCreated()->json('menu.id');

        Sanctum::actingAs(User::factory()->create(['role' => 'ADMIN']));
        $this->putJson("/api/owner/menus/{$menuId}", ['price' => 4500])->assertForbidden();
    }

    private function fixture(): array
    {
        $owner = User::factory()->create(['role' => 'OWNER']);
        $store = Store::create(['name' => '메뉴 관리 매장', 'slug' => 'menu-owner-'.uniqid(), 'address' => '서울', 'is_active' => true]);
        StoreMember::create(['store_id' => $store->id, 'user_id' => $owner->id, 'role' => 'OWNER', 'is_active' => true]);

        return [$owner, $store];
    }
}
