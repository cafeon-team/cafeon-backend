<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\PostLike;
use App\Models\Store;
use App\Models\StoreMember;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class BlogDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@cafeon.test'],
            [
                'name' => 'CafeOn Admin',
                'password' => Hash::make('password1234'),
                'role' => 'ADMIN',
                'is_active' => true,
            ],
        );

        $customer = User::updateOrCreate(
            ['email' => 'user@cafeon.test'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password1234'),
                'role' => 'CUSTOMER',
                'is_active' => true,
            ],
        );

        $store = Store::updateOrCreate(
            ['slug' => 'cafeon-test'],
            [
                'name' => 'CafeOn Test Store',
                'description' => 'Store for frontend integration testing.',
                'address' => 'Seoul Test District',
                'reservation_enabled' => true,
                'is_active' => true,
            ],
        );

        StoreMember::updateOrCreate(
            ['store_id' => $store->id, 'user_id' => $admin->id],
            ['role' => 'OWNER', 'is_active' => true],
        );

        $categoryData = [
            ['name' => 'New Menu / Events', 'slug' => 'new-menu-event'],
            ['name' => 'Cafe Story', 'slug' => 'cafe-story'],
            ['name' => 'Customer Reviews', 'slug' => 'customer-review'],
            ['name' => 'Tips / Recipes', 'slug' => 'tips-recipe'],
            ['name' => 'Notices', 'slug' => 'notice'],
        ];

        foreach ($categoryData as $index => $data) {
            PostCategory::updateOrCreate(
                ['store_id' => $store->id, 'slug' => $data['slug']],
                ['name' => $data['name'], 'sort_order' => $index, 'is_active' => true],
            );
        }

        $category = PostCategory::where('store_id', $store->id)
            ->where('slug', 'notice')
            ->firstOrFail();

        $post = Post::updateOrCreate(
            ['store_id' => $store->id, 'slug' => 'cafeon-first-news'],
            [
                'category_id' => $category->id,
                'author_id' => $admin->id,
                'title' => 'CafeOn First News',
                'summary' => 'Test post for frontend integration.',
                'content' => 'This is the first CafeOn blog test post.',
                'status' => 'PUBLISHED',
                'published_at' => now(),
            ],
        );

        $comment = Comment::updateOrCreate(
            ['post_id' => $post->id, 'user_id' => $customer->id, 'parent_id' => null],
            ['content' => 'First comment written by the test user.', 'status' => 'VISIBLE'],
        );

        Comment::updateOrCreate(
            ['post_id' => $post->id, 'user_id' => $admin->id, 'parent_id' => $comment->id],
            ['content' => 'Test reply from the store owner.', 'status' => 'VISIBLE'],
        );

        PostLike::firstOrCreate(['post_id' => $post->id, 'user_id' => $customer->id]);
    }
}