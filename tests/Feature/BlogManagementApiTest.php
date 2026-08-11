<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Store;
use App\Models\StoreMember;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BlogManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_search_and_filter_published_posts(): void
    {
        [$owner, $store, $category, $tag] = $this->blogFixture();
        $post = Post::create([
            'store_id' => $store->id, 'category_id' => $category->id, 'author_id' => $owner->id,
            'title' => 'Summer Cake', 'slug' => 'summer-cake', 'content' => 'Fresh mango cake',
            'status' => 'PUBLISHED', 'published_at' => now(),
        ]);
        $post->tags()->attach($tag);

        $this->getJson('/api/posts?keyword=mango&tag=event&category=notice')
            ->assertOk()->assertJsonPath('data.0.id', $post->id);
        $this->getJson('/api/posts?keyword=missing')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_owner_can_update_and_delete_post(): void
    {
        [$owner, $store, $category, $tag] = $this->blogFixture();
        $post = Post::create([
            'store_id' => $store->id, 'category_id' => $category->id, 'author_id' => $owner->id,
            'title' => 'Old title', 'slug' => 'old-title', 'content' => 'Body', 'status' => 'DRAFT',
        ]);
        Sanctum::actingAs($owner);

        $this->putJson("/api/posts/{$post->id}", [
            'title' => 'New title', 'status' => 'PUBLISHED', 'tag_ids' => [$tag->id],
        ])->assertOk()->assertJsonPath('post.title', 'New title')->assertJsonPath('post.status', 'PUBLISHED');

        $this->deleteJson("/api/posts/{$post->id}")->assertNoContent();
        $this->assertSoftDeleted('posts', ['id' => $post->id]);
    }

    public function test_owner_can_manage_categories_tags_and_comment_status(): void
    {
        [$owner, $store, $category] = $this->blogFixture();
        Sanctum::actingAs($owner);

        $categoryId = $this->postJson("/api/stores/{$store->id}/post-categories", [
            'name' => 'Story', 'slug' => 'story',
        ])->assertCreated()->json('id');
        $this->putJson("/api/post-categories/{$categoryId}", ['name' => 'Cafe Story'])->assertOk();

        $tagId = $this->postJson("/api/stores/{$store->id}/tags", [
            'name' => 'Recipe', 'slug' => 'recipe',
        ])->assertCreated()->json('id');
        $this->putJson("/api/tags/{$tagId}", ['name' => 'New Recipe'])->assertOk();

        $post = Post::create([
            'store_id' => $store->id, 'category_id' => $category->id, 'author_id' => $owner->id,
            'title' => 'Post', 'slug' => 'post', 'content' => 'Body', 'status' => 'PUBLISHED', 'published_at' => now(),
        ]);
        $commenter = User::factory()->create();
        $comment = Comment::create(['post_id' => $post->id, 'user_id' => $commenter->id, 'content' => 'Spam', 'status' => 'VISIBLE']);

        $this->patchJson("/api/comments/{$comment->id}/status", ['status' => 'SPAM'])
            ->assertOk()->assertJsonPath('comment.status', 'SPAM');
        $this->deleteJson("/api/tags/{$tagId}")->assertNoContent();
        $this->deleteJson("/api/post-categories/{$categoryId}")->assertNoContent();
    }

    public function test_customer_cannot_manage_blog(): void
    {
        [, $store] = $this->blogFixture();
        Sanctum::actingAs(User::factory()->create(['role' => 'CUSTOMER']));

        $this->postJson("/api/stores/{$store->id}/tags", ['name' => 'No', 'slug' => 'no'])
            ->assertForbidden();
    }

    private function blogFixture(): array
    {
        $owner = User::factory()->create(['role' => 'CUSTOMER']);
        $store = Store::create(['name' => 'CafeON', 'slug' => 'cafeon', 'address' => 'Seoul', 'is_active' => true]);
        StoreMember::create(['store_id' => $store->id, 'user_id' => $owner->id, 'role' => 'OWNER', 'is_active' => true]);
        $category = PostCategory::create(['store_id' => $store->id, 'name' => 'Notice', 'slug' => 'notice', 'is_active' => true]);
        $tag = Tag::create(['store_id' => $store->id, 'name' => 'Event', 'slug' => 'event']);

        return [$owner, $store, $category, $tag];
    }
}
