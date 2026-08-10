<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_update_list_and_delete_review(): void
    {
        $user = User::factory()->create();
        $store = Store::create(['name' => 'CafeON', 'slug' => 'cafeon', 'address' => 'Seoul', 'is_active' => true]);

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/stores/{$store->id}/reviews", [
            'rating' => 5,
            'content' => 'Great cafe',
            'image_urls' => ['https://example.com/review.jpg'],
        ])->assertCreated()->assertJsonPath('review.rating', 5)->assertJsonCount(1, 'review.images');

        $reviewId = $response->json('review.id');
        $this->getJson("/api/stores/{$store->id}/reviews")->assertOk()->assertJsonCount(1, 'data');
        $this->putJson("/api/reviews/{$reviewId}", ['rating' => 4, 'content' => 'Updated'])
            ->assertOk()->assertJsonPath('review.rating', 4);
        $this->deleteJson("/api/reviews/{$reviewId}")->assertOk();
        $this->assertSoftDeleted('reviews', ['id' => $reviewId]);
    }
}
