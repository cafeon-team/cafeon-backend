<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            $table->enum('discount_type', ['FIXED', 'PERCENT', 'FREE_ITEM']);
            $table->decimal('discount_value', 12, 2)->default(0);
            $table->decimal('minimum_order_amount', 12, 2)->default(0);
            $table->decimal('maximum_discount_amount', 12, 2)->nullable();
            $table->foreignId('free_menu_id')->nullable()->constrained('menus')->nullOnDelete();
            $table->timestamp('valid_from');
            $table->timestamp('valid_until');
            $table->unsignedInteger('total_quantity')->nullable();
            $table->unsignedInteger('issued_quantity')->default(0);
            $table->unsignedInteger('per_user_limit')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['store_id', 'is_active', 'valid_until']);
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
