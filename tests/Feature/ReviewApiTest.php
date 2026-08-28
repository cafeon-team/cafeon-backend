<?php

namespace Tests\Feature;

use App\Models\CustomerVisit;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReviewApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitor_can_create_update_list_and_delete_review(): void
    {
        [$user, $store, $visit] = $this->visitFixture();
        Storage::fake('public');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $upload = $this->actingAs($user, 'sanctum')->postJson('/api/uploads/images', [
            'image' => UploadedFile::fake()->createWithContent('review.png', $png),
        ])->assertCreated();

        $response = $this->postJson("/api/stores/{$store->id}/reviews", [
            'customer_visit_id' => $visit->id,
            'rating' => 5,
            'content' => 'Great cafe',
            'image_urls' => [$upload->json('url')],
        ])->assertCreated()
            ->assertJsonPath('review.rating', 5)
            ->assertJsonPath('review.is_verified_purchase', true)
            ->assertJsonCount(1, 'review.images');

        $reviewId = $response->json('review.id');
        $this->assertDatabaseHas('uploaded_images', ['user_id' => $user->id, 'attached_type' => 'review', 'attached_id' => $reviewId]);
        $this->getJson("/api/stores/{$store->id}/reviews")->assertOk()->assertJsonCount(1, 'data');
        $this->putJson("/api/reviews/{$reviewId}", ['rating' => 4, 'content' => 'Updated'])
            ->assertOk()->assertJsonPath('review.rating', 4);
        $this->deleteJson("/api/reviews/{$reviewId}")->assertOk();
        $this->assertSoftDeleted('reviews', ['id' => $reviewId]);
        Storage::disk('public')->assertMissing($upload->json('path'));
        $this->assertDatabaseMissing('uploaded_images', ['path' => $upload->json('path')]);
    }

    public function test_review_rejects_external_or_another_users_upload(): void
    {
        Storage::fake('public');
        [$user, $store, $visit] = $this->visitFixture();
        $other = User::factory()->create();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $upload = $this->actingAs($other, 'sanctum')->postJson('/api/uploads/images', [
            'image' => UploadedFile::fake()->createWithContent('other.png', $png),
        ])->assertCreated();

        $payload = ['customer_visit_id' => $visit->id, 'rating' => 5, 'content' => '사진 검증'];
        $this->actingAs($user, 'sanctum')->postJson("/api/stores/{$store->id}/reviews", [
            ...$payload, 'image_urls' => [$upload->json('url')],
        ])->assertUnprocessable()->assertJsonValidationErrors('image_urls.0');
        $this->postJson("/api/stores/{$store->id}/reviews", [
            ...$payload, 'image_urls' => ['https://example.com/not-owned.jpg'],
        ])->assertUnprocessable()->assertJsonValidationErrors('image_urls.0');
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
