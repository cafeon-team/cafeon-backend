<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\StoreMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NoshowPolicyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_read_default_noshow_policy(): void
    {
        $store = $this->store();

        $this->getJson("/api/stores/{$store->id}/noshow-policy")
            ->assertOk()
            ->assertJsonPath('policy.deposit_required', false)
            ->assertJsonPath('policy.free_cancellation_minutes', 60);
    }

    public function test_owner_can_save_noshow_policy(): void
    {
        $store = $this->store();
        $owner = User::factory()->create();
        StoreMember::create(['store_id' => $store->id, 'user_id' => $owner->id, 'role' => 'OWNER', 'is_active' => true]);
        Sanctum::actingAs($owner);

        $this->putJson("/api/stores/{$store->id}/noshow-policy", [
            'deposit_required' => true,
            'deposit_amount' => 5000,
            'free_cancellation_minutes' => 120,
            'penalty_type' => 'POINT',
            'penalty_point_amount' => 1000,
        ])->assertOk()
            ->assertJsonPath('policy.penalty_type', 'POINT')
            ->assertJsonPath('policy.free_cancellation_minutes', 120);

        $this->assertDatabaseHas('store_noshow_policies', ['store_id' => $store->id, 'deposit_amount' => 5000]);
    }

    public function test_customer_cannot_update_noshow_policy(): void
    {
        $store = $this->store();
        Sanctum::actingAs(User::factory()->create(['role' => 'CUSTOMER']));

        $this->putJson("/api/stores/{$store->id}/noshow-policy", [
            'deposit_required' => false,
            'deposit_amount' => 0,
            'free_cancellation_minutes' => 60,
            'penalty_type' => 'NONE',
        ])->assertForbidden();
    }

    private function store(): Store
    {
        return Store::create(['name' => '노쇼 정책 매장', 'slug' => 'noshow-'.uniqid(), 'address' => '서울', 'is_active' => true]);
    }
}
