<?php

namespace Tests\Feature;

use App\Models\CustomerVisit;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitor_can_create_update_list_and_delete_review(): void
    {
        [$user, $store, $visit] = $this->visitFixture();

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/stores/{$store->id}/reviews", [
            'customer_visit_id' => $visit->id,
            'rating' => 5,
            'content' => 'Great cafe',
            'image_urls' => ['https://example.com/review.jpg'],
        ])->assertCreated()
            ->assertJsonPath('review.rating', 5)
            ->assertJsonPath('review.is_verified_purchase', true)
            ->assertJsonCount(1, 'review.images');

        $reviewId = $response->json('review.id');
        $this->getJson("/api/stores/{$store->id}/reviews")->assertOk()->assertJsonCount(1, 'data');
        $this->putJson("/api/reviews/{$reviewId}", ['rating' => 4, 'content' => 'Updated'])
            ->assertOk()->assertJsonPath('review.rating', 4);
        $this->deleteJson("/api/reviews/{$reviewId}")->assertOk();
        $this->assertSoftDeleted('reviews', ['id' => $reviewId]);
    }

    public function test_user_without_completed_visit_cannot_write_review(): void
    {
        $user = User::factory()->create();
        $store = $this->store();

        $this->actingAs($user, 'sanctum')->postJson("/api/stores/{$store->id}/reviews", [
            'rating' => 5,
            'content' => '방문 증빙이 없습니다.',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_visit_id', 'order_id', 'reservation_id']);
    }

    public function test_user_cannot_review_with_another_users_visit(): void
    {
        [, $store, $visit] = $this->visitFixture();
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser, 'sanctum')->postJson("/api/stores/{$store->id}/reviews", [
            'customer_visit_id' => $visit->id,
            'rating' => 5,
            'content' => '타인의 방문 이력입니다.',
        ])->assertForbidden();
    }

    public function test_incomplete_order_cannot_be_used_for_review(): void
    {
        $user = User::factory()->create();
        $store = $this->store();
        $order = Order::create([
            'order_number' => 'ORD-'.uniqid(),
            'user_id' => $user->id,
            'store_id' => $store->id,
            'menu_amount' => 5000,
            'final_amount' => 5000,
            'status' => 'PAID',
        ]);

        $this->actingAs($user, 'sanctum')->postJson("/api/stores/{$store->id}/reviews", [
            'order_id' => $order->id,
            'rating' => 5,
            'content' => '아직 완료되지 않은 주문입니다.',
        ])->assertUnprocessable();
    }

    public function test_same_visit_cannot_receive_multiple_reviews(): void
    {
        [$user, $store, $visit] = $this->visitFixture();
        $payload = ['customer_visit_id' => $visit->id, 'rating' => 5, 'content' => '첫 리뷰'];
        $this->actingAs($user, 'sanctum')->postJson("/api/stores/{$store->id}/reviews", $payload)->assertCreated();
        $this->postJson("/api/stores/{$store->id}/reviews", $payload)->assertUnprocessable();
    }

    private function visitFixture(): array
    {
        $user = User::factory()->create();
        $store = $this->store();
        $visit = CustomerVisit::create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'type' => 'CHECK_IN',
            'visited_at' => now(),
            'idempotency_key' => 'visit-'.uniqid(),
        ]);

        return [$user, $store, $visit];
    }

    private function store(): Store
    {
        return Store::create(['name' => 'CafeON', 'slug' => 'cafeon-'.uniqid(), 'address' => 'Seoul', 'is_active' => true]);
    }
}
