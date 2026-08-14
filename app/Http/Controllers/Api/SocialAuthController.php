<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class SocialAuthController extends Controller
{
    private const PROVIDERS = ['google', 'kakao', 'naver'];

    public function redirect(string $provider): RedirectResponse
    {
        $this->ensureSupported($provider);
        abort_unless(filled(config("services.{$provider}.client_id")), 503, "{$provider} OAuth credentials are not configured.");

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        $this->ensureSupported($provider);

        try {
            $socialUser = Socialite::driver($provider)->user();
            $user = $this->resolveUser($provider, $socialUser);
            abort_if(! $user->is_active, 403, '비활성화된 계정입니다.');

            $user->forceFill(['last_login_at' => now()])->save();
            $code = Str::random(64);
            Cache::put($this->cacheKey($code), $user->getKey(), now()->addMinutes(5));

            return redirect()->away($this->callbackUrl(['code' => $code, 'provider' => $provider]));
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->away($this->callbackUrl([
                'error' => 'social_login_failed',
                'message' => '소셜 로그인 처리에 실패했습니다.',
            ]));
        }
    }

    public function exchange(Request $request): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'size:64']]);
        $userId = Cache::pull($this->cacheKey($validated['code']));

        if (! $userId || ! $user = User::find($userId)) {
            return response()->json(['message' => '만료되었거나 이미 사용한 로그인 코드입니다.'], 401);
        }
        if (! $user->is_active) {
            return response()->json(['message' => '비활성화된 계정입니다.'], 403);
        }

        return response()->json([
            'token' => $user->createToken('social-login')->plainTextToken,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    private function resolveUser(string $provider, SocialiteUser $socialUser): User
    {
        return DB::transaction(function () use ($provider, $socialUser): User {
            $providerId = (string) $socialUser->getId();
            $account = SocialAccount::query()
                ->where('provider', $provider)
                ->where('provider_user_id', $providerId)
                ->first();

            if ($account) {
                return $account->user;
            }

            $providerEmail = $socialUser->getEmail();
            $user = $providerEmail ? User::where('email', $providerEmail)->first() : null;

            if (! $user) {
                $user = User::create([
                    'name' => $socialUser->getName() ?: $socialUser->getNickname() ?: "{$provider} 사용자",
                    'email' => $providerEmail ?: "{$provider}.{$providerId}@social.cafeon.local",
                    'password' => Str::random(64),
                    'profile_image_url' => $socialUser->getAvatar(),
                    'role' => 'CUSTOMER',
                    'is_active' => true,
                    'email_verified_at' => $providerEmail ? now() : null,
                ]);
            }

            $user->socialAccounts()->create([
                'provider' => $provider,
                'provider_user_id' => $providerId,
                'provider_email' => $providerEmail,
            ]);

            return $user;
        });
    }

    private function ensureSupported(string $provider): void
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);
    }

    private function cacheKey(string $code): string
    {
        return 'social-login:'.hash('sha256', $code);
    }

    private function callbackUrl(array $parameters): string
    {
        $url = config('services.social_login.frontend_callback');
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.http_build_query($parameters);
    }
}
