<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\Waitlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WaitlistController extends Controller
{
    private const TRANSITIONS = [
        'WAITING' => ['CALLED', 'CANCELLED', 'EXPIRED'],
        'CALLED' => ['SEATED', 'CANCELLED', 'EXPIRED'],
    ];

    public function store(Request $request, Store $store): JsonResponse
    {
        abort_unless($store->is_active, 404);
        $validated = $request->validate(['guest_count' => ['required', 'integer', 'between:1,20']]);

        $waitlist = DB::transaction(function () use ($request, $store, $validated) {
            Store::query()->lockForUpdate()->findOrFail($store->id);
            $duplicate = Waitlist::query()->where('store_id', $store->id)
                ->where('user_id', $request->user()->id)->whereIn('status', ['WAITING', 'CALLED'])->exists();
            abort_if($duplicate, 422, '이미 진행 중인 대기 신청이 있습니다.');

            $queueNumber = ((int) Waitlist::query()->where('store_id', $store->id)
                ->whereDate('queued_on', today())->max('queue_number')) + 1;
            $waitingAhead = Waitlist::query()->where('store_id', $store->id)
                ->whereDate('queued_on', today())->where('status', 'WAITING')->count();

            return Waitlist::create([
                'store_id' => $store->id,
                'user_id' => $request->user()->id,
                'queued_on' => today(),
                'queue_number' => $queueNumber,
                'guest_count' => $validated['guest_count'],
                'estimated_wait_minutes' => $waitingAhead * 10,
                'status' => 'WAITING',
            ]);
        });

        return response()->json([
            'message' => '대기 신청이 완료되었습니다.',
            'waitlist' => $waitlist->load('store:id,name,slug'),
        ], 201);
    }

    public function mine(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['WAITING', 'CALLED', 'SEATED', 'CANCELLED', 'EXPIRED'])],
        ]);
        $waitlists = Waitlist::query()->with('store:id,name,slug,address,latitude,longitude')
            ->where('user_id', $request->user()->id)
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()->paginate(20);

        return response()->json($waitlists);
    }

    public function cancelMine(Request $request, Waitlist $waitlist): JsonResponse
    {
        abort_unless($waitlist->user_id === $request->user()->id, 404);
        $cancelled = DB::transaction(function () use ($waitlist) {
            $locked = Waitlist::query()->lockForUpdate()->findOrFail($waitlist->id);
            abort_unless(in_array($locked->status, ['WAITING', 'CALLED'], true), 422, '취소할 수 없는 대기 상태입니다.');
            $locked->forceFill(['status' => 'CANCELLED', 'cancelled_at' => now()])->save();

            return $locked;
        });

        return response()->json(['message' => '대기 신청이 취소되었습니다.', 'waitlist' => $cancelled]);
    }

    public function ownerIndex(Request $request, Store $store): JsonResponse
    {
        $this->authorizeStore($request, $store);
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::in(['WAITING', 'CALLED', 'SEATED', 'CANCELLED', 'EXPIRED'])],
        ]);
        $waitlists = Waitlist::query()->with('user:id,name,phone')->where('store_id', $store->id)
            ->whereDate('queued_on', $validated['date'] ?? today())
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('queue_number')->paginate(50);

        return response()->json($waitlists);
    }

    public function updateStatus(Request $request, Waitlist $waitlist): JsonResponse
    {
        $waitlist->loadMissing('store');
        $this->authorizeStore($request, $waitlist->store);
        $validated = $request->validate([
            'status' => ['required', Rule::in(['CALLED', 'SEATED', 'CANCELLED', 'EXPIRED'])],
        ]);
        $updated = DB::transaction(function () use ($waitlist, $validated) {
            $locked = Waitlist::query()->lockForUpdate()->findOrFail($waitlist->id);
            abort_unless(in_array($validated['status'], self::TRANSITIONS[$locked->status] ?? [], true), 422, '변경할 수 없는 대기 상태입니다.');
            $timestampColumn = match ($validated['status']) {
                'CALLED' => 'called_at',
                'SEATED' => 'seated_at',
                'CANCELLED' => 'cancelled_at',
                'EXPIRED' => 'expired_at',
            };
            $locked->forceFill(['status' => $validated['status'], $timestampColumn => now()])->save();

            return $locked;
        });

        return response()->json([
            'message' => '대기 상태가 변경되었습니다.',
            'waitlist' => $updated->load(['store:id,name,slug', 'user:id,name,phone']),
        ]);
    }

    private function authorizeStore(Request $request, Store $store): void
    {
        $user = $request->user();
        $isAdmin = strtoupper((string) $user->role) === 'ADMIN';
        $isManager = $store->members()->where('user_id', $user->id)->where('is_active', true)
            ->whereIn('role', ['OWNER', 'MANAGER'])->exists();
        abort_unless($isAdmin || $isManager, 403, '매장의 대기열을 관리할 권한이 없습니다.');
    }
}
