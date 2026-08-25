<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Models\Store;
use App\Models\StoreMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerOperationsPersistenceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_seats_are_restored_after_logout_and_login(): void
    {
        $signup = $this->postJson('/api/auth/owner/signup', [
            'name' => '좌석 저장 사장님',
            'email' => 'seat-persistence-owner@cafeon.test',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
            'phone' => '010-1111-2222',
            'store_name' => '좌석 저장 카페',
            'terms_accepted' => true,
        ])->assertCreated();
        $headers = ['Authorization' => 'Bearer '.$signup->json('token')];

        $this->withHeaders($headers)->postJson('/api/owner/seats/reset', [
            'total_seats' => 15,
        ])->assertOk()->assertJsonCount(15, 'seats');

        $this->withHeaders($headers)->postJson('/api/logout')->assertOk();

        $login = $this->postJson('/api/auth/owner/login', [
            'email' => 'seat-persistence-owner@cafeon.test',
            'password' => 'password1234',
        ])->assertOk();
        $newHeaders = ['Authorization' => 'Bearer '.$login->json('token')];

        $this->withHeaders($newHeaders)->getJson('/api/owner/seats')
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('data.0.seat_code', '1')
            ->assertJsonFragment(['seat_code' => '15']);
    }

    public function test_owner_operations_are_restored_after_logout_and_login(): void
    {
        $signup = $this->postJson('/api/auth/owner/signup', [
            'name' => '운영 사장님',
            'email' => 'operations-owner@cafeon.test',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
            'phone' => '010-1234-5678',
            'store_name' => '운영 저장 카페',
            'terms_accepted' => true,
        ])->assertCreated();
        $storeId = $signup->json('store_id');
        $headers = ['Authorization' => 'Bearer '.$signup->json('token')];

        $this->withHeaders($headers)->patchJson('/api/owner/store/business-status', [
            'is_active' => false,
        ])->assertOk()->assertJsonPath('store.is_open', false);

        $categoryId = $this->withHeaders($headers)->postJson('/api/owner/menu-categories', [
            'name' => '커피',
        ])->assertCreated()->json('category.id');
        $menuId = $this->withHeaders($headers)->postJson('/api/owner/menus', [
            'category' => '커피',
            'name' => '저장되는 아메리카노',
            'description' => '재로그인 후에도 남는 메뉴',
            'price' => 4500,
        ])->assertCreated()->json('menu.id');

        $seatId = $this->withHeaders($headers)->postJson('/api/owner/seats', [
            'seat_code' => 'A1',
            'seat_name' => '창가 좌석',
            'seat_type' => 'WINDOW',
            'capacity' => 2,
            'status' => '비어있음',
        ])->assertCreated()->json('seat.id');
        $this->withHeaders($headers)->patchJson("/api/owner/seats/{$seatId}", [
            'status' => '사용중',
        ])->assertOk()->assertJsonPath('seat.status', 'UNAVAILABLE');

        $customer = User::factory()->create(['role' => 'CUSTOMER']);
        $slot = ReservationSlot::create([
            'store_id' => $storeId,
            'slot_date' => today()->addDay(),
            'start_time' => '14:00',
            'end_time' => '15:00',
        ]);
        $pending = $this->reservation($customer, $storeId, $slot->id, 'PENDING-'.uniqid());
        $accepted = $this->reservation($customer, $storeId, $slot->id, 'ACCEPTED-'.uniqid());

        $this->withHeaders($headers)->patchJson("/api/owner/reservations/{$accepted->id}/status", [
            'status' => 'ACCEPTED',
        ])->assertOk()->assertJsonPath('reservation.status', 'CONFIRMED');

        $this->withHeaders($headers)->postJson('/api/logout')->assertOk();
        $login = $this->postJson('/api/auth/owner/login', [
            'email' => 'operations-owner@cafeon.test',
            'password' => 'password1234',
        ])->assertOk()
            ->assertJsonPath('store_id', $storeId)
            ->assertJsonPath('store.is_open', false);
        $newHeaders = ['Authorization' => 'Bearer '.$login->json('token')];

        $this->withHeaders($newHeaders)->getJson('/api/owner/dashboard')
            ->assertOk()
            ->assertJsonPath('summary.is_open', false)
            ->assertJsonPath('summary.seat_count', 1)
            ->assertJsonPath('summary.seat_capacity', 2)
            ->assertJsonPath('summary.reservation_count', 2)
            ->assertJsonPath('summary.pending_reservation_count', 1)
            ->assertJsonPath('menuItems.0.id', $menuId)
            ->assertJsonPath('pendingReservations.0.id', $pending->id);

        $this->withHeaders($newHeaders)->getJson('/api/owner/reservations?status=PENDING_APPROVAL')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $pending->id);
        $this->withHeaders($newHeaders)->getJson('/api/owner/menus')
            ->assertOk()
            ->assertJsonPath('menus.data.0.id', $menuId)
            ->assertJsonPath('menus.data.0.category', '커피')
            ->assertJsonPath('menus.data.0.category_name', '커피')
            ->assertJsonPath('menus.data.0.category_detail.name', '커피');
        $this->withHeaders($newHeaders)->getJson('/api/owner/seats')
            ->assertOk()->assertJsonPath('data.0.id', $seatId)->assertJsonPath('data.0.status', 'UNAVAILABLE');

        $this->assertDatabaseHas('stores', ['id' => $storeId, 'is_open' => false]);
        $this->assertDatabaseHas('menus', ['id' => $menuId, 'store_id' => $storeId]);
        $this->assertDatabaseHas('store_seats', ['id' => $seatId, 'store_id' => $storeId, 'status' => 'UNAVAILABLE']);
        $this->assertDatabaseHas('reservations', ['id' => $pending->id, 'status' => 'PENDING_APPROVAL']);
        $this->assertDatabaseHas('reservations', ['id' => $accepted->id, 'status' => 'CONFIRMED']);
    }

    public function test_admin_role_without_membership_cannot_read_another_owners_operations(): void
    {
        $owner = User::factory()->create(['role' => 'ADMIN']);
        $unrelatedAdmin = User::factory()->create(['role' => 'ADMIN']);
        $store = Store::create(['name' => '보호 매장', 'slug' => 'protected-operations', 'address' => '서울']);
        StoreMember::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'role' => 'OWNER',
            'is_active' => true,
        ]);

        $this->actingAs($unrelatedAdmin, 'sanctum')->getJson("/api/owner/stores/{$store->id}/dashboard")->assertForbidden();
        $this->actingAs($unrelatedAdmin, 'sanctum')->getJson("/api/stores/{$store->id}/reservations")->assertForbidden();
        $this->actingAs($unrelatedAdmin, 'sanctum')->getJson("/api/owner/stores/{$store->id}/menus")->assertForbidden();
        $this->actingAs($unrelatedAdmin, 'sanctum')->getJson("/api/owner/stores/{$store->id}/seats")->assertForbidden();
    }

    private function reservation(User $customer, int $storeId, int $slotId, string $number): Reservation
    {
        return Reservation::create([
            'user_id' => $customer->id,
            'store_id' => $storeId,
            'reservation_slot_id' => $slotId,
            'reservation_number' => $number,
            'guest_count' => 2,
            'customer_name' => $customer->name,
            'customer_phone' => '010-0000-0000',
            'status' => 'PENDING_APPROVAL',
        ]);
    }
}
