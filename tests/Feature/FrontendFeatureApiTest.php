<?php
namespace Tests\Feature;
use App\Models\Faq;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class FrontendFeatureApiTest extends TestCase {
 use RefreshDatabase;
 public function test_frontend_gap_apis_work():void {
  $user=User::factory()->create(); $store=Store::create(['name'=>'Cafe','slug'=>'cafe','address'=>'Seoul','is_active'=>true]); Sanctum::actingAs($user);
  $this->postJson("/api/stores/{$store->id}/favorite")->assertCreated();
  $this->getJson('/api/users/me/favorites')->assertOk()->assertJsonCount(1);
  $this->putJson('/api/users/me/preferences',['order_notifications'=>false,'preferred_tags'=>['quiet']])->assertOk()->assertJsonPath('order_notifications',false);
  $this->postJson('/api/users/me/inquiries',['category'=>'ORDER','title'=>'문의','content'=>'내용'])->assertCreated();
  $this->getJson('/api/users/me/inquiries')->assertOk()->assertJsonCount(1,'data');
  $this->getJson('/api/users/me/membership-summary')->assertOk()->assertJsonStructure(['grade','total_points','stamps']);
  $this->getJson('/api/users/me/referral-code')->assertOk()->assertJsonStructure(['code']);
  $this->deleteJson("/api/stores/{$store->id}/favorite")->assertNoContent();
 }
 public function test_public_support_and_recommendation_apis_work():void {
  Faq::create(['question'=>'질문','answer'=>'답변']); Store::create(['name'=>'Cafe','slug'=>'cafe','address'=>'Seoul','is_active'=>true]);
  $this->getJson('/api/faqs')->assertOk()->assertJsonCount(1);
  $this->getJson('/api/recommendations/stores')->assertOk()->assertJsonCount(1,'stores');
 }
}
