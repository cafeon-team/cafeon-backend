<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MapStoreController extends Controller
{
    private const CONGESTION_LEVELS = ['RELAXED', 'NORMAL', 'BUSY', 'NEAR_FULL', 'UNAVAILABLE'];

    public function index(Request $request): JsonResponse
    {
        if (is_string($request->input('congestion'))) {
            $request->merge([
                'congestion' => array_values(array_filter(explode(',', strtoupper($request->string('congestion')->toString())))),
            ]);
        }

        $validated = $request->validate([
            'sw_lat' => ['required', 'numeric', 'between:-90,90', 'lt:ne_lat'],
            'sw_lng' => ['required', 'numeric', 'between:-180,180', 'lt:ne_lng'],
            'ne_lat' => ['required', 'numeric', 'between:-90,90'],
            'ne_lng' => ['required', 'numeric', 'between:-180,180'],
            'keyword' => ['nullable', 'string', 'max:100'],
            'congestion' => ['nullable', 'array', 'max:5'],
            'congestion.*' => ['string', Rule::in(self::CONGESTION_LEVELS)],
            'limit' => ['nullable', 'integer', 'between:1,200'],
        ]);

        $limit = (int) ($validated['limit'] ?? 100);
        $levels = $validated['congestion'] ?? [];

        $stores = Store::query()
            ->select([
                'id', 'name', 'slug', 'address', 'detail_address', 'latitude', 'longitude',
                'thumbnail_url', 'reservation_enabled', 'availability_updated_at',
            ])
            ->where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('latitude', [(float) $validated['sw_lat'], (float) $validated['ne_lat']])
            ->whereBetween('longitude', [(float) $validated['sw_lng'], (float) $validated['ne_lng']])
            ->when($validated['keyword'] ?? null, function (Builder $query, string $keyword): void {
                $query->where(function (Builder $query) use ($keyword): void {
                    $query->where('name', 'like', "%{$keyword}%")
                        ->orWhere('address', 'like', "%{$keyword}%")
                        ->orWhere('detail_address', 'like', "%{$keyword}%");
                });
            })
            ->withCount([
                'seats as total_seat_units' => fn (Builder $query) => $query->where('is_active', true),
                'reviews as review_count' => fn (Builder $query) => $query->where('status', 'VISIBLE'),
            ])
            ->withSum(['seats as total_capacity' => fn (Builder $query) => $query->where('is_active', true)], 'capacity')
            ->withSum(['seats as occupied_capacity' => fn (Builder $query) => $query->where('is_active', true)->where('status', 'UNAVAILABLE')], 'capacity')
            ->withSum(['seats as maintenance_capacity' => fn (Builder $query) => $query->where('is_active', true)->where('status', 'MAINTENANCE')], 'capacity')
            ->withAvg(['reviews as rating' => fn (Builder $query) => $query->where('status', 'VISIBLE')], 'rating')
            ->orderBy('id')
            ->limit(1000)
            ->get()
            ->map(fn (Store $store): array => $this->markerData($store))
            ->when($levels !== [], fn ($stores) => $stores->whereIn('congestion', $levels))
            ->take($limit)
            ->values();

        return response()->json([
            'data' => $stores,
            'meta' => [
                'count' => $stores->count(),
                'limit' => $limit,
                'bounds' => [
                    'south_west' => ['latitude' => (float) $validated['sw_lat'], 'longitude' => (float) $validated['sw_lng']],
                    'north_east' => ['latitude' => (float) $validated['ne_lat'], 'longitude' => (float) $validated['ne_lng']],
                ],
            ],
        ]);
    }

    private function markerData(Store $store): array
    {
        $totalCapacity = (int) ($store->total_capacity ?? 0);
        $occupiedCapacity = (int) ($store->occupied_capacity ?? 0);
        $maintenanceCapacity = (int) ($store->maintenance_capacity ?? 0);
        $usableCapacity = max(0, $totalCapacity - $maintenanceCapacity);
        $availableCapacity = max(0, $totalCapacity - $occupiedCapacity - $maintenanceCapacity);
        $occupancyRate = $usableCapacity > 0 ? (int) round(($occupiedCapacity / $usableCapacity) * 100) : 0;

        [$congestion, $label] = match (true) {
            $usableCapacity === 0 => ['UNAVAILABLE', '이용 불가'],
            $occupancyRate >= 90 => ['NEAR_FULL', '만석 임박'],
            $occupancyRate >= 70 => ['BUSY', '혼잡'],
            $occupancyRate >= 40 => ['NORMAL', '보통'],
            default => ['RELAXED', '여유'],
        };

        return [
            'id' => $store->id,
            'name' => $store->name,
            'slug' => $store->slug,
            'latitude' => (float) $store->latitude,
            'longitude' => (float) $store->longitude,
            'address' => $store->address,
            'detail_address' => $store->detail_address,
            'thumbnail_url' => $store->thumbnail_url,
            'rating' => $store->rating === null ? null : round((float) $store->rating, 1),
            'review_count' => (int) $store->review_count,
            'total_seat_units' => (int) $store->total_seat_units,
            'total_capacity' => $totalCapacity,
            'available_capacity' => $availableCapacity,
            'occupancy_rate' => $occupancyRate,
            'congestion' => $congestion,
            'congestion_label' => $label,
            'reservation_enabled' => (bool) $store->reservation_enabled,
            'availability_updated_at' => $store->availability_updated_at?->toIso8601String(),
        ];
    }
}
