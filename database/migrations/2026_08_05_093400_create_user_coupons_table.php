<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('coupon_code', 100)->unique();
            $table->enum('status', ['AVAILABLE', 'USED', 'EXPIRED', 'CANCELLED'])->default('AVAILABLE');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at');
            $table->timestamp('expires_at');
            $table->foreignId('used_order_id')->nullable()->unique()->constrained('orders')->nullOnDelete();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status', 'expires_at']);
            $table->index(['coupon_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_coupons');
    }
};
