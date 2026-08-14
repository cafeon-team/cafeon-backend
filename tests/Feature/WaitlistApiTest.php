<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\StoreMember;
use App\Models\User;
use App\Models\Waitlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WaitlistApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_join_waitlist_and_queue_numbers_increase(): void
    {
        $store = $this->store();
        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/stores/{$store->id}/waitlists", ['guest_count' => 2])
            ->assertCreated()->assertJsonPath('waitlist.queue_number', 1)
            ->assertJsonPath('waitlist.estimated_wait_minutes', 0);

        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/stores/{$store->id}/waitlists", ['guest_count' => 4])
            ->assertCreated()->assertJsonPath('waitlist.queue_number', 2)
            ->assertJsonPath('waitlist.estimated_wait_minutes', 10);
    }

    public function test_customer_cannot_create_duplicate_active_waitlist(): void
    {
        $store = $this->store();
        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/stores/{$store->id}/waitlists", ['guest_count' => 2])->assertCreated();
        $this->postJson("/api/stores/{$store->id}/waitlists", ['guest_count' => 2])->assertUnprocessable();
    }

    public function test_customer_can_list_and_cancel_own_waitlist(): void
    {
        $store = $this->store();
        Sanctum::actingAs(User::factory()->create());
        $id = $this->postJson("/api/stores/{$store->id}/waitlists", ['guest_count' => 2])->json('waitlist.id');
        $this->getJson('/api/users/me/waitlists')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->deleteJson("/api/users/me/waitlists/{$id}")->assertOk()->assertJsonPath('waitlist.status', 'CANCELLED');
    }

    public function test_owner_can_list_call_and_seat_waiting_customer(): void
    {
        $store = $this->store();
        $owner = User::factory()->create();
        $customer = User::factory()->create();
        StoreMember::create(['store_id' => $store->id, 'user_id' => $owner->id, 'role' => 'OWNER', 'is_active' => true]);
        $waitlist = Waitlist::create([
            'store_id' => $store->id, 'user_id' => $customer->id, 'queued_on' => today(),
            'queue_number' => 1, 'guest_count' => 2, 'status' => 'WAITING',
        ]);
        Sanctum::actingAs($owner);
        $this->getJson("/api/owner/stores/{$store->id}/waitlists")->assertOk()->assertJsonPath('data.0.id', $waitlist->id);
        $this->patchJson("/api/owner/waitlists/{$waitlist->id}/status", ['status' => 'CALLED'])
            ->assertOk()->assertJsonPath('waitlist.status', 'CALLED');
        $this->patchJson("/api/owner/waitlists/{$waitlist->id}/status", ['status' => 'SEATED'])
            ->assertOk()->assertJsonPath('waitlist.status', 'SEATED');
    }

    public function test_unrelated_user_cannot_manage_store_waitlist(): void
    {
        $store = $this->store();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/owner/stores/{$store->id}/waitlists")->assertForbidden();
    }

    private function store(): Store
    {
        return Store::create([
            'name' => '대기 테스트 매장', 'slug' => 'waitlist-'.uniqid(),
            'address' => '서울', 'is_active' => true,
        ]);
    }
}
