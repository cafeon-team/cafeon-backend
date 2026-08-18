<?php

namespace Tests\Feature;

use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerRegistrationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_register_with_store_and_receive_token(): void
    {
        $response = $this->postJson('/api/auth/owner/signup', [
            'name' => '김사장',
            'email' => 'owner@cafeon.test',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
            'phone' => '010-1234-5678',
            'store_name' => '카페온 강남점',
            'terms_accepted' => true,
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['token', 'token_type', 'user', 'store', 'membership'])
            ->assertJsonPath('user.role', 'ADMIN')
            ->assertJsonPath('store.name', '카페온 강남점')
            ->assertJsonPath('store.address', null)
            ->assertJsonPath('membership.role', 'OWNER');

        $userId = $response->json('user.id');
        $storeId = $response->json('store.id');
        $this->assertDatabaseHas('users', ['id' => $userId, 'email' => 'owner@cafeon.test', 'role' => 'ADMIN']);
        $this->assertDatabaseHas('stores', ['id' => $storeId, 'name' => '카페온 강남점']);
        $this->assertDatabaseHas('store_members', [
            'store_id' => $storeId, 'user_id' => $userId, 'role' => 'OWNER', 'is_active' => true,
        ]);
    }

    public function test_owner_registration_requires_matching_password_and_terms(): void
    {
        $this->postJson('/api/auth/owner/signup', [
            'name' => '김사장',
            'email' => 'invalid@cafeon.test',
            'password' => 'password1234',
            'password_confirmation' => 'different1234',
            'phone' => '010-1234-5678',
            'store_name' => '검증 매장',
            'terms_accepted' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors(['password', 'terms_accepted']);

        $this->assertDatabaseMissing('users', ['email' => 'invalid@cafeon.test']);
        $this->assertDatabaseMissing('stores', ['name' => '검증 매장']);
    }

    public function test_duplicate_store_names_receive_unique_slugs(): void
    {
        foreach (['first@cafeon.test', 'second@cafeon.test'] as $email) {
            $this->postJson('/api/auth/owner/signup', [
                'name' => '김사장',
                'email' => $email,
                'password' => 'password1234',
                'password_confirmation' => 'password1234',
                'phone' => '010-1234-5678',
                'store_name' => '같은 매장',
                'terms_accepted' => true,
            ])->assertCreated();
        }

        $this->assertSame(2, Store::query()->distinct()->count('slug'));
    }
}
