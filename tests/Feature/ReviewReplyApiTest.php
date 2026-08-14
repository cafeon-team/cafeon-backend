<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Models\Store;
use App\Models\StoreMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReviewReplyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_owner_can_create_reply_and_public_review_includes_it(): void
    {
        [$owner, $store, $review] = $this->fixture();
        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/owner/reviews/{$review->id}/reply", [
            'content' => '방문해 주셔서 감사합니다.',
        ])->assertCreated()
            ->assertJsonPath('reply.content', '방문해 주셔서 감사합니다.')
            ->assertJsonPath('reply.author.id', $owner->id);

        $this->getJson("/api/stores/{$store->id}/reviews")
            ->assertOk()
            ->assertJsonPath('data.0.reply.id', $response->json('reply.id'))
            ->assertJsonPath('data.0.reply.content', '방문해 주셔서 감사합니다.');
    }

    public function test_review_can_have_only_one_owner_reply(): void
    {
        [$owner, , $review] = $this->fixture();
        Sanctum::actingAs($owner);

        $this->postJson("/api/owner/reviews/{$review->id}/reply", ['content' => '첫 답글'])->assertCreated();
        $this->postJson("/api/owner/reviews/{$review->id}/reply", ['content' => '중복 답글'])->assertUnprocessable();
    }

    public function test_store_manager_can_update_and_delete_reply(): void
    {
        [$owner, $store, $review] = $this->fixture();
        Sanctum::actingAs($owner);
        $replyId = $this->postJson("/api/owner/reviews/{$review->id}/reply", ['content' => '기존 답글'])->json('reply.id');

        $manager = User::factory()->create();
        StoreMember::create(['store_id' => $store->id, 'user_id' => $manager->id, 'role' => 'MANAGER', 'is_active' => true]);
        Sanctum::actingAs($manager);

        $this->putJson("/api/owner/review-replies/{$replyId}", ['content' => '수정된 답글'])
            ->assertOk()->assertJsonPath('reply.content', '수정된 답글');
        $this->deleteJson("/api/owner/review-replies/{$replyId}")->assertNoContent();
        $this->assertDatabaseMissing('review_replies', ['id' => $replyId]);
    }

    public function test_unrelated_user_cannot_manage_reply(): void
    {
        [, , $review] = $this->fixture();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/owner/reviews/{$review->id}/reply", ['content' => '권한 없는 답글'])
            ->assertForbidden();
    }

    public function test_admin_can_create_reply_for_any_store(): void
    {
        [, , $review] = $this->fixture();
        Sanctum::actingAs(User::factory()->create(['role' => 'ADMIN']));

        $this->postJson("/api/owner/reviews/{$review->id}/reply", ['content' => '관리자 답글'])
            ->assertCreated();
    }

    private function fixture(): array
    {
        $owner = User::factory()->create(['role' => 'OWNER']);
        $customer = User::factory()->create();
        $store = Store::create([
            'name' => '답글 테스트 매장',
            'slug' => 'reply-store-'.uniqid(),
            'address' => '서울',
            'is_active' => true,
        ]);
        StoreMember::create(['store_id' => $store->id, 'user_id' => $owner->id, 'role' => 'OWNER', 'is_active' => true]);
        $review = Review::create([
            'store_id' => $store->id,
            'user_id' => $customer->id,
            'rating' => 5,
            'content' => '좋은 카페입니다.',
            'is_verified_purchase' => true,
            'status' => 'VISIBLE',
        ]);

        return [$owner, $store, $review];
    }
}
