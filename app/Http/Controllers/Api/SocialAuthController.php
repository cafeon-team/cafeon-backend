<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\Store;
use App\Models\User;
use App\Services\OwnerProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

class SocialAuthController extends Controller
{
    private const PROVIDERS = ['google', 'kakao', 'naver'];

    private const ROLES = ['CUSTOMER', 'OWNER'];

    public function __construct(private readonly OwnerProfileService $ownerProfiles) {}

    public function redirect(Request $request, string $provider): RedirectResponse
    {
        $this->ensureSupported($provider);
        $this->ensureConfigured($provider);

        $role = $this->requestedRole($request);

        if ($provider === 'google' && ! app()->environment('testing')) {
            $state = Str::random(64);
            Cache::put($this->stateCacheKey($provider, $state), $role, now()->addMinutes(10));

            return Socialite::driver($provider)
                ->stateless()
                ->with(['state' => $state])
                ->redirect();
        }

        $request->session()->put($this->roleSessionKey($provider), $role);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $this->ensureSupported($provider);
        if ($provider === 'google' && ! app()->environment('testing')) {
            $state = (string) $request->query('state');
            $role = $state !== ''
                ? Cache::pull($this->stateCacheKey($provider, $state))
                : null;

            if (! $role) {
                throw new InvalidStateException;
            }
        } else {
            $role = $request->session()->pull($this->roleSessionKey($provider), 'CUSTOMER');
        }

        try {
            $driver = Socialite::driver($provider);
            $socialUser = $provider === 'google' && ! app()->environment('testing')
                ? $driver->stateless()->user()
                : $driver->user();
            $user = $this->resolveUser($provider, $socialUser, $role);
            abort_if(! $user->is_active, 403, '비활성화된 계정입니다.');

            $user = $this->normalizeLegacyOwnerRole($user, $role);
            $this->ensureMatchingRole($user, $role);
            if ($role === 'OWNER') {
                $this->ensureOwnerStore($user);
            }

            $user->forceFill(['last_login_at' => now()])->save();
            $code = Str::random(64);
            Cache::put($this->cacheKey($code), $user->getKey(), now()->addMinutes(5));

            return redirect()->away($this->callbackUrl($role, [
                'code' => $code,
                'provider' => $provider,
                'role' => strtolower($role),
            ]));
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->away($this->callbackUrl($role, [
                'error' => 'social_login_failed',
                'message' => '소셜 로그인 처리에 실패했습니다.',
                'role' => strtolower($role),
            ]));
        }
    }

    public function exchange(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:64'],
            'role' => ['sometimes', 'string', 'in:customer,owner,CUSTOMER,OWNER'],
        ]);
        $userId = Cache::pull($this->cacheKey($validated['code']));

        if (! $userId || ! $user = User::find($userId)) {
            return response()->json(['message' => '만료되었거나 이미 사용한 로그인 코드입니다.'], 401);
        }
        if (! $user->is_active) {
            return response()->json(['message' => '비활성화된 계정입니다.'], 403);
        }
        if (strtoupper((string) $user->role) === 'OWNER') {
            $user = $this->normalizeLegacyOwnerRole($user, 'OWNER');
        }
        if (isset($validated['role'])) {
            $this->ensureMatchingRole($user, strtoupper($validated['role']));
        }
        if (strtoupper((string) $user->role) === 'ADMIN') {
            $this->ensureOwnerStore($user);
        }

        $response = [
            'token' => $user->createToken('social-login')->plainTextToken,
            'token_type' => 'Bearer',
            'user' => $user,
        ];

        if (strtoupper((string) $user->role) === 'ADMIN') {
            $response = array_merge($response, $this->ownerProfiles->payload($user));
        }

        return response()->json($response);
    }

    private function resolveUser(string $provider, SocialiteUser $socialUser, string $role): User
    {
        return DB::transaction(function () use ($provider, $socialUser, $role): User {
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

            if ($user) {
                $user = $this->normalizeLegacyOwnerRole($user, $role);
                $this->ensureMatchingRole($user, $role);
            }

            if (! $user) {
                $user = User::create([
                    'name' => $socialUser->getName() ?: $socialUser->getNickname() ?: "{$provider} 사용자",
                    'email' => $providerEmail ?: "{$provider}.{$providerId}@social.cafeon.local",
                    'password' => Str::random(64),
                    'profile_image_url' => $socialUser->getAvatar(),
                    'role' => $this->accountRole($role),
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

    private function ensureConfigured(string $provider): void
    {
        $missing = collect(['client_id', 'client_secret', 'redirect'])
            ->reject(fn (string $key): bool => filled(config("services.{$provider}.{$key}")))
            ->values();

        abort_if(
            $missing->isNotEmpty(),
            503,
            sprintf(
                '%s OAuth 설정이 누락되었습니다: %s',
                ucfirst($provider),
                $missing->implode(', ')
            )
        );
    }

    private function ensureMatchingRole(User $user, string $role): void
    {
        $expectedRole = $this->accountRole($role);
        abort_if(
            strtoupper((string) $user->role) !== $expectedRole,
            403,
            $role === 'OWNER'
                ? '손님 계정은 사장님 로그인으로 사용할 수 없습니다.'
                : '사장님 계정은 손님 로그인으로 사용할 수 없습니다.'
        );
    }

    private function cacheKey(string $code): string
    {
        return 'social-login:'.hash('sha256', $code);
    }

    private function requestedRole(Request $request): string
    {
        $role = strtoupper((string) $request->query('role', 'CUSTOMER'));
        abort_unless(in_array($role, self::ROLES, true), 422, 'role은 customer 또는 owner여야 합니다.');

        return $role;
    }

    private function roleSessionKey(string $provider): string
    {
        return "social-login.{$provider}.role";
    }

    private function stateCacheKey(string $provider, string $state): string
    {
        return 'social-login-state:'.$provider.':'.hash('sha256', $state);
    }

    private function callbackUrl(string $role, array $parameters): string
    {
        $url = config('services.social_login.frontend_callbacks.'.strtolower($role))
            ?: config('services.social_login.frontend_callback');
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.http_build_query($parameters);
    }

    private function accountRole(string $loginRole): string
    {
        return strtoupper($loginRole) === 'OWNER' ? 'ADMIN' : 'CUSTOMER';
    }

    private function normalizeLegacyOwnerRole(User $user, string $loginRole): User
    {
        if ($loginRole === 'OWNER' && strtoupper((string) $user->role) === 'OWNER') {
            $user->forceFill(['role' => 'ADMIN'])->save();
        }

        return $user;
    }

    private function ensureOwnerStore(User $user): void
    {
        if ($user->storeMemberships()->where('role', 'OWNER')->where('is_active', true)->exists()) {
            return;
        }

        DB::transaction(function () use ($user): void {
            if ($user->storeMemberships()->where('role', 'OWNER')->where('is_active', true)->lockForUpdate()->exists()) {
                return;
            }

            $storeName = trim($user->name).'님의 카페';
            $store = Store::create([
                'name' => $storeName,
                'slug' => $this->uniqueStoreSlug($storeName),
                'reservation_enabled' => true,
                'is_active' => true,
            ]);
            $store->members()->create([
                'user_id' => $user->id,
                'role' => 'OWNER',
                'is_active' => true,
            ]);
        });
    }

    private function uniqueStoreSlug(string $storeName): string
    {
        $base = Str::slug($storeName) ?: 'social-owner-store';
        $slug = $base;

        while (Store::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(6));
        }

        return $slug;
    }
}
