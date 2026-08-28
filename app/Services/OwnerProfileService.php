<?php

namespace App\Services;

use App\Models\User;

class OwnerProfileService
{
    public function payload(User $user): array
    {
        $memberships = $user->storeMemberships()
            ->where('role', 'OWNER')
            ->where('is_active', true)
            ->with(['store' => fn ($query) => $query->with([
                'businessHours',
                'tags:id,store_id,name,slug',
            ])])
            ->orderBy('id')
            ->get();

        $stores = $memberships
            ->pluck('store')
            ->filter()
            ->each(function ($store): void {
                $store->makeVisible('business_info');
                $store->setAttribute('hours', $store->business_hours_text);
                $store->setAttribute('business_info_text', data_get($store->business_info, 'business_registration_number'));
                $store->setAttribute('tag_names', $store->tags->pluck('name')->values());
            })
            ->values();

        $membershipData = $memberships->map(fn ($membership) => [
            'id' => $membership->id,
            'store_id' => $membership->store_id,
            'user_id' => $membership->user_id,
            'role' => $membership->role,
            'is_active' => $membership->is_active,
            'created_at' => $membership->created_at,
            'updated_at' => $membership->updated_at,
        ])->values();

        return [
            'user' => $user->fresh(),
            'preferences' => $user->preference()->first(),
            'store_id' => $stores->first()?->id,
            'store' => $stores->first(),
            'membership' => $membershipData->first(),
            'stores' => $stores,
            'memberships' => $membershipData,
        ];
    }
}
