<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 64)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->foreignId('reservation_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('menu_amount', 12, 2);
            $table->decimal('deposit_amount', 12, 2)->default(0);
            $table->decimal('coupon_discount_amount', 12, 2)->default(0);
            $table->unsignedInteger('point_used')->default(0);
            $table->decimal('final_amount', 12, 2);
            $table->enum('status', [
                'PENDING_PAYMENT', 'PAID', 'PREPARING', 'READY',
                'COMPLETED', 'CANCELLED', 'REFUNDED',
            ])->default('PENDING_PAYMENT');
            $table->text('customer_request')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('preparing_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['store_id', 'status', 'created_at']);
            $table->index(['store_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
