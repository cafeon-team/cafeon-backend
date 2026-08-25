<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerVisit;
use App\Models\Reservation;
use App\Models\Store;
use App\Services\OwnerStoreAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OwnerReservationController extends Controller
{
    private const TRANSITIONS = [
        'PENDING_APPROVAL' => ['CONFIRMED', 'REJECTED', 'CANCELLED', 'EXPIRED'],
        'AWAITING_PAYMENT' => ['CONFIRMED', 'CANCELLED', 'PAYMENT_FAILED', 'EXPIRED'],
        'CONFIRMED' => ['COMPLETED', 'CANCELLED', 'NO_SHOW'],
    ];

    public function __construct(private readonly OwnerStoreAccessService $storeAccess) {}

    public function indexMine(Request $request): JsonResponse
    {
        return $this->index($request, $this->storeAccess->primary($request->user()));
    }

    public function index(Request $request, Store $store): JsonResponse
    {
        $this->authorizeStore($request, $store);

        $validated = $request->validate([
            'status' => ['nullable', Rule::in([
                'PENDING_APPROVAL', 'AWAITING_PAYMENT', 'CONFIRMED', 'REJECTED',
                'CANCELLED', 'COMPLETED', 'NO_SHOW', 'PAYMENT_FAILED', 'EXPIRED',
            ])],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $reservations = Reservation::query()
            ->with(['user:id,name,email,phone', 'slot', 'seats:id,store_id,seat_code,seat_name,capacity', 'order:id,reservation_id,order_number,status,final_amount'])
            ->where('store_id', $store->id)
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['date'] ?? null, fn ($query, $date) => $query->whereHas('slot', fn ($slot) => $slot->whereDate('slot_date', $date)))
            ->latest()
            ->paginate(20);

        return response()->json($reservations);
    }

    public function updateStatus(Request $request, Reservation $reservation): JsonResponse
    {
        $reservation->loadMissing('store', 'slot');
        $this->authorizeStore($request, $reservation->store);
        if ($request->has('status')) {
            $status = strtoupper(trim((string) $request->input('status')));
            $request->merge(['status' => match ($status) {
                'ACCEPT', 'ACCEPTED', 'APPROVE', 'APPROVED', '수락', '승인' => 'CONFIRMED',
                'REJECT', 'DECLINED', '거절' => 'REJECTED',
                'CANCEL', '취소' => 'CANCELLED',
                'COMPLETE', '완료' => 'COMPLETED',
                default => $status,
            }]);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in([
                'CONFIRMED', 'REJECTED', 'CANCELLED', 'COMPLETED',
                'NO_SHOW', 'PAYMENT_FAILED', 'EXPIRED',
            ])],
            'reason' => ['nullable', 'string', 'max:500', Rule::requiredIf($request->input('status') === 'REJECTED')],
        ]);

        $updated = DB::transaction(function () use ($request, $reservation, $validated) {
            $locked = Reservation::query()->with('slot')->lockForUpdate()->findOrFail($reservation->id);
            $allowed = self::TRANSITIONS[$locked->status] ?? [];
            abort_unless(in_array($validated['status'], $allowed, true), 422, "{$locked->status} 상태에서 {$validated['status']} 상태로 변경할 수 없습니다.");

            if ($validated['status'] === 'NO_SHOW') {
                $slotStart = $locked->slot->slot_date->format('Y-m-d').' '.$locked->slot->start_time;
                abort_if(now()->lt($slotStart), 422, '예약 시간이 지나기 전에는 노쇼 처리할 수 없습니다.');
            }

            $changes = ['status' => $validated['status']];
            if ($validated['status'] === 'CONFIRMED') {
                $changes += ['approved_by' => $request->user()->id, 'approved_at' => now(), 'confirmed_at' => now()];
            } elseif ($validated['status'] === 'REJECTED') {
                $changes += ['rejected_at' => now(), 'rejection_reason' => $validated['reason']];
            } elseif ($validated['status'] === 'CANCELLED') {
                $changes['cancelled_at'] = now();
            } elseif ($validated['status'] === 'COMPLETED') {
                $changes['completed_at'] = now();
            }

            $locked->forceFill($changes)->save();

            if ($validated['status'] === 'COMPLETED') {
                CustomerVisit::firstOrCreate(
                    ['reservation_id' => $locked->id],
                    [
                        'user_id' => $locked->user_id,
                        'store_id' => $locked->store_id,
                        'type' => 'RESERVATION',
                        'visited_at' => $locked->completed_at,
                        'confirmed_by' => $request->user()->id,
                        'idempotency_key' => "reservation:{$locked->id}:completed",
                    ],
                );
            }

            return $locked;
        });

        return response()->json([
            'message' => '예약 상태가 변경되었습니다.',
            'reservation' => $updated->load(['user:id,name,email,phone', 'store:id,name,slug', 'slot']),
        ]);
    }

    private function authorizeStore(Request $request, Store $store): void
    {
        $this->storeAccess->authorize($request->user(), $store);
    }
}
