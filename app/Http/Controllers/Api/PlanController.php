<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
use App\Models\UserSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Plan::where('is_active', true)->orderBy('monthly_price')->get()]);
    }

    public function mine(Request $request): JsonResponse
    {
        $subscription = UserSubscription::with(['plan', 'payments' => fn ($q) => $q->latest()])->where('user_id', $request->user()->id)->latest()->first();

        return response()->json(['subscription' => $subscription, 'effective_plan' => $subscription?->status === 'ACTIVE' ? $subscription->plan : Plan::where('code', 'BASIC')->first()]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate(['plan_id' => ['required', 'integer', 'exists:plans,id'], 'billing_cycle' => ['required', Rule::in(['MONTHLY', 'YEARLY'])]]);
        $plan = Plan::where('is_active', true)->findOrFail($data['plan_id']);
        abort_if($plan->code === 'BASIC', 422, 'Basic 요금제는 다운그레이드 API를 사용하세요.');
        $result = DB::transaction(function () use ($request, $data, $plan) {
            UserSubscription::where('user_id', $request->user()->id)->where('status', 'PENDING_PAYMENT')->update(['status' => 'CANCELLED', 'cancelled_at' => now()]);
            $subscription = UserSubscription::create(['user_id' => $request->user()->id, 'plan_id' => $plan->id, 'billing_cycle' => $data['billing_cycle'], 'status' => 'PENDING_PAYMENT']);
            $payment = $subscription->payments()->create(['amount' => $data['billing_cycle'] === 'YEARLY' ? $plan->yearly_price : $plan->monthly_price, 'status' => 'PENDING']);

            return [$subscription->load('plan'), $payment];
        });

        return response()->json(['message' => '구독 결제 대기가 생성되었습니다.', 'subscription' => $result[0], 'payment' => $result[1]], 201);
    }

    public function activate(Request $request, UserSubscription $subscription): JsonResponse
    {
        abort_unless(strtoupper((string) $request->user()->role) === 'ADMIN', 403);
        $data = $request->validate(['provider_transaction_id' => ['required', 'string', 'max:150', 'unique:subscription_payments,provider_transaction_id']]);
        DB::transaction(function () use ($subscription, $data) {
            $locked = UserSubscription::lockForUpdate()->findOrFail($subscription->id);
            abort_unless($locked->status === 'PENDING_PAYMENT', 422);
            UserSubscription::where('user_id', $locked->user_id)->where('status', 'ACTIVE')->update(['status' => 'CANCELLED', 'cancelled_at' => now()]);
            $end = $locked->billing_cycle === 'YEARLY' ? now()->addYear() : now()->addMonth();
            $locked->update(['status' => 'ACTIVE', 'starts_at' => now(), 'ends_at' => $end]);
            $locked->payments()->where('status', 'PENDING')->latest()->firstOrFail()->update(['status' => 'PAID', 'paid_at' => now(), 'provider_transaction_id' => $data['provider_transaction_id']]);
        });

        return response()->json(['message' => '구독이 활성화되었습니다.', 'subscription' => $subscription->fresh()->load(['plan', 'payments'])]);
    }

    public function downgrade(Request $request): JsonResponse
    {
        $count = UserSubscription::where('user_id', $request->user()->id)->whereIn('status', ['ACTIVE', 'PENDING_PAYMENT'])->update(['status' => 'CANCELLED', 'cancelled_at' => now()]);

        return response()->json(['message' => 'Basic 요금제로 변경되었습니다.', 'cancelled_count' => $count, 'effective_plan' => Plan::where('code', 'BASIC')->first()]);
    }

    public function billingHistory(Request $request): JsonResponse
    {
        $rows = SubscriptionPayment::with('subscription.plan')->whereHas('subscription', fn ($q) => $q->where('user_id', $request->user()->id))->latest()->paginate(30);

        return response()->json($rows);
    }
}
