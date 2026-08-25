<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\StoreMember;
use App\Models\StoreSeat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OwnerStoreProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_update_store_profile_and_business_status(): void
    {
        [$owner, $store] = $this->fixture();
        Sanctum::actingAs($owner);

        $this->patchJson("/api/owner/stores/{$store->id}", [
            'name' => '수정된 카페', 'description' => '새 설명', 'phone' => '02-123-4567',
        ])->assertOk()->assertJsonPath('store.name', '수정된 카페');

        $this->patchJson("/api/owner/stores/{$store->id}/business-status", ['is_open' => false])
            ->assertOk()->assertJsonPath('store.is_open', false);

        $this->patchJson("/api/owner/stores/{$store->id}/availability", ['is_active' => true])
            ->assertOk()->assertJsonPath('store.is_open', true);
    }

    public function test_owner_can_read_profile_and_sync_store_tags(): void
    {
        [$owner, $store] = $this->fixture();
        Sanctum::actingAs($owner);

        $this->patchJson("/api/owner/stores/{$store->id}", [
            'tags' => [
                ['name' => '와이파이', 'slug' => 'wifi'],
                ['name' => '주차', 'slug' => 'parking'],
            ],
        ])->assertOk()->assertJsonCount(2, 'store.tags');

        $this->getJson("/api/owner/stores/{$store->id}")
            ->assertOk()
            ->assertJsonFragment(['name' => '와이파이', 'slug' => 'wifi'])
            ->assertJsonFragment(['name' => '주차', 'slug' => 'parking']);
    }

    public function test_owner_cannot_manage_another_owners_store_even_with_admin_user_role(): void
    {
        [, $store] = $this->fixture();
        Sanctum::actingAs(User::factory()->create(['role' => 'ADMIN']));

        $this->getJson("/api/owner/stores/{$store->id}")->assertForbidden();
        $this->patchJson("/api/owner/stores/{$store->id}", ['name' => '탈취 시도'])->assertForbidden();
        $this->deleteJson("/api/owner/stores/{$store->id}")->assertForbidden();
    }

    public function test_owner_can_soft_delete_own_store_profile(): void
    {
        [$owner, $store] = $this->fixture();
        Sanctum::actingAs($owner);

        $this->deleteJson("/api/owner/stores/{$store->id}")->assertNoContent();

        $this->assertSoftDeleted('stores', ['id' => $store->id]);
        $this->assertDatabaseHas('store_members', [
            'store_id' => $store->id, 'user_id' => $owner->id, 'is_active' => false,
        ]);
    }

    public function test_owner_can_save_business_hours_and_business_information(): void
    {
        [$owner, $store] = $this->fixture();
        Sanctum::actingAs($owner);

        $this->patchJson("/api/owner/stores/{$store->id}", [
            'business_info' => [
                'business_registration_number' => '123-45-67890',
                'representative_name' => '홍길동',
                'company_name' => '카페온 주식회사',
                'business_type' => '음식점업',
                'business_item' => '커피 전문점',
                'business_address' => '서울특별시 중구',
            ],
            'business_hours' => [
                ['day_of_week' => 1, 'opening_time' => '09:00', 'closing_time' => '22:00', 'is_closed' => false],
                ['day_of_week' => 2, 'opening_time' => null, 'closing_time' => null, 'is_closed' => true],
            ],
        ])->assertOk()
            ->assertJsonPath('store.business_info.representative_name', '홍길동')
            ->assertJsonPath('store.business_hours.0.day_of_week', 1)
            ->assertJsonPath('store.business_hours.1.is_closed', true);

        $this->assertDatabaseHas('store_business_hours', [
            'store_id' => $store->id, 'day_of_week' => 1, 'opening_time' => '09:00', 'closing_time' => '22:00',
        ]);
    }

    public function test_open_business_day_requires_valid_time_range(): void
    {
        [$owner, $store] = $this->fixture();
        Sanctum::actingAs($owner);

        $this->patchJson("/api/owner/stores/{$store->id}", [
            'business_hours' => [
                ['day_of_week' => 1, 'opening_time' => '18:00', 'closing_time' => '09:00', 'is_closed' => false],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('business_hours.0.closing_time');
    }

    public function test_business_information_is_not_exposed_by_public_store_api(): void
    {
        [, $store] = $this->fixture();
        $store->update(['business_info' => ['business_registration_number' => '123-45-67890']]);

        $this->getJson("/api/stores/{$store->id}")
            ->assertOk()->assertJsonMissingPath('store.business_info');
    }

    public function test_owner_can_list_create_and_delete_seats(): void
    {
        [$owner, $store] = $this->fixture();
        Sanctum::actingAs($owner);
        StoreSeat::create(['store_id' => $store->id, 'seat_code' => 'A1', 'seat_name' => '창가', 'capacity' => 2]);

        $this->getJson("/api/owner/stores/{$store->id}/seats")
            ->assertOk()->assertJsonPath('data.0.seat_code', 'A1');

        $seatId = $this->postJson("/api/owner/stores/{$store->id}/seats", [
            'seat_code' => 'B1', 'seat_name' => '단체석', 'seat_type' => 'GROUP', 'capacity' => 4,
        ])->assertCreated()->json('seat.id');

        $this->deleteJson("/api/owner/stores/{$store->id}/seats/{$seatId}")->assertNoContent();
    }

    public function test_unrelated_customer_cannot_manage_store(): void
    {
        [, $store] = $this->fixture();
        Sanctum::actingAs(User::factory()->create(['role' => 'CUSTOMER']));

        $this->patchJson("/api/owner/stores/{$store->id}", ['name' => '권한 없음'])->assertForbidden();
        $this->getJson("/api/owner/stores/{$store->id}/seats")->assertForbidden();
    }

    private function fixture(): array
    {
        $owner = User::factory()->create(['role' => 'CUSTOMER']);
        $store = Store::create(['name' => '프로필 테스트', 'slug' => 'profile-'.uniqid(), 'address' => '서울']);
        StoreMember::create(['store_id' => $store->id, 'user_id' => $owner->id, 'role' => 'OWNER', 'is_active' => true]);

        return [$owner, $store];
    }
}
