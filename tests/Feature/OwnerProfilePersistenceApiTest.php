<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerProfilePersistenceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_profile_and_store_are_restored_after_logout_and_login(): void
    {
        $signup = $this->postJson('/api/auth/owner/signup', [
            'name' => '저장 전 사장님',
            'email' => 'persistent-owner@cafeon.test',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
            'phone' => '010-1111-2222',
            'store_name' => '저장 전 카페',
            'store_address' => '서울특별시',
            'terms_accepted' => true,
        ])->assertCreated();

        $token = $signup->json('token');
        $storeId = $signup->json('store.id');
        $headers = ['Authorization' => 'Bearer '.$token];

        $this->withHeaders($headers)->patchJson('/api/owner/profile', [
            'name' => '저장된 사장님',
            'phone' => '010-9999-8888',
            'profile_image_url' => 'https://cdn.example.com/owners/profile.jpg',
            'birth_date' => '1988-05-12',
        ])->assertOk()
            ->assertJsonPath('user.name', '저장된 사장님')
            ->assertJsonPath('store.id', $storeId);

        $this->withHeaders($headers)->patchJson("/api/owner/stores/{$storeId}", [
            'name' => '로그인 후 복원 카페',
            'description' => '서버에 저장된 매장 설명',
            'phone' => '02-1234-5678',
            'business_info' => [
                'business_registration_number' => '123-45-67890',
                'representative_name' => '저장된 사장님',
            ],
            'business_hours' => [
                ['day_of_week' => 1, 'opening_time' => '09:00', 'closing_time' => '22:00', 'is_closed' => false],
            ],
            'tags' => [
                ['name' => '와이파이', 'slug' => 'wifi'],
            ],
        ])->assertOk();

        $this->withHeaders($headers)->patchJson('/api/owner/store', [
            'description' => '매장 ID 없이도 서버에 저장된 설명',
        ])->assertOk()
            ->assertJsonPath('store.id', $storeId)
            ->assertJsonPath('store.description', '매장 ID 없이도 서버에 저장된 설명');

        $this->withHeaders($headers)->putJson('/api/users/me/preferences', [
            'order_notifications' => false,
            'marketing_notifications' => true,
            'preferred_tags' => ['wifi', 'quiet'],
        ])->assertOk();

        $this->withHeaders($headers)->postJson('/api/logout')->assertOk();

        $login = $this->postJson('/api/auth/owner/login', [
            'email' => 'persistent-owner@cafeon.test',
            'password' => 'password1234',
        ])->assertOk()
            ->assertJsonPath('user.name', '저장된 사장님')
            ->assertJsonPath('user.phone', '010-9999-8888')
            ->assertJsonPath('user.profile_image_url', 'https://cdn.example.com/owners/profile.jpg')
            ->assertJsonPath('user.birth_date', '1988-05-12')
            ->assertJsonPath('store_id', $storeId)
            ->assertJsonPath('store.id', $storeId)
            ->assertJsonPath('store.name', '로그인 후 복원 카페')
            ->assertJsonPath('store.description', '매장 ID 없이도 서버에 저장된 설명')
            ->assertJsonPath('store.business_info.business_registration_number', '123-45-67890')
            ->assertJsonPath('store.business_hours.0.opening_time', '09:00')
            ->assertJsonPath('store.business_hours_text', '월 09:00-22:00')
            ->assertJsonPath('store.tags.0.slug', 'wifi')
            ->assertJsonPath('membership.role', 'OWNER')
            ->assertJsonPath('preferences.order_notifications', false)
            ->assertJsonPath('preferences.marketing_notifications', true)
            ->assertJsonPath('preferences.preferred_tags.0', 'wifi')
            ->assertJsonCount(1, 'stores')
            ->assertJsonCount(1, 'memberships');

        $newHeaders = ['Authorization' => 'Bearer '.$login->json('token')];
        $this->withHeaders($newHeaders)->getJson('/api/users/me')
            ->assertOk()
            ->assertJsonPath('user.name', '저장된 사장님')
            ->assertJsonPath('store.id', $storeId);

        $this->withHeaders($newHeaders)->getJson('/api/owner/profile')
            ->assertOk()
            ->assertJsonPath('store.name', '로그인 후 복원 카페');

        $this->withHeaders($newHeaders)->getJson('/api/owner/stores')
            ->assertOk()
            ->assertJsonPath('store_id', $storeId)
            ->assertJsonPath('data.0.id', $storeId);

        $this->withHeaders($newHeaders)->getJson('/api/owner/store')
            ->assertOk()
            ->assertJsonPath('store.id', $storeId)
            ->assertJsonPath('store.description', '매장 ID 없이도 서버에 저장된 설명');
    }

    public function test_customer_cannot_use_owner_profile_endpoint(): void
    {
        $customer = User::factory()->create(['role' => 'CUSTOMER', 'is_active' => true]);

        $this->actingAs($customer, 'sanctum')->getJson('/api/owner/profile')->assertForbidden();
        $this->actingAs($customer, 'sanctum')->patchJson('/api/owner/profile', ['name' => '권한 없음'])->assertForbidden();
    }

    public function test_mobile_store_profile_text_fields_and_tag_names_are_saved(): void
    {
        $signup = $this->postJson('/api/auth/owner/signup', [
            'name' => '모바일 사장님',
            'email' => 'mobile-profile@cafeon.test',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
            'phone' => '010-1111-2222',
            'store_name' => '저장 전 매장',
            'terms_accepted' => true,
        ])->assertCreated();
        $headers = ['Authorization' => 'Bearer '.$signup->json('token')];

        $this->withHeaders($headers)->patchJson('/api/owner/store', [
            'name' => 'asdf',
            'description' => 'asdf',
            'address' => 'asdf',
            'phone' => 'asdf',
            'business_hours' => '12-12',
            'business_info' => '12',
            'tags' => ['커피', '티(차)', '스터디카페'],
        ])->assertOk()
            ->assertJsonPath('store.business_hours_text', '12-12')
            ->assertJsonPath('store.hours', '12-12')
            ->assertJsonPath('store.business_info.business_registration_number', '12')
            ->assertJsonPath('store.business_info_text', '12')
            ->assertJsonCount(3, 'store.tags')
            ->assertJsonFragment(['name' => '티(차)', 'slug' => 'tea']);

        $this->withHeaders($headers)->postJson('/api/logout')->assertOk();
        $login = $this->postJson('/api/auth/owner/login', [
            'email' => 'mobile-profile@cafeon.test',
            'password' => 'password1234',
        ])->assertOk()
            ->assertJsonPath('store.name', 'asdf')
            ->assertJsonPath('store.business_hours_text', '12-12')
            ->assertJsonPath('store.business_info_text', '12')
            ->assertJsonFragment(['name' => '스터디카페', 'slug' => 'study-cafe']);

        $this->withHeaders(['Authorization' => 'Bearer '.$login->json('token')])
            ->getJson('/api/owner/store')
            ->assertOk()
            ->assertJsonPath('store.hours', '12-12')
            ->assertJsonCount(3, 'store.tags');
    }

    public function test_mobile_store_profile_accepts_empty_business_hours(): void
    {
        $signup = $this->postJson('/api/auth/owner/signup', [
            'name' => '빈 영업시간 사장님',
            'email' => 'empty-hours-owner@cafeon.test',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
            'phone' => '010-1111-2222',
            'store_name' => '빈 영업시간 매장',
            'terms_accepted' => true,
        ])->assertCreated();

        $this->withToken($signup->json('token'))->patchJson('/api/owner/store', [
            'name' => '저장된 매장명',
            'business_hours' => null,
            'tags' => ['커피'],
        ])->assertOk()
            ->assertJsonPath('store.name', '저장된 매장명')
            ->assertJsonPath('store.business_hours_text', null)
            ->assertJsonCount(1, 'store.tags');
    }

    public function test_numeric_daily_hours_are_saved_to_text_and_all_week_rows(): void
    {
        $signup = $this->postJson('/api/auth/owner/signup', [
            'name' => '영업시간 사장님',
            'email' => 'hours-owner@cafeon.test',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
            'phone' => '010-1111-2222',
            'store_name' => '영업시간 매장',
            'terms_accepted' => true,
        ])->assertCreated();

        $this->withToken($signup->json('token'))->patchJson('/api/owner/store', [
            'business_hours' => '08-22',
        ])->assertOk()
            ->assertJsonPath('store.business_hours_text', '08-22')
            ->assertJsonPath('store.hours', '08-22')
            ->assertJsonCount(7, 'store.business_hours')
            ->assertJsonPath('store.business_hours.0.opening_time', '08:00')
            ->assertJsonPath('store.business_hours.0.closing_time', '22:00');
    }

    public function test_owner_can_set_manual_map_location_for_store_without_map_cafe(): void
    {
        $signup = $this->postJson('/api/auth/owner/signup', [
            'name' => '지도 위치 사장님',
            'email' => 'map-location-owner@cafeon.test',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
            'phone' => '010-1111-2222',
            'store_name' => '지도 위치 매장',
            'terms_accepted' => true,
        ])->assertCreated();

        $this->withToken($signup->json('token'))->patchJson('/api/owner/store/location', [
            'latitude' => 35.8714,
            'longitude' => 128.6014,
            'address' => '대구광역시 중구',
            'detail_address' => '수동 지정 위치',
        ])->assertOk()
            ->assertJsonPath('store.latitude', '35.8714000')
            ->assertJsonPath('store.longitude', '128.6014000');

        $this->assertDatabaseHas('stores', [
            'id' => $signup->json('store.id'),
            'latitude' => 35.8714,
            'longitude' => 128.6014,
        ]);
    }
}
