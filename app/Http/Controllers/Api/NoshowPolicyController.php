<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreNoshowPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NoshowPolicyController extends Controller
{
    public function show(Store $store): JsonResponse
    {
        abort_unless($store->is_active, 404);

        return response()->json([
            'store_id' => $store->id,
            'policy' => $store->noshowPolicy ?? $this->defaults($store),
        ]);
    }

    public function update(Request $request, Store $store): JsonResponse
    {
        $this->authorizeStore($request, $store);

        $validated = $request->validate([
            'deposit_required' => ['required', 'boolean'],
            'deposit_amount' => ['required_if:deposit_required,true', 'numeric', 'min:0', 'max:1000000'],
            'free_cancellation_minutes' => ['required', 'integer', 'min:0', 'max:10080'],
            'penalty_type' => ['required', Rule::in(['NONE', 'POINT', 'RESERVATION_BLOCK'])],
            'penalty_point_amount' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'reservation_block_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (! $validated['deposit_required']) {
            $validated['deposit_amount'] = 0;
        }
        if ($validated['penalty_type'] !== 'POINT') {
            $validated['penalty_point_amount'] = 0;
        }
        if ($validated['penalty_type'] !== 'RESERVATION_BLOCK') {
            $validated['reservation_block_days'] = 0;
        }

        $policy = StoreNoshowPolicy::updateOrCreate(
            ['store_id' => $store->id],
            $validated + [
                'created_by' => $store->noshowPolicy?->created_by ?? $request->user()->id,
                'updated_by' => $request->user()->id,
            ],
        );

        return response()->json([
            'message' => '노쇼 정책이 저장되었습니다.',
            'policy' => $policy,
        ]);
    }

    private function defaults(Store $store): array
    {
        return [
            'store_id' => $store->id,
            'deposit_required' => false,
            'deposit_amount' => '0.00',
            'free_cancellation_minutes' => 60,
            'penalty_type' => 'NONE',
            'penalty_point_amount' => 0,
            'reservation_block_days' => 0,
            'is_active' => true,
        ];
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

        abort_unless($isAdmin || $isManager, 403, '이 매장의 노쇼 정책 관리 권한이 없습니다.');
    }
}
