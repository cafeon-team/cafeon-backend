<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Models\Store;
use App\Models\StoreSeat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MapStoreApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_map_returns_only_stores_inside_bounds_with_marker_data(): void
    {
        $inside = Store::create([
            'name' => '지도 카페', 'slug' => 'map-cafe', 'address' => '서울 강남구',
            'latitude' => 37.50, 'longitude' => 127.03, 'is_active' => true,
            'availability_updated_at' => '2026-08-13 10:30:00',
        ]);
        Store::create([
            'name' => '영역 밖 카페', 'slug' => 'outside-cafe', 'address' => '부산',
            'latitude' => 35.18, 'longitude' => 129.07, 'is_active' => true,
        ]);
        StoreSeat::create(['store_id' => $inside->id, 'seat_code' => 'A1', 'seat_name' => 'A1', 'capacity' => 6, 'status' => 'AVAILABLE']);
        StoreSeat::create(['store_id' => $inside->id, 'seat_code' => 'A2', 'seat_name' => 'A2', 'capacity' => 4, 'status' => 'UNAVAILABLE']);
        Review::create(['store_id' => $inside->id, 'user_id' => User::factory()->create()->id, 'rating' => 5, 'content' => '좋아요', 'status' => 'VISIBLE']);

        $this->getJson('/api/map/stores?sw_lat=37.4&sw_lng=126.9&ne_lat=37.7&ne_lng=127.2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'map-cafe')
            ->assertJsonPath('data.0.total_capacity', 10)
            ->assertJsonPath('data.0.available_capacity', 6)
            ->assertJsonPath('data.0.occupancy_rate', 40)
            ->assertJsonPath('data.0.congestion', 'NORMAL')
            ->assertJsonPath('data.0.congestion_label', '보통')
            ->assertJsonPath('data.0.availability_updated_at', fn ($value) => str_starts_with($value, '2026-08-13T10:30:00'))
            ->assertJsonPath('data.0.rating', 5)
            ->assertJsonPath('meta.count', 1);
    }

    public function test_map_supports_keyword_and_comma_separated_congestion_filter(): void
    {
        $store = Store::create([
            'name' => '여유로운 카페', 'slug' => 'relaxed-cafe', 'address' => '서울',
            'latitude' => 37.50, 'longitude' => 127.03, 'is_active' => true,
        ]);
        StoreSeat::create(['store_id' => $store->id, 'seat_code' => 'A1', 'seat_name' => 'A1', 'capacity' => 10, 'status' => 'AVAILABLE']);

        $url = '/api/map/stores?sw_lat=37.4&sw_lng=126.9&ne_lat=37.7&ne_lng=127.2&keyword='.urlencode('여유').'&congestion=RELAXED,NORMAL';
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.congestion', 'RELAXED');
    }

    public function test_map_requires_valid_bounds(): void
    {
        $this->getJson('/api/map/stores?sw_lat=37.7&sw_lng=127.2&ne_lat=37.4&ne_lng=126.9')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sw_lat', 'sw_lng']);
    }
}
