<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->foreignId('reservation_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->enum('type', ['RESERVATION', 'PURCHASE', 'CHECK_IN']);
            $table->timestamp('visited_at');
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('points_awarded')->default(false);
            $table->string('idempotency_key', 150)->unique();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'store_id', 'visited_at']);
            $table->index(['store_id', 'visited_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_visits');
    }
};
