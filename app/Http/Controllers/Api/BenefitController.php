<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerStoreAccount;
use App\Models\UserCoupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BenefitController extends Controller
{
    public function coupons(Request $request): JsonResponse
    {
        $coupons = UserCoupon::query()
            ->with('coupon.store:id,name,slug')
            ->where('user_id', $request->user()->id)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderBy('expires_at')
            ->paginate(20);

        return response()->json($coupons);
    }

    public function membership(Request $request): JsonResponse
    {
        $accounts = CustomerStoreAccount::query()
            ->with(['store:id,name,slug,thumbnail_url', 'pointTransactions' => fn ($query) => $query->latest()->limit(20)])
            ->where('user_id', $request->user()->id)
            ->when($request->filled('store_id'), fn ($query) => $query->where('store_id', $request->integer('store_id')))
            ->get();

        return response()->json([
            'total_points' => $accounts->sum('point_balance'),
            'accounts' => $accounts,
        ]);
    }
}
