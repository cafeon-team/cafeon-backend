<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    public function test_customer_profile_image_is_saved_and_restored_after_login(): void
    {
        Storage::fake('public');
        $user = User::factory()->create([
            'email' => 'profile-image-customer@cafeon.test',
            'password' => 'password1234',
            'role' => 'CUSTOMER',
        ]);

        $response = $this->actingAs($user, 'sanctum')->post('/api/users/me/profile-image', [
            'profile_image' => UploadedFile::fake()->image('customer.jpg'),
        ])->assertOk();

        $profileImageUrl = $response->json('user.profile_image_url');
        $this->assertNotEmpty($profileImageUrl);
        $this->assertSame($profileImageUrl, $user->fresh()->profile_image_url);
        $this->assertNotEmpty($user->fresh()->profile_thumbnail_url);
        $this->assertStringEndsWith('.webp', $user->fresh()->profile_thumbnail_url);
        Storage::disk('public')->assertExists(parse_url($profileImageUrl, PHP_URL_PATH)
            ? ltrim(Str::after(parse_url($profileImageUrl, PHP_URL_PATH), '/storage/'), '/')
            : '');
        $this->assertCount(1, Storage::disk('public')->files('profile-thumbnails'));

        $this->postJson('/api/logout')->assertOk();
        $this->postJson('/api/auth/customer/login', [
            'email' => 'profile-image-customer@cafeon.test',
            'password' => 'password1234',
        ])->assertOk()->assertJsonPath('user.profile_image_url', $profileImageUrl);
    }

    public function test_customer_profile_image_larger_than_two_megabytes_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->post('/api/users/me/profile-image', [
            'profile_image' => UploadedFile::fake()->image('too-large.jpg')->size(2049),
        ])->assertUnprocessable()->assertJsonValidationErrors('profile_image');
    }

    public function test_local_uploaded_image_url_also_generates_profile_thumbnail(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $imageUrl = $this->post('/api/uploads/images', [
            'image' => UploadedFile::fake()->image('legacy-flow.jpg', 800, 600),
        ])->assertCreated()->json('url');

        $this->patchJson('/api/users/me', [
            'profile_image_url' => $imageUrl,
        ])->assertOk()->assertJsonPath('user.profile_image_url', $imageUrl);

        $this->assertNotEmpty($user->fresh()->profile_thumbnail_url);
        $this->assertCount(1, Storage::disk('public')->files('profile-thumbnails'));
    }
}
