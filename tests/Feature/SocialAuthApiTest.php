<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class SocialAuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_callback_creates_account_and_exchanges_one_time_code(): void
    {
        $this->assertSocialLoginCreatesAccount('google', 'google-123', 'social@example.com', 'CUSTOMER');
    }

    public function test_naver_callback_creates_account_and_exchanges_one_time_code(): void
    {
        $this->assertSocialLoginCreatesAccount('naver', 'naver-123', 'naver@example.com', 'CUSTOMER');
    }

    public function test_kakao_callback_flow_is_ready_before_credentials_are_added(): void
    {
        $this->assertSocialLoginCreatesAccount('kakao', 'kakao-123', 'kakao@example.com', 'CUSTOMER');
    }

    public function test_owner_social_login_creates_owner_and_uses_owner_callback(): void
    {
        $this->configureProvider('google');

        $this->get('/auth/social/google/redirect?role=owner')->assertRedirect();

        config(['services.social_login.frontend_callbacks.owner' => 'http://localhost/owner/login/callback']);
        Socialite::fake('google', (new SocialiteUser)->map([
            'id' => 'owner-google-123',
            'name' => 'CafeOn Owner',
            'email' => 'owner-social@example.com',
        ]));

        $callback = $this->get('/auth/social/google/callback');
        $callback->assertRedirectContains('http://localhost/owner/login/callback');
        parse_str(parse_url($callback->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->assertSame('owner', $query['role']);
        $this->assertDatabaseHas('users', ['email' => 'owner-social@example.com', 'role' => 'OWNER']);
    }

    public function test_social_login_rejects_unknown_role(): void
    {
        $this->configureProvider('google');

        $this->get('/auth/social/google/redirect?role=admin')->assertUnprocessable();
    }

    public function test_customer_account_cannot_log_in_through_owner_flow(): void
    {
        $this->configureProvider('google');
        $user = User::factory()->create(['email' => 'existing@example.com', 'role' => 'CUSTOMER']);
        $user->socialAccounts()->create([
            'provider' => 'google',
            'provider_user_id' => 'existing-google-123',
            'provider_email' => $user->email,
        ]);

        $this->get('/auth/social/google/redirect?role=owner')->assertRedirect();
        Socialite::fake('google', (new SocialiteUser)->map([
            'id' => 'existing-google-123',
            'name' => 'Existing Customer',
            'email' => $user->email,
        ]));

        $callback = $this->get('/auth/social/google/callback');
        parse_str(parse_url($callback->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->assertSame('social_login_failed', $query['error']);
        $this->assertSame('owner', $query['role']);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'CUSTOMER']);
    }

    public function test_existing_customer_email_is_not_linked_during_owner_flow(): void
    {
        $this->configureProvider('google');
        $user = User::factory()->create(['email' => 'same-email@example.com', 'role' => 'CUSTOMER']);

        $this->get('/auth/social/google/redirect?role=owner')->assertRedirect();
        Socialite::fake('google', (new SocialiteUser)->map([
            'id' => 'new-provider-id',
            'name' => 'Existing Customer',
            'email' => $user->email,
        ]));

        $this->get('/auth/social/google/callback')->assertRedirect();

        $this->assertDatabaseMissing('social_accounts', [
            'provider' => 'google',
            'provider_user_id' => 'new-provider-id',
        ]);
    }

    public function test_naver_redirect_reports_missing_credentials_until_keys_are_added(): void
    {
        config(['services.naver.client_id' => null]);

        $this->get('/auth/social/naver/redirect')->assertServiceUnavailable();
    }

    public function test_redirect_reports_incomplete_provider_configuration(): void
    {
        config([
            'services.kakao.client_id' => 'configured',
            'services.kakao.client_secret' => null,
            'services.kakao.redirect' => null,
        ]);

        $this->get('/auth/social/kakao/redirect')
            ->assertServiceUnavailable();
    }

    public function test_local_social_login_view_is_available(): void
    {
        $this->get('/test/social-login')
            ->assertOk()
            ->assertSee('카카오로 로그인')
            ->assertSee('네이버로 로그인')
            ->assertSee('Google로 로그인');
    }

    private function assertSocialLoginCreatesAccount(string $provider, string $providerId, string $email, string $role): void
    {
        config(['services.social_login.frontend_callback' => 'http://localhost/test/social-login/callback']);
        Socialite::fake($provider, (new SocialiteUser)->map([
            'id' => $providerId,
            'name' => 'CafeOn User',
            'email' => $email,
            'avatar' => 'https://example.com/avatar.png',
        ]));

        $callback = $this->get("/auth/social/{$provider}/callback");
        $callback->assertRedirect();
        parse_str(parse_url($callback->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertArrayHasKey('code', $query);
        $this->assertSame(strtolower($role), $query['role']);

        $this->postJson('/api/auth/social/exchange', ['code' => $query['code']])
            ->assertOk()
            ->assertJsonPath('user.email', $email)
            ->assertJsonPath('user.role', $role)
            ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'name', 'email']]);

        $this->postJson('/api/auth/social/exchange', ['code' => $query['code']])->assertUnauthorized();
        $this->assertDatabaseHas('social_accounts', [
            'provider' => $provider,
            'provider_user_id' => $providerId,
            'provider_email' => $email,
        ]);
    }

    private function configureProvider(string $provider): void
    {
        config([
            "services.{$provider}.client_id" => 'test-client-id',
            "services.{$provider}.client_secret" => 'test-client-secret',
            "services.{$provider}.redirect" => "http://localhost/auth/social/{$provider}/callback",
        ]);
    }
}
