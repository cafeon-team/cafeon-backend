<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\StoreMember;
use App\Models\StoreSeat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OwnerSeatApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_update_one_seat_and_receive_availability(): void
    {
        [$owner, $store, $seats] = $this->fixture();
        Sanctum::actingAs($owner);

        $this->patchJson("/api/owner/stores/{$store->id}/seats/{$seats[0]->id}", ['status' => 'UNAVAILABLE'])
            ->assertOk()
            ->assertJsonPath('seat.status', 'UNAVAILABLE')
            ->assertJsonPath('availability.total_capacity', 6)
            ->assertJsonPath('availability.available_capacity', 4)
            ->assertJsonPath('availability.congestion_label', '여유')
            ->assertJsonPath('availability.availability_updated_at', fn ($value) => is_string($value));

        $this->assertNotNull($store->fresh()->availability_updated_at);
    }

    public function test_owner_can_update_multiple_seats_at_once(): void
    {
        [$owner, $store, $seats] = $this->fixture();
        Sanctum::actingAs($owner);

        $this->patchJson("/api/owner/stores/{$store->id}/availability", [
            'seats' => [
                ['id' => $seats[0]->id, 'status' => 'UNAVAILABLE'],
                ['id' => $seats[1]->id, 'status' => 'MAINTENANCE'],
            ],
        ])->assertOk()
            ->assertJsonPath('availability.occupied_capacity', 2)
            ->assertJsonPath('availability.maintenance_capacity', 4)
            ->assertJsonPath('availability.available_capacity', 0);
    }

    public function test_unrelated_user_cannot_update_seats(): void
    {
        [, $store, $seats] = $this->fixture();
        Sanctum::actingAs(User::factory()->create(['role' => 'CUSTOMER']));

        $this->patchJson("/api/owner/stores/{$store->id}/seats/{$seats[0]->id}", ['status' => 'UNAVAILABLE'])
            ->assertForbidden();
    }

    public function test_seat_from_another_store_is_rejected(): void
    {
        [$owner, $store] = $this->fixture();
        $otherStore = Store::create(['name' => '다른 매장', 'slug' => 'other-'.uniqid(), 'address' => '서울']);
        $otherSeat = StoreSeat::create(['store_id' => $otherStore->id, 'seat_code' => 'X1', 'seat_name' => 'X1', 'capacity' => 1]);
        Sanctum::actingAs($owner);

        $this->patchJson("/api/owner/stores/{$store->id}/seats/{$otherSeat->id}", ['status' => 'UNAVAILABLE'])
            ->assertNotFound();
    }

    private function fixture(): array
    {
        $owner = User::factory()->create(['role' => 'CUSTOMER']);
        $store = Store::create(['name' => '좌석 관리 매장', 'slug' => 'seat-owner-'.uniqid(), 'address' => '서울']);
        StoreMember::create(['store_id' => $store->id, 'user_id' => $owner->id, 'role' => 'OWNER', 'is_active' => true]);
        $seats = collect([
            StoreSeat::create(['store_id' => $store->id, 'seat_code' => 'A1', 'seat_name' => 'A1', 'capacity' => 2]),
            StoreSeat::create(['store_id' => $store->id, 'seat_code' => 'A2', 'seat_name' => 'A2', 'capacity' => 4]),
        ]);

        return [$owner, $store, $seats];
    }
}
