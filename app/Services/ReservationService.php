<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Models\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReservationService
{
    private const CAPACITY_STATUSES = [
        'PENDING_APPROVAL',
        'AWAITING_PAYMENT',
        'CONFIRMED',
    ];

    public function create(User $user, Store $store, array $data): Reservation
    {
        abort_unless($store->is_active, 404);
        abort_unless($store->reservation_enabled, 422, '현재 예약을 받지 않는 매장입니다.');

        return DB::transaction(function () use ($user, $store, $data) {
            $slot = ReservationSlot::query()->lockForUpdate()->findOrFail($data['reservation_slot_id']);

            abort_unless($slot->store_id === $store->id, 422, '선택한 시간은 해당 매장의 예약 시간이 아닙니다.');
            abort_unless($slot->is_active, 422, '비활성화된 예약 시간입니다.');

            $startAt = Carbon::parse($slot->slot_date->format('Y-m-d').' '.$slot->start_time);
            abort_if($startAt->lte(now()), 422, '이미 지난 예약 시간입니다.');
            abort_if($store->closures()->whereDate('closure_date', $slot->slot_date)->exists(), 422, '임시 휴무일에는 예약할 수 없습니다.');

            $businessHour = $store->businessHours()->where('day_of_week', $slot->slot_date->dayOfWeek)->first();
            abort_if($businessHour?->is_closed === true, 422, '정기 휴무일에는 예약할 수 없습니다.');
            abort_if($businessHour && (
                $slot->start_time < $businessHour->opening_time || $slot->end_time > $businessHour->closing_time
            ), 422, '영업시간 밖에는 예약할 수 없습니다.');

            $duplicateExists = Reservation::query()
                ->where('user_id', $user->id)
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

            abort_if($remainingCount < $data['guest_count'], 422, "남은 예약 가능 인원은 {$remainingCount}명입니다.");

            return Reservation::create([
                'user_id' => $user->id,
                'store_id' => $store->id,
                'reservation_slot_id' => $slot->id,
                'reservation_number' => 'RSV-'.$slot->slot_date->format('Ymd').'-'.Str::upper(Str::random(8)),
                'guest_count' => $data['guest_count'],
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'customer_request' => $data['customer_request'] ?? null,
                'status' => 'PENDING_APPROVAL',
                'approval_expires_at' => now()->addMinutes(30),
            ]);
        });
    }
}
