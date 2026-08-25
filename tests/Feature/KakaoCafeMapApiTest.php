<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KakaoCafeMapApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['services.kakao.rest_api_key' => 'test-rest-api-key']);
    }

    public function test_it_returns_normalized_kakao_cafes_in_map_bounds(): void
    {
        Http::fake(['dapi.kakao.com/*' => Http::response([
            'meta' => ['total_count' => 1, 'pageable_count' => 1, 'is_end' => true],
            'documents' => [[
                'id' => '12345', 'place_name' => '카페온 테스트점',
                'x' => '127.0276', 'y' => '37.4979',
                'address_name' => '서울 강남구', 'road_address_name' => '서울 강남구 테헤란로',
                'phone' => '02-1234-5678', 'place_url' => 'https://place.map.kakao.com/12345',
                'category_name' => '음식점 > 카페', 'distance' => '',
            ]],
        ])]);

        $this->getJson('/api/map/kakao-cafes?sw_lat=37.4&sw_lng=126.9&ne_lat=37.6&ne_lng=127.2')
            ->assertOk()
            ->assertJsonPath('data.0.source', 'KAKAO')
            ->assertJsonPath('data.0.kakao_place_id', '12345')
            ->assertJsonPath('data.0.latitude', 37.4979)
            ->assertJsonPath('data.0.longitude', 127.0276)
            ->assertJsonPath('meta.category_group_code', 'CE7')
            ->assertJsonPath('meta.is_end', true);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'KakaoAK test-rest-api-key')
            && $request['category_group_code'] === 'CE7'
            && $request['rect'] === '126.9,37.4,127.2,37.6');
    }

    public function test_it_validates_bounds_and_kakao_pagination_limits(): void
    {
        $this->getJson('/api/map/kakao-cafes?sw_lat=37.6&sw_lng=126.9&ne_lat=37.4&ne_lng=127.2&page=46&size=16')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sw_lat', 'page', 'size']);
    }

    public function test_empty_kakao_result_allows_manual_location_at_map_center(): void
    {
        Http::fake(['dapi.kakao.com/*' => Http::response([
            'meta' => ['total_count' => 0, 'pageable_count' => 0, 'is_end' => true],
            'documents' => [],
        ])]);

        $this->getJson('/api/map/kakao-cafes?sw_lat=35.8&sw_lng=128.5&ne_lat=36.0&ne_lng=128.7')
            ->assertOk()
            ->assertJsonPath('meta.has_cafes', false)
            ->assertJsonPath('meta.allow_manual_location', true)
            ->assertJsonPath('meta.manual_location.latitude', 35.9)
            ->assertJsonPath('meta.manual_location.longitude', 128.6);
    }

    public function test_kakao_pin_can_be_opened_by_place_id_after_map_search(): void
    {
        Http::fake(['dapi.kakao.com/*' => Http::response([
            'meta' => ['total_count' => 1, 'pageable_count' => 1, 'is_end' => true],
            'documents' => [[
                'id' => 'pin-123', 'place_name' => '핀 카페', 'x' => '127.1', 'y' => '37.5',
                'address_name' => '서울', 'road_address_name' => '서울 도로명',
            ]],
        ])]);

        $this->getJson('/api/map/kakao-cafes?sw_lat=37.4&sw_lng=127.0&ne_lat=37.6&ne_lng=127.2')->assertOk();
        $this->getJson('/api/map/pins/KAKAO/pin-123')
            ->assertOk()
            ->assertJsonPath('data.kakao_place_id', 'pin-123')
            ->assertJsonPath('data.name', '핀 카페');
    }

    public function test_it_returns_service_unavailable_without_rest_api_key(): void
    {
        config(['services.kakao.rest_api_key' => null]);

        $this->getJson('/api/map/kakao-cafes?sw_lat=37.4&sw_lng=126.9&ne_lat=37.6&ne_lng=127.2')
            ->assertServiceUnavailable();
    }

    public function test_it_returns_bad_gateway_when_kakao_fails(): void
    {
        Http::fake(['dapi.kakao.com/*' => Http::response(['message' => 'upstream error'], 500)]);

        $this->getJson('/api/map/kakao-cafes?sw_lat=37.4&sw_lng=126.9&ne_lat=37.6&ne_lng=127.2')
            ->assertStatus(502);
    }
}
