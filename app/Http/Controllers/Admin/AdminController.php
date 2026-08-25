<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\CustomerInquiry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function login(): View|RedirectResponse
    {
        if (Auth::check() && Auth::user()->role === 'SUPER_ADMIN') {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'max:128'],
        ]);

        if (! Auth::attempt([...$credentials, 'role' => 'SUPER_ADMIN', 'is_active' => true], $request->boolean('remember'))) {
            return back()->withErrors(['email' => '관리자 계정 정보를 확인해 주세요.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        $this->audit($request, 'admin.login');

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->audit($request, 'admin.logout');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    public function dashboard(): View
    {
        $today = today();

        return view('admin.dashboard', [
            'metrics' => [
                'users' => User::count(),
                'newUsers' => User::whereDate('created_at', $today)->count(),
                'stores' => Store::count(),
                'activeStores' => Store::where('is_active', true)->count(),
                'orders' => Order::count(),
                'todaySales' => (int) Order::whereDate('paid_at', $today)->whereNotIn('status', ['CANCELLED', 'REFUNDED'])->sum('final_amount'),
                'reservations' => Reservation::count(),
                'pendingInquiries' => CustomerInquiry::where('status', 'PENDING')->count(),
            ],
            'orderStatuses' => Order::selectRaw('status, count(*) total')->groupBy('status')->pluck('total', 'status'),
            'recentUsers' => User::latest()->limit(6)->get(),
            'recentStores' => Store::latest()->limit(6)->get(),
            'recentAudits' => AdminAuditLog::with('admin:id,name,email')->latest('id')->limit(8)->get(),
        ]);
    }

    public function users(Request $request): View
    {
        $query = User::query()
            ->withCount(['storeMemberships as active_owner_stores_count' => fn ($q) => $q
                ->where('role', 'OWNER')
                ->where('is_active', true)])
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', '%'.$request->string('q').'%')
                ->orWhere('email', 'like', '%'.$request->string('q').'%')))
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->string('role')))
            ->when($request->filled('active'), fn ($q) => $q->where('is_active', $request->boolean('active')));

        return view('admin.users', [
            'users' => $query->latest()->paginate(20)->withQueryString(),
            'roles' => User::select('role')->distinct()->orderBy('role')->pluck('role'),
        ]);
    }

    public function toggleUser(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id || $user->role === 'SUPER_ADMIN', 422, 'SUPER_ADMIN 계정은 이 화면에서 정지할 수 없습니다.');
        $before = $user->is_active;
        $user->forceFill(['is_active' => ! $before])->save();
        if ($before) {
            $user->tokens()->delete();
        }
        $this->audit($request, 'user.toggle_active', $user, ['before' => $before, 'after' => $user->is_active]);

        return back()->with('status', $user->is_active ? '사용자 계정을 활성화했습니다.' : '사용자 계정을 정지했습니다.');
    }

    public function updateUserRole(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id || $user->role === 'SUPER_ADMIN', 422, 'SUPER_ADMIN 권한은 서버 명령으로만 관리할 수 있습니다.');
        $validated = $request->validate([
            'role' => ['required', Rule::in(['CUSTOMER', 'ADMIN'])],
        ]);
        $before = strtoupper((string) $user->role);
        $after = $validated['role'];

        if ($before === $after) {
            return back()->with('status', '이미 선택한 역할로 설정되어 있습니다.');
        }

        $deactivatedMembershipIds = [];
        DB::transaction(function () use ($request, $user, $before, $after, &$deactivatedMembershipIds): void {
            if ($after === 'CUSTOMER') {
                $deactivatedMembershipIds = $user->storeMemberships()
                    ->where('role', 'OWNER')
                    ->where('is_active', true)
                    ->pluck('id')
                    ->all();

                if ($deactivatedMembershipIds !== []) {
                    $user->storeMemberships()->whereKey($deactivatedMembershipIds)->update(['is_active' => false]);
                }
            }

            $user->forceFill(['role' => $after])->save();
            $user->tokens()->delete();
            $this->audit($request, 'user.update_role', $user, [
                'before' => $before,
                'after' => $after,
                'deactivated_owner_membership_ids' => $deactivatedMembershipIds,
            ]);
        });

        $message = "사용자 역할을 {$after}(으)로 변경했습니다.";
        if ($deactivatedMembershipIds !== []) {
            $message .= ' 기존 매장 OWNER 연결도 비활성화했습니다.';
        }

        return back()->with('status', $message);
    }

    public function stores(Request $request): View
    {
        $stores = Store::query()
            ->with(['members' => fn ($q) => $q->where('role', 'OWNER')->with('user:id,name,email')])
            ->withCount(['orders', 'reservations', 'reviews'])
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', '%'.$request->string('q').'%')
                ->orWhere('address', 'like', '%'.$request->string('q').'%')))
            ->when($request->filled('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.stores', compact('stores'));
    }

    public function toggleStore(Request $request, Store $store): RedirectResponse
    {
        $before = $store->is_active;
        $store->forceFill(['is_active' => ! $before, 'is_open' => $before ? false : $store->is_open])->save();
        $this->audit($request, 'store.toggle_active', $store, ['before' => $before, 'after' => $store->is_active]);

        return back()->with('status', $store->is_active ? '매장을 활성화했습니다.' : '매장을 정지했습니다.');
    }

    public function commerce(Request $request): View
    {
        $orders = Order::query()->with(['user:id,name,email', 'store:id,name', 'payment:id,order_id,status'])
            ->when($request->filled('q'), fn ($q) => $q->where('order_number', 'like', '%'.$request->string('q').'%'))
            ->when($request->filled('order_status'), fn ($q) => $q->where('status', $request->string('order_status')))
            ->latest()->paginate(15, ['*'], 'orders_page')->withQueryString();
        $reservations = Reservation::query()->with(['user:id,name,email', 'store:id,name'])
            ->when($request->filled('reservation_status'), fn ($q) => $q->where('status', $request->string('reservation_status')))
            ->latest()->paginate(15, ['*'], 'reservations_page')->withQueryString();

        return view('admin.commerce', [
            'orders' => $orders,
            'reservations' => $reservations,
            'payments' => [
                'done' => Payment::where('status', 'DONE')->count(),
                'failed' => Payment::where('status', 'FAILED')->count(),
                'cancelled' => Payment::whereIn('status', ['CANCELLED', 'PARTIAL_CANCELLED'])->count(),
            ],
        ]);
    }

    public function moderation(Request $request): View
    {
        return view('admin.moderation', [
            'reviews' => Review::with(['user:id,name,email', 'store:id,name'])
                ->when($request->filled('review_status'), fn ($q) => $q->where('status', $request->string('review_status')))
                ->latest()->paginate(15, ['*'], 'reviews_page')->withQueryString(),
            'inquiries' => CustomerInquiry::with('user:id,name,email')
                ->when($request->filled('inquiry_status'), fn ($q) => $q->where('status', $request->string('inquiry_status')))
                ->latest()->paginate(15, ['*'], 'inquiries_page')->withQueryString(),
        ]);
    }

    public function updateReview(Request $request, Review $review): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', 'in:VISIBLE,HIDDEN,REPORTED']]);
        $before = $review->status;
        $review->update($validated);
        $this->audit($request, 'review.update_status', $review, ['before' => $before, 'after' => $review->status]);

        return back()->with('status', '리뷰 상태를 변경했습니다.');
    }

    public function answerInquiry(Request $request, CustomerInquiry $inquiry): RedirectResponse
    {
        $validated = $request->validate([
            'answer' => ['required', 'string', 'max:5000'],
            'status' => ['required', 'in:ANSWERED,CLOSED'],
        ]);
        $inquiry->update([
            ...$validated,
            'answered_by' => $request->user()->id,
            'answered_at' => now(),
        ]);
        $this->audit($request, 'inquiry.answer', $inquiry, ['status' => $inquiry->status]);

        return back()->with('status', '문의 답변을 저장했습니다.');
    }

    public function system(): View
    {
        $databaseOk = true;
        try {
            DB::select('select 1');
        } catch (\Throwable) {
            $databaseOk = false;
        }

        return view('admin.system', [
            'checks' => [
                'database' => $databaseOk,
                'cache' => Cache::getStore() !== null,
                'storage' => is_writable(storage_path()),
                'kakaoMap' => filled(config('services.kakao.rest_api_key')),
                'googleLogin' => filled(config('services.google.client_id')) && filled(config('services.google.client_secret')),
                'kakaoLogin' => filled(config('services.kakao.client_id')) && filled(config('services.kakao.client_secret')),
                'naverLogin' => filled(config('services.naver.client_id')) && filled(config('services.naver.client_secret')),
            ],
            'audits' => AdminAuditLog::with('admin:id,name,email')->latest('id')->paginate(30),
        ]);
    }

    private function audit(Request $request, string $action, ?Model $target = null, array $metadata = []): void
    {
        AdminAuditLog::create([
            'admin_id' => $request->user()?->id,
            'action' => $action,
            'target_type' => $target ? class_basename($target) : null,
            'target_id' => $target?->getKey(),
            'metadata' => $metadata ?: null,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
        ]);
    }
}
