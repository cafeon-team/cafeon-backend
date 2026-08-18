<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthLoginSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_normalizes_email_before_authentication(): void
    {
        User::factory()->create(['email' => 'user@cafeon.test', 'password' => 'password1234']);

        $this->postJson('/api/auth/login', [
            'email' => '  USER@CAFEON.TEST  ',
            'password' => 'password1234',
        ])->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.email', 'user@cafeon.test');
    }

    public function test_login_rejects_password_longer_than_128_characters(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'user@cafeon.test',
            'password' => str_repeat('a', 129),
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_login_is_limited_to_five_requests_per_minute_per_ip(): void
    {
        $client = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.77']);

        foreach (range(1, 5) as $_) {
            $client->postJson('/api/auth/login', [
                'email' => 'missing@cafeon.test',
                'password' => 'wrong-password',
            ])->assertUnprocessable();
        }

        $client->postJson('/api/auth/login', [
            'email' => 'missing@cafeon.test',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }
}
