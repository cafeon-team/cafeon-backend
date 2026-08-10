<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'keyword' => ['nullable', 'string', 'max:100'],
            'reservation_available' => ['nullable', 'boolean'],
            'tag' => ['nullable', 'string', 'max:100'],
        ]);

        $stores = Store::query()
            ->with('tags:id,store_id,name,slug')
            ->where('is_active', true)
            ->when($validated['keyword'] ?? null, function (Builder $query, string $keyword) {
                $query->where(function (Builder $query) use ($keyword) {
                    $query->where('name', 'like', "%{$keyword}%")
                        ->orWhere('address', 'like', "%{$keyword}%")
                        ->orWhere('detail_address', 'like', "%{$keyword}%");
                });
            })
            ->when($validated['tag'] ?? null, fn (Builder $query, string $tag) => $query->whereHas(
                'tags',
                fn (Builder $tags) => $tags->where('slug', $tag)->orWhere('name', $tag),
            ))
            ->when($request->boolean('reservation_available'), fn (Builder $query) => $query->where('reservation_enabled', true))
            ->orderBy('id')
            ->get();

        return response()->json($stores);
    }

    public function show(Store $store): JsonResponse
    {
        abort_unless($store->is_active, 404);

        $store->load([
            'images',
            'businessHours',
            'closures' => fn ($query) => $query->whereDate('closure_date', '>=', today())->orderBy('closure_date'),
            'seats' => fn ($query) => $query->where('is_active', true)->orderBy('floor_number')->orderBy('seat_code'),
            'menuCategories.menus' => fn ($query) => $query->where('is_available', true),
            'tags:id,store_id,name,slug',
        ]);

        return response()->json([
            'store' => $store,
            'availability' => $this->availabilityData($store),
        ]);
    }

    public function availability(Store $store): JsonResponse
    {
        abort_unless($store->is_active, 404);
        $store->load(['seats' => fn ($query) => $query->where('is_active', true)]);

        return response()->json($this->availabilityData($store));
    }

    private function availabilityData(Store $store): array
    {
        $seats = $store->seats;
        $totalCapacity = (int) $seats->sum('capacity');
        $occupiedCapacity = (int) $seats->where('status', 'UNAVAILABLE')->sum('capacity');
        $maintenanceCapacity = (int) $seats->where('status', 'MAINTENANCE')->sum('capacity');
        $availableCapacity = max(0, $totalCapacity - $occupiedCapacity - $maintenanceCapacity);
        $usableCapacity = max(0, $totalCapacity - $maintenanceCapacity);
        $occupancyRate = $usableCapacity > 0 ? (int) round(($occupiedCapacity / $usableCapacity) * 100) : 0;

        [$congestion, $label] = match (true) {
            $usableCapacity === 0 => ['UNAVAILABLE', '이용 불가'],
            $occupancyRate >= 90 => ['NEAR_FULL', '만석 임박'],
            $occupancyRate >= 70 => ['BUSY', '혼잡'],
            $occupancyRate >= 40 => ['NORMAL', '보통'],
            default => ['RELAXED', '여유'],
        };

        return [
            'store_id' => $store->id,
            'total_seat_units' => $seats->count(),
            'total_capacity' => $totalCapacity,
            'occupied_capacity' => $occupiedCapacity,
            'maintenance_capacity' => $maintenanceCapacity,
            'available_capacity' => $availableCapacity,
            'occupancy_rate' => $occupancyRate,
            'congestion' => $congestion,
            'congestion_label' => $label,
            'reservation_enabled' => $store->reservation_enabled,
            'updated_at' => now()->toIso8601String(),
        ];
    }
}
