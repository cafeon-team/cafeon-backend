<?php
namespace Database\Seeders;

use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\ReservationSlot;
use App\Models\Store;
use App\Models\StoreSeat;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CoreWorkflowDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $store = Store::query()->firstOrFail();
            $user = User::query()->where('email', 'user@cafeon.test')->firstOrFail();

            $category = MenuCategory::query()->updateOrCreate(
                ['store_id' => $store->id, 'name' => 'Demo Drinks'],
                ['sort_order' => 1, 'is_active' => true]
            );
            $menu = Menu::withTrashed()->updateOrCreate(
                ['store_id' => $store->id, 'name' => 'CafeOn Demo Latte'],
                ['category_id' => $category->id, 'description' => 'API workflow test menu', 'price' => 5500, 'is_available' => true, 'deleted_at' => null]
            );
            $seat = StoreSeat::withTrashed()->updateOrCreate(
                ['store_id' => $store->id, 'seat_code' => 'DEMO-A1'],
                ['seat_name' => 'Demo Window Seat', 'seat_type' => 'WINDOW', 'capacity' => 2, 'floor_number' => 1, 'status' => 'AVAILABLE', 'is_active' => true, 'deleted_at' => null]
            );
            $slotDate = now()->addDays(7)->toDateString();
            $slot = ReservationSlot::query()->updateOrCreate(
                ['store_id' => $store->id, 'slot_date' => $slotDate, 'start_time' => '14:00:00'],
                ['end_time' => '15:00:00', 'is_active' => true]
            );
            $reservation = Reservation::query()->updateOrCreate(
                ['reservation_number' => 'RSV-DEMO-0001'],
                ['user_id' => $user->id, 'store_id' => $store->id, 'reservation_slot_id' => $slot->id, 'guest_count' => 2, 'customer_name' => $user->name, 'customer_phone' => $user->phone ?: '010-0000-0000', 'status' => 'CONFIRMED', 'confirmed_at' => now()]
            );
            ReservationSeat::query()->updateOrCreate(
                ['reservation_id' => $reservation->id, 'seat_id' => $seat->id],
                ['reservation_slot_id' => $slot->id]
            );
            $order = Order::query()->updateOrCreate(
                ['order_number' => 'ORD-DEMO-0001'],
                ['user_id' => $user->id, 'store_id' => $store->id, 'reservation_id' => $reservation->id, 'menu_amount' => 11000, 'deposit_amount' => 0, 'coupon_discount_amount' => 0, 'point_used' => 0, 'final_amount' => 11000, 'status' => 'PAID', 'paid_at' => now()]
            );
            OrderItem::query()->updateOrCreate(
                ['order_id' => $order->id, 'menu_id' => $menu->id],
                ['menu_name' => $menu->name, 'unit_price' => 5500, 'quantity' => 2, 'line_amount' => 11000]
            );
            Payment::query()->updateOrCreate(
                ['toss_order_id' => 'TOSS-DEMO-0001'],
                ['order_id' => $order->id, 'provider' => 'TOSS', 'payment_key' => 'demo-payment-key-0001', 'method' => 'CARD', 'amount' => 11000, 'cancelled_amount' => 0, 'status' => 'DONE', 'approved_at' => now()]
            );
        });
    }
}