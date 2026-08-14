<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function registerOwner(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:50'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers()],
            'phone' => ['required', 'string', 'max:30'],
            'store_name' => ['required', 'string', 'min:2', 'max:100'],
            'store_address' => ['nullable', 'string', 'max:255'],
            'store_detail_address' => ['nullable', 'string', 'max:255'],
            'store_phone' => ['nullable', 'string', 'max:30'],
            'terms_accepted' => ['accepted'],
        ]);

        [$user, $store, $membership] = DB::transaction(function () use ($validated): array {
            $user = User::create([
                'name' => $validated['name'],
                'email' => strtolower($validated['email']),
                'password' => $validated['password'],
                'phone' => $validated['phone'],
                'role' => 'OWNER',
                'is_active' => true,
            ]);

            $store = Store::create([
                'name' => $validated['store_name'],
                'slug' => $this->uniqueStoreSlug($validated['store_name']),
                'address' => $validated['store_address'] ?? null,
                'detail_address' => $validated['store_detail_address'] ?? null,
                'phone' => $validated['store_phone'] ?? null,
                'reservation_enabled' => true,
                'is_active' => true,
            ]);

            $membership = $store->members()->create([
                'user_id' => $user->id,
                'role' => 'OWNER',
                'is_active' => true,
            ]);

            return [$user, $store, $membership];
        });

        return response()->json([
            'message' => '사장님 회원가입이 완료되었습니다.',
            'token' => $user->createToken('owner-frontend')->plainTextToken,
            'token_type' => 'Bearer',
            'user' => $user,
            'store' => $store,
            'membership' => $membership,
        ], 201);
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:50'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers()],
            'terms_accepted' => ['accepted'],
        ], [
            'email.unique' => '이미 가입된 이메일입니다.',
            'password.min' => '비밀번호는 8자 이상이어야 합니다.',
            'terms_accepted.accepted' => '이용약관에 동의해 주세요.',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'password' => $validated['password'],
            'role' => 'CUSTOMER',
            'is_active' => true,
        ]);

        $token = $user->createToken('frontend')->plainTextToken;

        return response()->json([
            'message' => '회원가입이 완료되었습니다.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'This account is inactive.'], 403);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $token = $user->createToken('frontend')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    private function uniqueStoreSlug(string $storeName): string
    {
        $base = Str::slug($storeName) ?: 'store';
        $slug = $base;

        while (Store::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(6));
        }

        return $slug;
    }
}
