<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('total_amount', 12, 2)->default(0)->after('reservation_id');
        });

        DB::table('orders')->orderBy('id')->chunkById(500, function ($orders) {
            foreach ($orders as $order) {
                $totalAmount = DB::table('order_items')
                    ->where('order_id', $order->id)
                    ->sum('line_amount');

                DB::table('orders')
                    ->where('id', $order->id)
                    ->update(['total_amount' => $totalAmount]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('total_amount');
        });
    }
};
