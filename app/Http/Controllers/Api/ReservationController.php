<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReservationSlot;
use App\Models\Reservation;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
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

    public function storeFromPayload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => ['required', 'integer', 'exists:stores,id'],
        ]);

        return $this->store($request, Store::findOrFail($validated['store_id']));
    }

    public function store(Request $request, Store $store): JsonResponse
    {
        abort_unless($store->is_active, 404);

        $validated = $request->validate([
            'reservation_slot_id' => ['required', 'integer', 'exists:reservation_slots,id'],
            'guest_count' => ['required', 'integer', 'min:1', 'max:20'],
            'customer_name' => ['required', 'string', 'max:50'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'customer_request' => ['nullable', 'string', 'max:1000'],
        ]);

        abort_unless($store->reservation_enabled, 422, '현재 예약을 받지 않는 매장입니다.');

        $reservation = DB::transaction(function () use ($request, $store, $validated) {
            $slot = ReservationSlot::query()->lockForUpdate()->findOrFail($validated['reservation_slot_id']);

            abort_unless($slot->store_id === $store->id, 422, '선택한 시간은 해당 매장의 예약 시간이 아닙니다.');
            abort_unless($slot->is_active, 422, '비활성화된 예약 시간입니다.');

            $startAt = Carbon::parse($slot->slot_date->format('Y-m-d').' '.$slot->start_time);
            abort_if($startAt->lte(now()), 422, '이미 지난 예약 시간입니다.');

            $isClosure = $store->closures()->whereDate('closure_date', $slot->slot_date)->exists();
            abort_if($isClosure, 422, '임시 휴무일에는 예약할 수 없습니다.');

            $businessHour = $store->businessHours()->where('day_of_week', $slot->slot_date->dayOfWeek)->first();
            abort_if($businessHour?->is_closed === true, 422, '정기 휴무일에는 예약할 수 없습니다.');
            abort_if($businessHour && (
                $slot->start_time < $businessHour->opening_time || $slot->end_time > $businessHour->closing_time
            ), 422, '영업시간 밖에는 예약할 수 없습니다.');

            $duplicateExists = Reservation::query()
                ->where('user_id', $request->user()->id)
                ->where('reservation_slot_id', $slot->id)
                ->whereIn('status', self::CAPACITY_STATUSES)
                ->exists();
            abort_if($duplicateExists, 422, '같은 시간에 이미 예약이 있습니다.');

            $capacity = (int) $store->seats()
                ->where('is_active', true)
                ->where('status', 'AVAILABLE')
                ->sum('capacity');
            $reservedCount = (int) Reservation::query()
                ->where('reservation_slot_id', $slot->id)
                ->whereIn('status', self::CAPACITY_STATUSES)
                ->sum('guest_count');
            $remainingCount = max(0, $capacity - $reservedCount);

            abort_if($remainingCount < $validated['guest_count'], 422, "남은 예약 가능 인원은 {$remainingCount}명입니다.");

            return Reservation::create([
                'user_id' => $request->user()->id,
                'store_id' => $store->id,
                'reservation_slot_id' => $slot->id,
                'reservation_number' => 'RSV-'.$slot->slot_date->format('Ymd').'-'.Str::upper(Str::random(8)),
                'guest_count' => $validated['guest_count'],
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'],
                'customer_request' => $validated['customer_request'] ?? null,
                'status' => 'PENDING_APPROVAL',
                'approval_expires_at' => now()->addMinutes(30),
            ]);
        });

        return response()->json([
            'message' => '예약 신청이 완료되었습니다. 사장님 승인을 기다려 주세요.',
            'reservation' => $reservation->load(['store:id,name,slug', 'slot']),
        ], 201);
    }

    public function slots(Request $request, Store $store): JsonResponse
    {
        abort_unless($store->is_active, 404);

        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'guest_count' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

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
