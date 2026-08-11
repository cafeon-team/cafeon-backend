<?php

namespace App\Http\Controllers\Test;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\CustomerStoreAccount;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\Store;
use App\Models\User;
use Illuminate\Contracts\View\View;

class MvcVerificationController extends Controller
{
    public function index(): View
    {
        return view('test.mvc', $this->page('MVC 검증 홈', 'dashboard', [
            ['항목' => '매장', '값' => Store::count()],
            ['항목' => '메뉴', '값' => Menu::count()],
            ['항목' => '회원', '값' => User::count()],
            ['항목' => '예약', '값' => Reservation::count()],
            ['항목' => '주문', '값' => Order::count()],
            ['항목' => '쿠폰', '값' => Coupon::count()],
            ['항목' => '리뷰', '값' => Review::count()],
        ]));
    }

    public function stores(): View
    {
        $rows = Store::withCount(['menus', 'seats', 'reservations'])->with('tags:id,store_id,name')->latest()->limit(100)->get()->map(fn ($store) => [
            'ID' => $store->id, '매장명' => $store->name, '주소' => $store->address,
            '메뉴' => $store->menus_count, '좌석' => $store->seats_count, '예약' => $store->reservations_count,
            '태그' => $store->tags->pluck('name')->join(', '), '활성' => $store->is_active ? 'Y' : 'N',
        ])->all();

        return view('test.mvc', $this->page('매장 목록', 'stores', $rows));
    }

    public function menus(): View
    {
        $rows = Menu::with(['store:id,name', 'category:id,name'])->latest()->limit(100)->get()->map(fn ($menu) => [
            'ID' => $menu->id, '매장' => $menu->store?->name, '카테고리' => $menu->category?->name,
            '메뉴명' => $menu->name, '가격' => number_format((float) $menu->price).'원', '판매 가능' => $menu->is_available ? 'Y' : 'N',
        ])->all();

        return view('test.mvc', $this->page('메뉴 목록', 'menus', $rows));
    }

    public function users(): View
    {
        $rows = User::latest()->limit(100)->get()->map(fn ($user) => [
            'ID' => $user->id, '이름' => $user->name, '이메일' => $user->email,
            '역할' => $user->role, '활성' => $user->is_active ? 'Y' : 'N', '최근 로그인' => $user->last_login_at?->format('Y-m-d H:i'),
        ])->all();

        return view('test.mvc', $this->page('회원·인증 상태', 'users', $rows));
    }

    public function reservations(): View
    {
        $rows = Reservation::with(['user:id,name', 'store:id,name', 'slot'])->latest()->limit(100)->get()->map(fn ($reservation) => [
            '번호' => $reservation->reservation_number, '회원' => $reservation->user?->name, '매장' => $reservation->store?->name,
            '날짜' => $reservation->slot?->slot_date?->format('Y-m-d'), '시간' => $reservation->slot?->start_time,
            '인원' => $reservation->guest_count, '상태' => $reservation->status,
        ])->all();

        return view('test.mvc', $this->page('예약 내역', 'reservations', $rows));
    }

    public function orders(): View
    {
        $rows = Order::with(['user:id,name', 'store:id,name', 'items'])->latest()->limit(100)->get()->map(fn ($order) => [
            '주문번호' => $order->order_number, '회원' => $order->user?->name, '매장' => $order->store?->name,
            '상품수' => $order->items->sum('quantity'), '쿠폰 할인' => $order->coupon_discount_amount,
            '포인트' => $order->point_used, '결제금액' => $order->final_amount, '상태' => $order->status,
        ])->all();

        return view('test.mvc', $this->page('주문 내역', 'orders', $rows));
    }

    public function benefits(): View
    {
        $rows = CustomerStoreAccount::with(['user:id,name', 'store:id,name'])->latest()->limit(100)->get()->map(fn ($account) => [
            '회원' => $account->user?->name, '매장' => $account->store?->name, '포인트' => $account->point_balance,
            '누적 적립' => $account->total_earned_points, '방문' => $account->visit_count, '구매' => $account->purchase_count,
        ])->all();

        return view('test.mvc', $this->page('쿠폰·포인트·멤버십', 'benefits', $rows, '발급 쿠폰 '.Coupon::withCount('userCoupons')->get()->sum('user_coupons_count').'장'));
    }

    public function reviews(): View
    {
        $rows = Review::with(['user:id,name', 'store:id,name', 'images', 'reply'])->latest()->limit(100)->get()->map(fn ($review) => [
            'ID' => $review->id, '회원' => $review->user?->name, '매장' => $review->store?->name,
            '평점' => $review->rating, '내용' => $review->content, '이미지' => $review->images->count(),
            '답글' => $review->reply ? 'Y' : 'N', '상태' => $review->status,
        ])->all();

        return view('test.mvc', $this->page('리뷰 목록', 'reviews', $rows));
    }

    public function dashboard(): View
    {
        $rows = Store::withCount(['menus', 'reservations', 'orders', 'reviews'])->get()->map(fn ($store) => [
            '매장' => $store->name, '메뉴' => $store->menus_count, '예약' => $store->reservations_count,
            '주문' => $store->orders_count, '리뷰' => $store->reviews_count,
            '매출 합계' => number_format((float) $store->orders()->whereIn('status', ['PAID', 'PREPARING', 'READY', 'COMPLETED'])->sum('final_amount')).'원',
        ])->all();

        return view('test.mvc', $this->page('사장 대시보드 검증', 'dashboard', $rows));
    }

    public function blogApi(): View
    {
        return view('test.blog-api', [
            'stores' => Store::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    private function page(string $title, string $active, array $rows, ?string $note = null): array
    {
        return compact('title', 'active', 'rows', 'note');
    }
}
