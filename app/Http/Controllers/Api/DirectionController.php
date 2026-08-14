<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class DirectionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'origin_lat' => ['required', 'numeric', 'between:-90,90'],
            'origin_lng' => ['required', 'numeric', 'between:-180,180'],
            'destination_lat' => ['required', 'numeric', 'between:-90,90'],
            'destination_lng' => ['required', 'numeric', 'between:-180,180'],
            'mode' => ['nullable', Rule::in(['WALK', 'BICYCLE'])],
            'route_mode' => ['nullable', 'string', 'max:30'],
        ]);

        $apiKey = config('services.kakao.rest_api_key');
        abort_unless(filled($apiKey), 503, '카카오 REST API 키가 설정되지 않았습니다.');

        $mode = $validated['mode'] ?? 'WALK';
        $endpoint = $mode === 'BICYCLE' ? 'bicycle' : 'walk';
        $allowedRouteModes = $mode === 'BICYCLE'
            ? ['BIKE_ONLY', 'SHORTEST', 'ACCESSIBLE']
            : ['BROAD_FIRST', 'SHORTEST', 'ACCESSIBLE'];
        $routeMode = strtoupper($validated['route_mode'] ?? $allowedRouteModes[0]);
        abort_unless(in_array($routeMode, $allowedRouteModes, true), 422, '지원하지 않는 경로 탐색 옵션입니다.');

        try {
            $response = Http::withHeaders(['Authorization' => 'KakaoAK '.$apiKey])
                ->acceptJson()
                ->timeout(8)
                ->retry(2, 150, throw: false)
                ->get("https://dapi.kakao.com/v2/routing/{$endpoint}", [
                    'start_x' => $validated['origin_lng'],
                    'start_y' => $validated['origin_lat'],
                    'end_x' => $validated['destination_lng'],
                    'end_y' => $validated['destination_lat'],
                    'input_coord' => 'WGS84',
                    'output_coord' => 'WGS84',
                    'route_mode' => $routeMode,
                ]);
        } catch (ConnectionException) {
            return response()->json(['message' => '카카오 길찾기 서버에 연결할 수 없습니다.'], 502);
        }

        if ($response->failed()) {
            return response()->json(['message' => '길찾기 요청에 실패했습니다.', 'provider' => $response->json()], 502);
        }

        $body = $response->json();
        if (($body['status'] ?? null) !== 'OK' || empty($body['route'])) {
            return response()->json(['message' => '경로를 찾을 수 없습니다.', 'status' => $body['status'] ?? 'UNKNOWN'], 422);
        }

        $route = $body['route'];
        $steps = collect($route['legs'] ?? [])->flatMap(fn (array $leg) => $leg['steps'] ?? [])->values();

        return response()->json([
            'mode' => $mode,
            'distance_meters' => (int) ($route['properties']['totalDistance'] ?? 0),
            'duration_seconds' => (int) ($route['properties']['totalTime'] ?? 0),
            'landing_url' => $route['properties']['landingUrl'] ?? null,
            'path' => $steps->flatMap(fn (array $step) => $step['path']['points'] ?? [])->values(),
            'guides' => $steps->map(fn (array $step) => [
                'guidance' => $step['properties']['guidance'] ?? null,
                'distance_meters' => (int) ($step['properties']['distance'] ?? 0),
                'duration_seconds' => (int) ($step['properties']['time'] ?? 0),
            ])->values(),
        ]);
    }
}
