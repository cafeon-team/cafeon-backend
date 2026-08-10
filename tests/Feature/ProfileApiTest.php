<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->create(['name' => 'Before']);

        $this->actingAs($user, 'sanctum')->putJson('/api/users/me', [
            'name' => 'After',
            'phone' => '010-1234-5678',
        ])->assertOk()->assertJsonPath('user.name', 'After');
    }

    public function test_user_can_change_password(): void
    {
        $user = User::factory()->create(['password' => 'oldPassword1']);

        $this->actingAs($user, 'sanctum')->putJson('/api/users/me/password', [
            'current_password' => 'oldPassword1',
            'password' => 'newPassword2',
            'password_confirmation' => 'newPassword2',
        ])->assertOk();

        $this->assertTrue(Hash::check('newPassword2', $user->fresh()->password));
    }
}
