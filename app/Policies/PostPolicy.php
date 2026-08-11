<?php

namespace App\Policies;

use App\Models\Store;
use App\Models\User;

class PostPolicy
{
    public function createForStore(User $user, Store $store): bool
    {
        return $this->manageBlog($user, $store);
    }

    public function update(User $user, \App\Models\Post $post): bool
    {
        return $this->manageBlog($user, $post->store);
    }

    public function delete(User $user, \App\Models\Post $post): bool
    {
        return $this->manageBlog($user, $post->store);
    }

    public function manageBlog(User $user, Store $store): bool
    {
        if (strtoupper((string) $user->role) === 'ADMIN') {
            return true;
        }

        return $store->members()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereIn('role', ['OWNER', 'MANAGER'])
            ->exists();
    }
}
