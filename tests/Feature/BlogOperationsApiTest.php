<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Store;
use App\Models\User;
use App\Services\ImageStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BlogOperationsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_posts_are_published_by_command(): void
    {
        $user = User::factory()->create();
        $store = Store::create(['name' => 'Cafe', 'slug' => 'cafe', 'address' => 'Seoul', 'is_active' => true]);
        $category = PostCategory::create(['store_id' => $store->id, 'name' => 'Notice', 'slug' => 'notice']);
        $post = Post::create([
            'store_id' => $store->id, 'category_id' => $category->id, 'author_id' => $user->id,
            'title' => 'Scheduled', 'slug' => 'scheduled', 'content' => 'Body',
            'status' => 'SCHEDULED', 'scheduled_at' => now()->subMinute(),
        ]);

        $this->artisan('posts:publish-scheduled')->assertSuccessful();

        $this->assertDatabaseHas('posts', ['id' => $post->id, 'status' => 'PUBLISHED']);
        $this->assertNotNull($post->fresh()->published_at);
    }

    public function test_authenticated_user_can_upload_blog_image(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create());

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $response = $this->postJson('/api/uploads/images', [
            'image' => UploadedFile::fake()->createWithContent('cake.png', $png),
        ])->assertCreated()->assertJsonStructure(['path', 'url']);

        Storage::disk('public')->assertExists($response->json('path'));
    }

    public function test_image_cleanup_only_deletes_local_blog_files(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('blog/remove.png', 'image');
        Storage::disk('public')->put('other/keep.png', 'image');

        app(ImageStorageService::class)->deleteLocalUrls([
            'http://127.0.0.1:8000/storage/blog/remove.png',
            'http://example.com/image.png',
            'http://127.0.0.1:8000/storage/other/keep.png',
        ]);

        Storage::disk('public')->assertMissing('blog/remove.png');
        Storage::disk('public')->assertExists('other/keep.png');
    }
}
