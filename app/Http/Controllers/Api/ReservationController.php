<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReservationSlotsRequest;
use App\Http\Requests\StoreReservationRequest;
use App\Models\ReservationSlot;
use App\Models\Reservation;
use App\Models\Store;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function __construct(private readonly ReservationService $reservationService)
    {
    }

    private const CAPACITY_STATUSES = [
        'PENDING_APPROVAL',
        'AWAITING_PAYMENT',
        'CONFIRMED',
    ];

    private const CANCELLABLE_STATUSES = [
        'PENDING_APPROVAL',
        'AWAITING_PAYMENT',
        'CONFIRMED',
    ];

    public function mine(Request $request): JsonResponse
    {
        $reservations = Reservation::query()
            ->with(['store:id,name,slug,address,thumbnail_url', 'slot', 'order:id,reservation_id,order_number,status,final_amount'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10);

        return response()->json($reservations);
    }

    public function showMine(Request $request, Reservation $reservation): JsonResponse
    {
        abort_unless($reservation->user_id === $request->user()->id, 404);

        return response()->json([
            'reservation' => $reservation->load([
                'store:id,name,slug,address,detail_address,phone,thumbnail_url',
                'slot',
                'seats:id,store_id,seat_code,seat_name,seat_type,capacity,floor_number',
                'order.items.menu:id,name,image_url',
                'order.payment',
            ]),
        ]);
    }

    public function cancelMine(Request $request, Reservation $reservation): JsonResponse
    {
        abort_unless($reservation->user_id === $request->user()->id, 404);

        $cancelled = DB::transaction(function () use ($reservation) {
            $locked = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            abort_unless(in_array($locked->status, self::CANCELLABLE_STATUSES, true), 422, '현재 상태에서는 예약을 취소할 수 없습니다.');

            $locked->forceFill([
                'status' => 'CANCELLED',
                'cancelled_at' => now(),
            ])->save();

            return $locked;
        });

        return response()->json([
            'message' => '예약이 취소되었습니다.',
            'reservation' => $cancelled->load(['store:id,name,slug', 'slot']),
        ]);
    }

    public function storeFromPayload(StoreReservationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return $this->createReservation($request, Store::findOrFail($validated['store_id']), $validated);
    }

    public function store(StoreReservationRequest $request, Store $store): JsonResponse
    {
        return $this->createReservation($request, $store, $request->validated());
    }

    private function createReservation(Request $request, Store $store, array $validated): JsonResponse
    {
        $reservation = $this->reservationService->create($request->user(), $store, $validated);

        return response()->json([
            'message' => '예약 신청이 완료되었습니다. 사장님 승인을 기다려 주세요.',
            'reservation' => $reservation->load(['store:id,name,slug', 'slot']),
        ], 201);
    }

    public function slots(ReservationSlotsRequest $request, Store $store): JsonResponse
    {
        abort_unless($store->is_active, 404);

        $validated = $request->validated();

        $date = Carbon::createFromFormat('Y-m-d', $validated['date'])->startOfDay();
        $guestCount = (int) ($validated['guest_count'] ?? 1);
        $businessHour = $store->businessHours()->where('day_of_week', $date->dayOfWeek)->first();
        $closure = $store->closures()->whereDate('closure_date', $date)->first();
        $usableCapacity = (int) $store->seats()
            ->where('is_active', true)
            ->where('status', 'AVAILABLE')
            ->sum('capacity');

        $storeUnavailableReason = match (true) {
            ! $store->reservation_enabled => '예약을 받지 않는 매장입니다.',
            $closure !== null => $closure->reason ?: '임시 휴무일입니다.',
            $businessHour?->is_closed === true => '정기 휴무일입니다.',
            $usableCapacity === 0 => '예약 가능한 좌석이 없습니다.',
            default => null,
        };

        $slots = ReservationSlot::query()
            ->where('store_id', $store->id)
            ->whereDate('slot_date', $date)
            ->where('is_active', true)
            ->with(['reservations' => fn ($query) => $query
                ->whereIn('status', self::CAPACITY_STATUSES)
                ->select('id', 'reservation_slot_id', 'guest_count', 'status')])
            ->orderBy('start_time')
            ->get()
            ->map(function (ReservationSlot $slot) use ($date, $guestCount, $businessHour, $usableCapacity, $storeUnavailableReason) {
                $reservedCount = (int) $slot->reservations->sum('guest_count');
                $remainingCount = max(0, $usableCapacity - $reservedCount);
                $startAt = Carbon::parse($date->format('Y-m-d').' '.$slot->start_time);
                $isPast = $startAt->lte(now());
                $outsideBusinessHours = $businessHour && ! $businessHour->is_closed && (
                    $slot->start_time < $businessHour->opening_time || $slot->end_time > $businessHour->closing_time
                );

                $unavailableReason = match (true) {
                    $storeUnavailableReason !== null => $storeUnavailableReason,
                    $isPast => '이미 지난 시간입니다.',
                    $outsideBusinessHours => '영업시간 밖의 슬롯입니다.',
                    $remainingCount < $guestCount => '요청 인원을 수용할 수 없습니다.',
                    default => null,
                };

                return [
                    'id' => $slot->id,
                    'date' => $date->format('Y-m-d'),
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'capacity' => $usableCapacity,
                    'reserved_count' => $reservedCount,
                    'remaining_count' => $remainingCount,
                    'available' => $unavailableReason === null,
                    'unavailable_reason' => $unavailableReason,
                ];
            });

        return response()->json([
            'store_id' => $store->id,
            'store_name' => $store->name,
            'date' => $date->format('Y-m-d'),
            'guest_count' => $guestCount,
            'reservation_enabled' => $store->reservation_enabled,
            'business_hours' => $businessHour ? [
                'opening_time' => $businessHour->opening_time,
                'closing_time' => $businessHour->closing_time,
                'is_closed' => $businessHour->is_closed,
            ] : null,
            'closure' => $closure ? ['reason' => $closure->reason] : null,
            'slots' => $slots,
        ]);
    }
}
