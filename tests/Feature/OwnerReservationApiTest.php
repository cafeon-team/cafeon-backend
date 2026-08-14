<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Models\Store;
use App\Models\StoreMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OwnerReservationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_owner_can_list_store_reservations(): void
    {
        [$owner, $store, $reservation] = $this->fixture();
        Sanctum::actingAs($owner);

        $this->getJson("/api/stores/{$store->id}/reservations")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reservation->id);
    }

    public function test_unrelated_customer_cannot_manage_store_reservations(): void
    {
        [, $store, $reservation] = $this->fixture();
        Sanctum::actingAs(User::factory()->create(['role' => 'CUSTOMER']));

        $this->getJson("/api/stores/{$store->id}/reservations")->assertForbidden();
        $this->patchJson("/api/reservations/{$reservation->id}/status", ['status' => 'CONFIRMED'])->assertForbidden();
    }

    public function test_owner_can_approve_pending_reservation(): void
    {
        [$owner, , $reservation] = $this->fixture();
        Sanctum::actingAs($owner);

        $this->patchJson("/api/reservations/{$reservation->id}/status", ['status' => 'CONFIRMED'])
            ->assertOk()
            ->assertJsonPath('reservation.status', 'CONFIRMED')
            ->assertJsonPath('reservation.approved_by', $owner->id);
    }

    public function test_rejection_requires_reason(): void
    {
        [$owner, , $reservation] = $this->fixture();
        Sanctum::actingAs($owner);

        $this->patchJson("/api/reservations/{$reservation->id}/status", ['status' => 'REJECTED'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');
    }

    public function test_completing_reservation_creates_verified_customer_visit(): void
    {
        [$owner, $store, $reservation] = $this->fixture();
        Sanctum::actingAs($owner);

        $this->patchJson("/api/reservations/{$reservation->id}/status", ['status' => 'CONFIRMED'])->assertOk();
        $this->patchJson("/api/reservations/{$reservation->id}/status", ['status' => 'COMPLETED'])->assertOk();

        $this->assertDatabaseHas('customer_visits', [
            'reservation_id' => $reservation->id,
            'user_id' => $reservation->user_id,
            'store_id' => $store->id,
            'type' => 'RESERVATION',
            'confirmed_by' => $owner->id,
        ]);
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        [$owner, , $reservation] = $this->fixture('COMPLETED');
        Sanctum::actingAs($owner);

        $this->patchJson("/api/reservations/{$reservation->id}/status", ['status' => 'CONFIRMED'])
            ->assertUnprocessable();
    }

    private function fixture(string $status = 'PENDING_APPROVAL'): array
    {
        $owner = User::factory()->create(['role' => 'CUSTOMER']);
        $customer = User::factory()->create(['role' => 'CUSTOMER']);
        $store = Store::create(['name' => '사장 예약 테스트', 'slug' => 'owner-reservation-'.uniqid(), 'address' => '서울']);
        StoreMember::create(['store_id' => $store->id, 'user_id' => $owner->id, 'role' => 'OWNER', 'is_active' => true]);
        $slot = ReservationSlot::create(['store_id' => $store->id, 'slot_date' => today()->addDay(), 'start_time' => '14:00', 'end_time' => '15:00']);
        $reservation = Reservation::create([
            'user_id' => $customer->id, 'store_id' => $store->id, 'reservation_slot_id' => $slot->id,
            'reservation_number' => 'OWNER-'.uniqid(), 'guest_count' => 2,
            'customer_name' => $customer->name, 'customer_phone' => '010-0000-0000', 'status' => $status,
        ]);

        return [$owner, $store, $reservation];
    }
}
