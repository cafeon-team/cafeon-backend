<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DirectionApiTest extends TestCase
{
    public function test_walk_direction_is_normalized_for_map_drawing(): void
    {
        config(['services.kakao.rest_api_key' => 'test-rest-key']);
        Http::fake(['dapi.kakao.com/*' => Http::response([
            'status' => 'OK',
            'route' => [
                'properties' => ['totalDistance' => 450, 'totalTime' => 360, 'landingUrl' => 'https://map.kakao.com/route'],
                'legs' => [['steps' => [[
                    'properties' => ['guidance' => '직진', 'distance' => 100, 'time' => 80],
                    'path' => ['points' => [[127.1, 37.5], [127.2, 37.6]]],
                ]]]],
            ],
        ])]);

        $this->getJson('/api/directions?origin_lat=37.5&origin_lng=127.1&destination_lat=37.6&destination_lng=127.2')
            ->assertOk()
            ->assertJsonPath('mode', 'WALK')
            ->assertJsonPath('distance_meters', 450)
            ->assertJsonPath('duration_seconds', 360)
            ->assertJsonCount(2, 'path');

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'KakaoAK test-rest-key')
            && str_contains($request->url(), '/v2/routing/walk'));
    }

    public function test_direction_rejects_invalid_coordinates(): void
    {
        $this->getJson('/api/directions?origin_lat=200')->assertUnprocessable();
    }
}
