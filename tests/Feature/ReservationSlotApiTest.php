<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Models\Store;
use App\Models\StoreSeat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationSlotApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_slots_calculate_remaining_capacity_for_requested_guests(): void
    {
        $store = Store::create(['name' => '예약 매장', 'slug' => 'reservation-store', 'address' => '서울', 'is_active' => true, 'reservation_enabled' => true]);
        StoreSeat::create(['store_id' => $store->id, 'seat_code' => 'A1', 'seat_name' => 'A1', 'capacity' => 4, 'status' => 'AVAILABLE']);
        $slot = ReservationSlot::create(['store_id' => $store->id, 'slot_date' => today()->addDay(), 'start_time' => '14:00', 'end_time' => '15:00', 'is_active' => true]);
        $user = User::factory()->create();
        Reservation::create([
            'user_id' => $user->id, 'store_id' => $store->id, 'reservation_slot_id' => $slot->id,
            'reservation_number' => 'TEST-001', 'guest_count' => 2, 'customer_name' => '테스트',
            'customer_phone' => '010-0000-0000', 'status' => 'CONFIRMED',
        ]);

        $date = today()->addDay()->format('Y-m-d');
        $this->getJson("/api/stores/{$store->id}/reservation-slots?date={$date}&guest_count=2")
            ->assertOk()
            ->assertJsonPath('slots.0.capacity', 4)
            ->assertJsonPath('slots.0.reserved_count', 2)
            ->assertJsonPath('slots.0.remaining_count', 2)
            ->assertJsonPath('slots.0.available', true);
    }

    public function test_slot_is_unavailable_when_requested_guests_exceed_remaining_capacity(): void
    {
        $store = Store::create(['name' => '인원 제한 매장', 'slug' => 'capacity-store', 'address' => '서울', 'is_active' => true, 'reservation_enabled' => true]);
        StoreSeat::create(['store_id' => $store->id, 'seat_code' => 'A1', 'seat_name' => 'A1', 'capacity' => 2, 'status' => 'AVAILABLE']);
        ReservationSlot::create(['store_id' => $store->id, 'slot_date' => today()->addDay(), 'start_time' => '14:00', 'end_time' => '15:00', 'is_active' => true]);

        $date = today()->addDay()->format('Y-m-d');
        $this->getJson("/api/stores/{$store->id}/reservation-slots?date={$date}&guest_count=3")
            ->assertOk()
            ->assertJsonPath('slots.0.available', false)
            ->assertJsonPath('slots.0.unavailable_reason', '요청 인원을 수용할 수 없습니다.');
    }
}
