<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_store_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->integer('point_balance')->default(0);
            $table->unsignedInteger('total_earned_points')->default(0);
            $table->unsignedInteger('visit_count')->default(0);
            $table->unsignedInteger('purchase_count')->default(0);
            $table->decimal('total_purchase_amount', 14, 2)->default(0);
            $table->timestamp('first_visited_at')->nullable();
            $table->timestamp('last_visited_at')->nullable();
            $table->timestamp('last_purchased_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'store_id']);
            $table->index(['store_id', 'visit_count']);
            $table->index(['store_id', 'total_purchase_amount']);
            $table->index(['store_id', 'point_balance']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_store_accounts');
    }
};
