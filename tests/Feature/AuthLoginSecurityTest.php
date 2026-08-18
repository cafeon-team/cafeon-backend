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

    public function test_customer_cannot_login_through_owner_endpoint(): void
    {
        User::factory()->create([
            'email' => 'customer@cafeon.test',
            'password' => 'password1234',
            'role' => 'CUSTOMER',
        ]);

        $this->postJson('/api/auth/owner/login', [
                'email' => 'customer@cafeon.test',
                'password' => 'password1234',
            ])->assertForbidden()
            ->assertJsonPath('message', '손님 계정은 사장님 화면에서 로그인할 수 없습니다.');
    }

    public function test_admin_cannot_login_from_customer_portal(): void
    {
        User::factory()->create([
            'email' => 'owner@cafeon.test',
            'password' => 'password1234',
            'role' => 'ADMIN',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'owner@cafeon.test',
            'password' => 'password1234',
        ])->assertForbidden()
            ->assertJsonPath('message', '사장님 계정은 손님 화면에서 로그인할 수 없습니다.');
    }

    public function test_role_specific_login_endpoints_enforce_account_type(): void
    {
        User::factory()->create([
            'email' => 'owner@cafeon.test',
            'password' => 'password1234',
            'role' => 'ADMIN',
        ]);

        $this->postJson('/api/auth/owner/login', [
            'email' => 'owner@cafeon.test',
            'password' => 'password1234',
        ])->assertOk()->assertJsonPath('user.role', 'ADMIN');

        $this->postJson('/api/auth/customer/login', [
            'email' => 'owner@cafeon.test',
            'password' => 'password1234',
        ])->assertForbidden();
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
