<?php

namespace App\Services;

use App\Models\Store;
use App\Models\User;

class OwnerStoreAccessService
{
    public function authorize(User $user, Store $store, array $roles = ['OWNER', 'MANAGER']): void
    {
        $allowed = $store->members()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereIn('role', $roles)
            ->exists();

        abort_unless($allowed, 403, '본인 매장의 관리 권한이 없습니다.');
    }

    public function primary(User $user, array $roles = ['OWNER']): Store
    {
        $membership = $user->storeMemberships()
            ->where('is_active', true)
            ->whereIn('role', $roles)
            ->whereHas('store')
            ->with('store')
            ->oldest('id')
            ->first();

        abort_if(! $membership?->store, 404, '연결된 매장을 찾을 수 없습니다.');

        return $membership->store;
    }
}
