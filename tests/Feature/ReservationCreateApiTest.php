<?php

namespace Tests\Feature;

use App\Models\ReservationSlot;
use App\Models\Store;
use App\Models\StoreSeat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReservationCreateApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_create_reservation(): void
    {
        $store = Store::create(['name' => '인증 매장', 'slug' => 'auth-store', 'address' => '서울']);
        $this->postJson('/api/reservations', [])->assertUnauthorized();
    }

    public function test_authenticated_customer_can_create_reservation(): void
    {
        [$store, $slot] = $this->reservationFixture(4);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/reservations', [
            'store_id' => $store->id,
            'reservation_slot_id' => $slot->id,
            'guest_count' => 2,
            'customer_name' => '홍길동',
            'customer_phone' => '010-1234-5678',
            'customer_request' => '창가 좌석 희망',
        ])->assertCreated()
            ->assertJsonPath('reservation.status', 'PENDING_APPROVAL')
            ->assertJsonPath('reservation.guest_count', 2);

        $this->assertDatabaseHas('reservations', [
            'user_id' => auth()->id(),
            'store_id' => $store->id,
            'reservation_slot_id' => $slot->id,
            'guest_count' => 2,
            'status' => 'PENDING_APPROVAL',
        ]);
    }

    public function test_reservation_is_rejected_when_guest_count_exceeds_capacity(): void
    {
        [$store, $slot] = $this->reservationFixture(2);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/reservations', [
            'store_id' => $store->id,
            'reservation_slot_id' => $slot->id,
            'guest_count' => 3,
            'customer_name' => '홍길동',
            'customer_phone' => '010-1234-5678',
        ])->assertUnprocessable()
            ->assertJsonPath('message', '남은 예약 가능 인원은 2명입니다.');
    }

    private function reservationFixture(int $capacity): array
    {
        $store = Store::create(['name' => '예약 생성 매장', 'slug' => 'create-store-'.$capacity, 'address' => '서울', 'is_active' => true, 'reservation_enabled' => true]);
        StoreSeat::create(['store_id' => $store->id, 'seat_code' => 'A1', 'seat_name' => 'A1', 'capacity' => $capacity, 'status' => 'AVAILABLE']);
        $slot = ReservationSlot::create(['store_id' => $store->id, 'slot_date' => today()->addDay(), 'start_time' => '14:00', 'end_time' => '15:00', 'is_active' => true]);

        return [$store, $slot];
    }
}
