<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\StoreSeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_stores_can_be_searched_by_keyword(): void
    {
        Store::create(['name' => '강남 카페온', 'slug' => 'gangnam', 'address' => '서울 강남구', 'is_active' => true]);
        Store::create(['name' => '홍대 카페온', 'slug' => 'hongdae', 'address' => '서울 마포구', 'is_active' => true]);

        $this->getJson('/api/stores?keyword=강남')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.slug', 'gangnam');
    }

    public function test_store_availability_returns_capacity_and_congestion(): void
    {
        $store = Store::create(['name' => '좌석 테스트', 'slug' => 'seat-test', 'address' => '서울', 'is_active' => true]);
        StoreSeat::create(['store_id' => $store->id, 'seat_code' => 'A1', 'seat_name' => 'A1', 'capacity' => 4, 'status' => 'AVAILABLE']);
        StoreSeat::create(['store_id' => $store->id, 'seat_code' => 'A2', 'seat_name' => 'A2', 'capacity' => 4, 'status' => 'UNAVAILABLE']);

        $this->getJson("/api/stores/{$store->id}/congestion")
            ->assertOk()
            ->assertJsonPath('total_capacity', 8)
            ->assertJsonPath('occupied_capacity', 4)
            ->assertJsonPath('available_capacity', 4)
            ->assertJsonPath('occupancy_rate', 50)
            ->assertJsonPath('congestion', 'NORMAL');
    }
}
