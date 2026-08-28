<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateProfileThumbnail;
use App\Models\UploadedImage;
use App\Services\OwnerProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function __construct(private readonly OwnerProfileService $ownerProfiles) {}

    public function owner(Request $request): JsonResponse
    {
        $this->ensureOwnerAccount($request);

        return response()->json($this->ownerProfiles->payload($request->user()));
    }

    public function ownerStores(Request $request): JsonResponse
    {
        $this->ensureOwnerAccount($request);
        $payload = $this->ownerProfiles->payload($request->user());

        return response()->json([
            'data' => $payload['stores'],
            ...$payload,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->normalizeProfileImageInput($request);
        $validated = $this->validateProfile($request);
        $profileImagePath = null;

        if ($profileImage = $this->profileImageFile($request)) {
            [$validated['profile_image_url'], $profileImagePath] = $this->storeProfileImage($request, $profileImage);
            $validated['profile_thumbnail_url'] = null;
        } elseif (array_key_exists('profile_image_url', $validated) && $validated['profile_image_url']) {
            $profileImagePath = $this->localProfileImagePath($validated['profile_image_url']);
            if ($profileImagePath && $validated['profile_image_url'] !== $user->profile_image_url) {
                $validated['profile_thumbnail_url'] = null;
            }
        }

        $user->fill($validated)->save();

        if (isset($profileImagePath)) {
            GenerateProfileThumbnail::dispatch($user->id, $profileImagePath, $validated['profile_image_url']);
        }

        $response = ['message' => '프로필이 수정되었습니다.', 'user' => $user->fresh()];
        if (strtoupper((string) $user->role) === 'ADMIN') {
            $ownerPayload = $this->ownerProfiles->payload($user);
            unset($ownerPayload['user']);
            $response = array_merge($response, $ownerPayload);
        }

        return response()->json($response);
    }

    public function updateOwner(Request $request): JsonResponse
    {
        $this->ensureOwnerAccount($request);
        $request->user()->fill($this->validateProfile($request))->save();

        return response()->json([
            'message' => '사장님 프로필이 저장되었습니다.',
            ...$this->ownerProfiles->payload($request->user()),
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $request->user()->forceFill(['password' => Hash::make($validated['password'])])->save();
        $request->user()->tokens()->delete();

        return response()->json(['message' => '비밀번호가 변경되었습니다. 다시 로그인해 주세요.']);
    }

    private function validateProfile(Request $request): array
    {
        return $request->validate([
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:50'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($request->user()->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'profile_image_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'profile_image' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'profileImage' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'avatar' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'image' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'birth_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);
    }

    private function normalizeProfileImageInput(Request $request): void
    {
        if ($request->exists('profile_image_url')) {
            return;
        }

        foreach (['profileImageUrl', 'avatar_url', 'avatarUrl'] as $alias) {
            if ($request->exists($alias)) {
                $request->merge(['profile_image_url' => $request->input($alias)]);

                return;
            }
        }
    }

    private function profileImageFile(Request $request): mixed
    {
        foreach (['profile_image', 'profileImage', 'avatar', 'image'] as $field) {
            if ($request->hasFile($field)) {
                return $request->file($field);
            }
        }

        return null;
    }

    private function storeProfileImage(Request $request, mixed $image): array
    {
        $path = $image->store('profiles', 'public');

        UploadedImage::create([
            'user_id' => $request->user()->id,
            'disk' => 'public',
            'path' => $path,
            'mime_type' => $image->getMimeType(),
            'size' => $image->getSize(),
        ]);

        $url = Storage::disk('public')->url($path);

        return [
            Str::startsWith($url, ['http://', 'https://']) ? $url : url($url),
            $path,
        ];
    }

    private function localProfileImagePath(string $url): ?string
    {
        $urlPath = parse_url($url, PHP_URL_PATH) ?: $url;
        if (! Str::contains($urlPath, '/storage/')) {
            return null;
        }

        $path = ltrim(Str::after($urlPath, '/storage/'), '/');

        return Str::startsWith($path, ['blog/', 'profiles/'])
            && Storage::disk('public')->exists($path)
                ? $path
                : null;
    }

    private function ensureOwnerAccount(Request $request): void
    {
        abort_unless(
            strtoupper((string) $request->user()->role) === 'ADMIN',
            403,
            '사장님 계정만 접근할 수 있습니다.',
        );
    }
}
