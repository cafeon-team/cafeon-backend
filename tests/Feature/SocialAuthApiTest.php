<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class SocialAuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_callback_creates_account_and_exchanges_one_time_code(): void
    {
        $this->assertSocialLoginCreatesAccount('google', 'google-123', 'social@example.com');
    }

    public function test_naver_callback_creates_account_and_exchanges_one_time_code(): void
    {
        $this->assertSocialLoginCreatesAccount('naver', 'naver-123', 'naver@example.com');
    }

    public function test_kakao_callback_flow_is_ready_before_credentials_are_added(): void
    {
        $this->assertSocialLoginCreatesAccount('kakao', 'kakao-123', 'kakao@example.com');
    }

    public function test_naver_redirect_reports_missing_credentials_until_keys_are_added(): void
    {
        config(['services.naver.client_id' => null]);

        $this->get('/auth/social/naver/redirect')->assertServiceUnavailable();
    }

    public function test_local_social_login_view_is_available(): void
    {
        $this->get('/test/social-login')
            ->assertOk()
            ->assertSee('카카오로 로그인')
            ->assertSee('네이버로 로그인')
            ->assertSee('Google로 로그인');
    }

    private function assertSocialLoginCreatesAccount(string $provider, string $providerId, string $email): void
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

        $this->postJson('/api/auth/social/exchange', ['code' => $query['code']])
            ->assertOk()
            ->assertJsonPath('user.email', $email)
            ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'name', 'email']]);

        $this->postJson('/api/auth/social/exchange', ['code' => $query['code']])->assertUnauthorized();
        $this->assertDatabaseHas('social_accounts', [
            'provider' => $provider,
            'provider_user_id' => $providerId,
            'provider_email' => $email,
        ]);
    }
}
