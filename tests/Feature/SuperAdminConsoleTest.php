<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\CustomerInquiry;
use App\Models\Review;
use App\Models\Store;
use App\Models\StoreMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminConsoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->get('/admin/login')->assertOk()->assertSee('운영 관리자 로그인');
    }

    public function test_regular_admin_cannot_access_super_admin_console(): void
    {
        $owner = User::factory()->create(['role' => 'ADMIN', 'is_active' => true]);

        $this->actingAs($owner)->get('/admin')->assertForbidden();
    }

    public function test_super_admin_can_login_and_view_dashboard(): void
    {
        $admin = User::factory()->create([
            'email' => 'superadmin@example.com',
            'password' => Hash::make('Safe-password-123!'),
            'role' => 'SUPER_ADMIN',
            'is_active' => true,
        ]);

        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'Safe-password-123!',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
        $this->get('/admin')->assertOk()->assertSee('오늘의 CafeOn');
        $this->assertDatabaseHas('admin_audit_logs', ['admin_id' => $admin->id, 'action' => 'admin.login']);
    }

    public function test_super_admin_can_open_every_mvp_console_screen(): void
    {
        $admin = User::factory()->create(['role' => 'SUPER_ADMIN', 'is_active' => true]);

        foreach (['/admin', '/admin/users', '/admin/stores', '/admin/commerce', '/admin/moderation', '/admin/system'] as $uri) {
            $this->actingAs($admin)->get($uri)->assertOk();
        }
    }

    public function test_inactive_super_admin_cannot_login(): void
    {
        $admin = User::factory()->create([
            'email' => 'inactive-admin@example.com',
            'password' => Hash::make('Safe-password-123!'),
            'role' => 'SUPER_ADMIN',
            'is_active' => false,
        ]);

        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'Safe-password-123!',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_super_admin_can_suspend_users_and_stores_with_audit_logs(): void
    {
        $admin = User::factory()->create(['role' => 'SUPER_ADMIN', 'is_active' => true]);
        $customer = User::factory()->create(['role' => 'CUSTOMER', 'is_active' => true]);
        $store = Store::create([
            'name' => '관리 대상 카페',
            'slug' => 'admin-target-cafe',
            'address' => '서울특별시',
            'is_active' => true,
            'is_open' => true,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.users.toggle', $customer))
            ->assertSessionHas('status');
        $this->actingAs($admin)
            ->patch(route('admin.stores.toggle', $store))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('users', ['id' => $customer->id, 'is_active' => false]);
        $this->assertDatabaseHas('stores', ['id' => $store->id, 'is_active' => false, 'is_open' => false]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'user.toggle_active', 'target_id' => $customer->id]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'store.toggle_active', 'target_id' => $store->id]);
    }

    public function test_super_admin_can_change_customer_to_admin_and_revoke_existing_tokens(): void
    {
        $admin = User::factory()->create(['role' => 'SUPER_ADMIN', 'is_active' => true]);
        $customer = User::factory()->create(['role' => 'CUSTOMER', 'is_active' => true]);
        $customer->createToken('old-session');

        $this->actingAs($admin)->patch(route('admin.users.role', $customer), [
            'role' => 'ADMIN',
        ])->assertSessionHas('status');

        $this->assertDatabaseHas('users', ['id' => $customer->id, 'role' => 'ADMIN']);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $customer->id]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'user.update_role',
            'target_id' => $customer->id,
        ]);
    }

    public function test_demoting_owner_to_customer_removes_active_store_owner_access(): void
    {
        $admin = User::factory()->create(['role' => 'SUPER_ADMIN', 'is_active' => true]);
        $owner = User::factory()->create(['role' => 'ADMIN', 'is_active' => true]);
        $store = Store::create(['name' => '권한 변경 카페', 'slug' => 'role-change-cafe', 'address' => '서울']);
        $membership = StoreMember::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'role' => 'OWNER',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->patch(route('admin.users.role', $owner), [
            'role' => 'CUSTOMER',
        ])->assertSessionHas('status');

        $this->assertDatabaseHas('users', ['id' => $owner->id, 'role' => 'CUSTOMER']);
        $this->assertDatabaseHas('store_members', ['id' => $membership->id, 'is_active' => false]);
    }

    public function test_super_admin_role_cannot_be_assigned_or_changed_from_user_screen(): void
    {
        $admin = User::factory()->create(['role' => 'SUPER_ADMIN', 'is_active' => true]);
        $customer = User::factory()->create(['role' => 'CUSTOMER', 'is_active' => true]);
        $otherSuperAdmin = User::factory()->create(['role' => 'SUPER_ADMIN', 'is_active' => true]);

        $this->actingAs($admin)->patch(route('admin.users.role', $customer), [
            'role' => 'SUPER_ADMIN',
        ])->assertSessionHasErrors('role');
        $this->actingAs($admin)->patch(route('admin.users.role', $otherSuperAdmin), [
            'role' => 'CUSTOMER',
        ])->assertUnprocessable();

        $this->assertSame('CUSTOMER', $customer->fresh()->role);
        $this->assertSame('SUPER_ADMIN', $otherSuperAdmin->fresh()->role);
    }

    public function test_super_admin_can_moderate_reviews_and_answer_inquiries(): void
    {
        $admin = User::factory()->create(['role' => 'SUPER_ADMIN', 'is_active' => true]);
        $customer = User::factory()->create(['role' => 'CUSTOMER', 'is_active' => true]);
        $store = Store::create(['name' => '리뷰 카페', 'slug' => 'review-cafe', 'address' => '서울']);
        $review = Review::create([
            'store_id' => $store->id,
            'user_id' => $customer->id,
            'rating' => 1,
            'content' => '검토가 필요한 리뷰',
            'status' => 'REPORTED',
        ]);
        $inquiry = CustomerInquiry::create([
            'user_id' => $customer->id,
            'category' => 'ACCOUNT',
            'title' => '계정 문의',
            'content' => '확인 부탁드립니다.',
            'status' => 'PENDING',
        ]);

        $this->actingAs($admin)->patch(route('admin.reviews.update', $review), [
            'status' => 'HIDDEN',
        ])->assertSessionHas('status');
        $this->actingAs($admin)->patch(route('admin.inquiries.answer', $inquiry), [
            'answer' => '확인 후 처리했습니다.',
            'status' => 'ANSWERED',
        ])->assertSessionHas('status');

        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'status' => 'HIDDEN']);
        $this->assertDatabaseHas('customer_inquiries', [
            'id' => $inquiry->id,
            'status' => 'ANSWERED',
            'answer' => '확인 후 처리했습니다.',
            'answered_by' => $admin->id,
        ]);
        $this->assertSame(2, AdminAuditLog::whereIn('action', ['review.update_status', 'inquiry.answer'])->count());
    }
}
