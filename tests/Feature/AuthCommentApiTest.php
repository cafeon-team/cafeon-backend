<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthCommentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_and_receive_token(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'password1234',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'login@example.com',
            'password' => 'password1234',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'token_type', 'user']);
    }

    public function test_guest_cannot_write_comment(): void
    {
        [$post] = $this->createPublishedPost();

        $this->postJson("/api/posts/{$post->id}/comments", ['content' => 'Guest comment'])
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_write_comment(): void
    {
        [$post, $user] = $this->createPublishedPost();
        Sanctum::actingAs($user);

        $this->postJson("/api/posts/{$post->id}/comments", ['content' => 'Member comment'])
            ->assertCreated()
            ->assertJsonPath('content', 'Member comment');

        $this->assertDatabaseHas('comments', [
            'post_id' => $post->id,
            'user_id' => $user->id,
            'content' => 'Member comment',
        ]);
    }

    private function createPublishedPost(): array
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = Store::create([
            'name' => 'Test Store',
            'slug' => 'test-store',
            'address' => 'Test Address',
        ]);
        $category = PostCategory::create([
            'store_id' => $store->id,
            'name' => 'Notice',
            'slug' => 'notice',
        ]);
        $post = Post::create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'author_id' => $user->id,
            'title' => 'Test Post',
            'slug' => 'test-post',
            'content' => 'Test content',
            'status' => 'PUBLISHED',
            'published_at' => now(),
        ]);

        return [$post, $user];
    }
}
