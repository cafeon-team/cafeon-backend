<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreSeat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OwnerSeatController extends Controller
{
    private const STATUSES = ['AVAILABLE', 'UNAVAILABLE', 'MAINTENANCE'];

    public function update(Request $request, Store $store, StoreSeat $seat): JsonResponse
    {
        $this->authorizeStore($request, $store);
        abort_unless($seat->store_id === $store->id, 404);

        $validated = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
        ]);

        DB::transaction(function () use ($seat, $store, $validated): void {
            $changedAt = now();
            $seat->forceFill(['status' => $validated['status'], 'updated_at' => $changedAt])->save();
            $store->forceFill(['availability_updated_at' => $changedAt])->save();
        });

        return response()->json([
            'message' => '좌석 상태가 변경되었습니다.',
            'seat' => $seat->fresh(),
            'availability' => $this->availability($store),
        ]);
    }

    public function updateMany(Request $request, Store $store): JsonResponse
    {
        $this->authorizeStore($request, $store);

        $validated = $request->validate([
            'seats' => ['required', 'array', 'min:1', 'max:100'],
            'seats.*.id' => ['required', 'integer', 'distinct'],
            'seats.*.status' => ['required', Rule::in(self::STATUSES)],
        ]);

        $seatIds = collect($validated['seats'])->pluck('id');
        $updatedSeats = DB::transaction(function () use ($store, $validated, $seatIds) {
            $changedAt = now();
            $seats = StoreSeat::query()
                ->where('store_id', $store->id)
                ->whereIn('id', $seatIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            abort_unless($seats->count() === $seatIds->count(), 422, '다른 매장의 좌석이 포함되었거나 존재하지 않는 좌석입니다.');

            foreach ($validated['seats'] as $change) {
                $seats[$change['id']]->forceFill(['status' => $change['status'], 'updated_at' => $changedAt])->save();
            }

            $store->forceFill(['availability_updated_at' => $changedAt])->save();

            return $seats->values()->map->fresh();
        });

        return response()->json([
            'message' => '좌석 현황이 변경되었습니다.',
            'seats' => $updatedSeats,
            'availability' => $this->availability($store),
        ]);
    }

    private function authorizeStore(Request $request, Store $store): void
    {
        $user = $request->user();
        $isAdmin = strtoupper((string) $user->role) === 'ADMIN';
        $isManager = $store->members()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereIn('role', ['OWNER', 'MANAGER'])
            ->exists();

        abort_unless($isAdmin || $isManager, 403, '매장의 좌석을 관리할 권한이 없습니다.');
    }

    private function availability(Store $store): array
    {
        $store->refresh();
        $seats = $store->seats()->where('is_active', true)->get();
        $totalCapacity = (int) $seats->sum('capacity');
        $occupiedCapacity = (int) $seats->where('status', 'UNAVAILABLE')->sum('capacity');
        $maintenanceCapacity = (int) $seats->where('status', 'MAINTENANCE')->sum('capacity');
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
            'store_id' => $store->id,
            'total_seat_units' => $seats->count(),
            'total_capacity' => $totalCapacity,
            'occupied_capacity' => $occupiedCapacity,
            'maintenance_capacity' => $maintenanceCapacity,
            'available_capacity' => $availableCapacity,
            'occupancy_rate' => $occupancyRate,
            'congestion' => $congestion,
            'congestion_label' => $label,
            'availability_updated_at' => $store->availability_updated_at?->toIso8601String(),
            'updated_at' => $store->availability_updated_at?->toIso8601String(),
        ];
    }
}
