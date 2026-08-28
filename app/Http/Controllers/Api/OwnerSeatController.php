<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreSeat;
use App\Services\OwnerStoreAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OwnerSeatController extends Controller
{
    private const STATUSES = ['AVAILABLE', 'UNAVAILABLE', 'MAINTENANCE'];

    private const TYPES = ['WINDOW', 'NORMAL', 'GROUP', 'OUTDOOR'];

    public function __construct(private readonly OwnerStoreAccessService $storeAccess) {}

    public function indexMine(Request $request): JsonResponse
    {
        return $this->index($request, $this->storeAccess->primary($request->user()));
    }

    public function storeMine(Request $request): JsonResponse
    {
        return $this->store($request, $this->storeAccess->primary($request->user()));
    }

    public function updateMine(Request $request, StoreSeat $seat): JsonResponse
    {
        return $this->update($request, $this->storeAccess->primary($request->user()), $seat);
    }

    public function updateManyMine(Request $request): JsonResponse
    {
        return $this->updateMany($request, $this->storeAccess->primary($request->user()));
    }

    public function resetMine(Request $request): JsonResponse
    {
        return $this->reset($request, $this->storeAccess->primary($request->user()));
    }

    public function destroyMine(Request $request, StoreSeat $seat): JsonResponse
    {
        return $this->destroy($request, $this->storeAccess->primary($request->user()), $seat);
    }

    public function index(Request $request, Store $store): JsonResponse
    {
        $this->authorizeStore($request, $store);

        return response()->json([
            'data' => $store->seats()->orderBy('floor_number')->orderBy('seat_code')->get(),
        ]);
    }

    public function store(Request $request, Store $store): JsonResponse
    {
        $this->authorizeStore($request, $store);
        $this->normalizeSeatStatus($request);
        $validated = $request->validate([
            'seat_code' => ['required', 'string', 'max:30', Rule::unique('store_seats')->where('store_id', $store->id)],
            'seat_name' => ['required', 'string', 'max:100'],
            'seat_type' => ['sometimes', Rule::in(self::TYPES)],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'floor_number' => ['sometimes', 'integer', 'min:-10', 'max:200'],
            'position_x' => ['nullable', 'integer'],
            'position_y' => ['nullable', 'integer'],
            'status' => ['sometimes', Rule::in(self::STATUSES)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $seat = $store->seats()->create($validated);

        return response()->json(['message' => '좌석이 등록되었습니다.', 'seat' => $seat], 201);
    }

    public function update(Request $request, Store $store, StoreSeat $seat): JsonResponse
    {
        $this->authorizeStore($request, $store);
        abort_unless($seat->store_id === $store->id, 404);
        $this->normalizeSeatStatus($request);

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

        // Legacy frontend compatibility: this path was once inferred as the
        // store open/closed toggle before its seat-batch contract was known.
        if ($request->has('is_active') && ! $request->has('seats')) {
            $validated = $request->validate(['is_active' => ['required', 'boolean']]);
            $store->forceFill(['is_open' => $validated['is_active']])->save();

            return response()->json([
                'message' => $store->is_open ? '영업 중으로 변경되었습니다.' : '영업 마감으로 변경되었습니다.',
                'store' => $store->fresh(),
            ]);
        }

        if (is_array($request->input('seats'))) {
            $request->merge(['seats' => collect($request->input('seats'))->map(function ($seat) {
                if (is_array($seat) && array_key_exists('status', $seat)) {
                    $seat['status'] = $this->normalizedSeatStatus($seat['status']);
                }

                return $seat;
            })->all()]);
        }

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

    public function reset(Request $request, Store $store): JsonResponse
    {
        $this->authorizeStore($request, $store);

        if (! $request->has('total_seats')) {
            $request->merge([
                'total_seats' => $request->input('seat_count', $request->input('count')),
            ]);
        }

        $validated = $request->validate([
            'total_seats' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $seats = DB::transaction(function () use ($store, $validated) {
            $changedAt = now();
            $resetKey = Str::lower(Str::random(12));
            $existing = StoreSeat::withTrashed()
                ->where('store_id', $store->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($existing as $seat) {
                $seat->forceFill(['seat_code' => "reset-{$resetKey}-{$seat->id}"])->saveQuietly();
            }

            for ($number = 1; $number <= $validated['total_seats']; $number++) {
                $seat = $existing->get($number - 1);
                $attributes = [
                    'seat_code' => (string) $number,
                    'seat_name' => "{$number}번 좌석",
                    'seat_type' => 'NORMAL',
                    'capacity' => 1,
                    'floor_number' => 1,
                    'position_x' => ($number - 1) % 4,
                    'position_y' => intdiv($number - 1, 4),
                    'status' => 'AVAILABLE',
                    'is_active' => true,
                    'deleted_at' => null,
                    'updated_at' => $changedAt,
                ];

                if ($seat) {
                    $seat->forceFill($attributes)->saveQuietly();
                } else {
                    $store->seats()->create($attributes);
                }
            }

            foreach ($existing->slice($validated['total_seats']) as $seat) {
                $seat->forceFill([
                    'status' => 'AVAILABLE',
                    'is_active' => false,
                    'updated_at' => $changedAt,
                ])->saveQuietly();
                $seat->delete();
            }

            $store->forceFill(['availability_updated_at' => $changedAt])->save();

            return $store->seats()->orderByRaw('CAST(seat_code AS UNSIGNED)')->get();
        });

        return response()->json([
            'message' => '좌석 설정이 초기화되었습니다.',
            'seats' => $seats,
            'availability' => $this->availability($store),
        ]);
    }

    public function destroy(Request $request, Store $store, StoreSeat $seat): JsonResponse
    {
        $this->authorizeStore($request, $store);
        abort_unless($seat->store_id === $store->id, 404);
        $seat->delete();

        return response()->json([], 204);
    }

    private function authorizeStore(Request $request, Store $store): void
    {
        $this->storeAccess->authorize($request->user(), $store);
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

    private function normalizeSeatStatus(Request $request): void
    {
        if ($request->has('status')) {
            $request->merge(['status' => $this->normalizedSeatStatus($request->input('status'))]);
        }
    }

    private function normalizedSeatStatus(mixed $status): string
    {
        $status = strtoupper(trim((string) $status));

        return match ($status) {
            'EMPTY', 'VACANT', 'FREE', '비어있음', '빈좌석' => 'AVAILABLE',
            'IN_USE', 'IN-USE', 'OCCUPIED', 'USED', '사용중' => 'UNAVAILABLE',
            'WAIT', 'WAITING', 'PENDING', '대기', '점검중' => 'MAINTENANCE',
            default => $status,
        };
    }
}
