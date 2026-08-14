<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Plan;
use App\Models\Store;
use App\Models\StoreMember;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OwnerBusinessOperationsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_manage_inventory_and_transactions(): void
    {
        [$owner, $store] = $this->fixture();
        Sanctum::actingAs($owner);
        $id = $this->postJson("/api/owner/stores/{$store->id}/inventory", ['ingredient_name' => '원두', 'quantity' => 10, 'unit' => 'kg', 'low_stock_threshold' => 2])->assertCreated()->json('inventory.id');
        $this->postJson("/api/owner/inventory/{$id}/transactions", ['type' => 'WASTE', 'quantity' => 3, 'reason' => '품질 저하'])->assertOk()->assertJsonPath('inventory.quantity', '7.000');
        $this->postJson("/api/owner/inventory/{$id}/transactions", ['type' => 'STOCK_OUT', 'quantity' => 8])->assertUnprocessable();
        $this->getJson("/api/owner/stores/{$store->id}/inventory/transactions")->assertOk()->assertJsonPath('data.0.type', 'WASTE');
    }

    public function test_owner_can_manage_staff_but_manager_cannot(): void
    {
        [$owner, $store] = $this->fixture();
        $staff = User::factory()->create();
        Sanctum::actingAs($owner);
        $memberId = $this->postJson("/api/owner/stores/{$store->id}/staff", ['email' => $staff->email, 'role' => 'MANAGER'])->assertCreated()->json('member.id');
        $this->patchJson("/api/owner/staff/{$memberId}", ['role' => 'STAFF'])->assertOk()->assertJsonPath('member.role', 'STAFF');
        Sanctum::actingAs($staff);
        $this->postJson("/api/owner/stores/{$store->id}/staff", ['email' => User::factory()->create()->email, 'role' => 'STAFF'])->assertForbidden();
    }

    public function test_owner_can_view_sales_summary_and_menu_ranking(): void
    {
        [$owner, $store] = $this->fixture();
        $customer = User::factory()->create();
        $order = Order::create(['order_number' => 'SALES-1', 'user_id' => $customer->id, 'store_id' => $store->id, 'menu_amount' => 12000, 'final_amount' => 12000, 'status' => 'COMPLETED', 'paid_at' => now()]);
        OrderItem::create(['order_id' => $order->id, 'menu_name' => '라테', 'unit_price' => 6000, 'quantity' => 2, 'line_amount' => 12000]);
        Sanctum::actingAs($owner);
        $this->getJson("/api/owner/stores/{$store->id}/sales")->assertOk()->assertJsonPath('summary.total_sales', 12000)->assertJsonPath('summary.order_count', 1)->assertJsonPath('menu_ranking.0.quantity', 2);
    }

    public function test_user_can_manage_notifications(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $notification = UserNotification::create(['user_id' => $user->id, 'type' => 'ORDER_STATUS', 'title' => '제조 완료', 'message' => '주문이 준비되었습니다.']);
        $otherNotification = UserNotification::create(['user_id' => $other->id, 'type' => 'TEST', 'title' => '다른 알림', 'message' => '다른 사용자']);
        Sanctum::actingAs($user);
        $this->getJson('/api/notifications?unread_only=1')->assertOk()->assertJsonPath('data.0.id', $notification->id);
        $this->patchJson("/api/notifications/{$notification->id}/read")->assertOk();
        $this->deleteJson("/api/notifications/{$otherNotification->id}")->assertNotFound();
    }

    public function test_paid_plan_requires_admin_activation_and_has_billing_history(): void
    {
        $user = User::factory()->create();
        $pro = Plan::where('code', 'PRO')->firstOrFail();
        Sanctum::actingAs($user);
        $subscriptionId = $this->postJson('/api/plans/subscribe', ['plan_id' => $pro->id, 'billing_cycle' => 'MONTHLY'])->assertCreated()->assertJsonPath('subscription.status', 'PENDING_PAYMENT')->json('subscription.id');
        $this->postJson("/api/admin/subscriptions/{$subscriptionId}/activate", ['provider_transaction_id' => 'pay-normal'])->assertForbidden();
        $admin = User::factory()->create(['role' => 'ADMIN']);
        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/subscriptions/{$subscriptionId}/activate", ['provider_transaction_id' => 'pay-admin-1'])->assertOk()->assertJsonPath('subscription.status', 'ACTIVE');
        Sanctum::actingAs($user);
        $this->getJson('/api/plans/me')->assertOk()->assertJsonPath('effective_plan.code', 'PRO');
        $this->getJson('/api/plans/billing-history')->assertOk()->assertJsonPath('data.0.status', 'PAID');
    }

    private function fixture(): array
    {
        $owner = User::factory()->create(['role' => 'OWNER']);
        $store = Store::create(['name' => '운영 매장', 'slug' => 'operations-'.uniqid(), 'address' => '서울', 'is_active' => true]);
        StoreMember::create(['store_id' => $store->id, 'user_id' => $owner->id, 'role' => 'OWNER', 'is_active' => true]);

        return [$owner, $store];
    }
}
