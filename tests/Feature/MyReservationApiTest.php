<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MyReservationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_only_list_own_reservations(): void
    {
        [$user, $reservation] = $this->reservationFixture('PENDING_APPROVAL');
        [$otherUser] = $this->reservationFixture('CONFIRMED', 'OTHER-001');
        Sanctum::actingAs($user);

        $this->getJson('/api/users/me/reservations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reservation->id);
    }

    public function test_customer_cannot_read_another_customers_reservation(): void
    {
        [, $reservation] = $this->reservationFixture('PENDING_APPROVAL');
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/users/me/reservations/{$reservation->id}")->assertNotFound();
    }

    public function test_customer_can_cancel_own_active_reservation(): void
    {
        [$user, $reservation] = $this->reservationFixture('CONFIRMED');
        Sanctum::actingAs($user);

        $this->deleteJson("/api/users/me/reservations/{$reservation->id}")
            ->assertOk()
            ->assertJsonPath('reservation.status', 'CANCELLED');

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'CANCELLED',
        ]);
    }

    public function test_completed_reservation_cannot_be_cancelled(): void
    {
        [$user, $reservation] = $this->reservationFixture('COMPLETED');
        Sanctum::actingAs($user);

        $this->deleteJson("/api/users/me/reservations/{$reservation->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', '현재 상태에서는 예약을 취소할 수 없습니다.');
    }

    private function reservationFixture(string $status, ?string $number = null): array
    {
        $user = User::factory()->create();
        $store = Store::create([
            'name' => '내 예약 테스트',
            'slug' => 'my-reservation-'.uniqid(),
            'address' => '서울',
            'is_active' => true,
        ]);
        $slot = ReservationSlot::create([
            'store_id' => $store->id,
            'slot_date' => today()->addDay(),
            'start_time' => '14:00',
            'end_time' => '15:00',
            'is_active' => true,
        ]);
        $reservation = Reservation::create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'reservation_slot_id' => $slot->id,
            'reservation_number' => $number ?? 'MY-'.uniqid(),
            'guest_count' => 2,
            'customer_name' => $user->name,
            'customer_phone' => '010-0000-0000',
            'status' => $status,
        ]);

        return [$user, $reservation];
    }
}
