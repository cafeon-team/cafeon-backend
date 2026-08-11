<?php

namespace Tests\Feature;

use App\Models\PostCategory;
use App\Models\Store;
use App\Models\StoreMember;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PostCreateApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_owner_can_create_published_post_with_images_and_tags(): void
    {
        $owner = User::factory()->create(['role' => 'CUSTOMER']);
        $store = Store::create(['name' => 'CafeON', 'slug' => 'cafeon', 'address' => 'Seoul', 'is_active' => true]);
        StoreMember::create(['store_id' => $store->id, 'user_id' => $owner->id, 'role' => 'OWNER', 'is_active' => true]);
        $category = PostCategory::create(['store_id' => $store->id, 'name' => 'Notice', 'slug' => 'notice', 'is_active' => true]);
        $tag = Tag::create(['store_id' => $store->id, 'name' => 'Event', 'slug' => 'event']);
        Sanctum::actingAs($owner);

        $this->postJson('/api/posts', [
            'store_id' => $store->id,
            'category_id' => $category->id,
            'title' => 'New Summer Menu',
            'content' => 'Post body',
            'status' => 'PUBLISHED',
            'tag_ids' => [$tag->id],
            'images' => [['image_url' => 'https://example.com/menu.jpg', 'alt_text' => 'Summer menu']],
        ])->assertCreated()
            ->assertJsonPath('post.slug', 'new-summer-menu')
            ->assertJsonPath('post.status', 'PUBLISHED')
            ->assertJsonCount(1, 'post.images')
            ->assertJsonCount(1, 'post.tags');

        $this->assertDatabaseHas('posts', ['store_id' => $store->id, 'title' => 'New Summer Menu']);
    }

    public function test_customer_without_store_permission_cannot_create_post(): void
    {
        $user = User::factory()->create(['role' => 'CUSTOMER']);
        $store = Store::create(['name' => 'CafeON', 'slug' => 'cafeon', 'address' => 'Seoul', 'is_active' => true]);
        $category = PostCategory::create(['store_id' => $store->id, 'name' => 'Notice', 'slug' => 'notice', 'is_active' => true]);
        Sanctum::actingAs($user);

        $this->postJson('/api/posts', [
            'store_id' => $store->id, 'category_id' => $category->id,
            'title' => 'Forbidden', 'content' => 'Body', 'status' => 'DRAFT',
        ])->assertForbidden();
    }
}
