<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('content');
            $table->boolean('is_verified_purchase')->default(false);
            $table->enum('status', ['VISIBLE', 'HIDDEN', 'REPORTED'])->default('VISIBLE');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['store_id', 'status', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['store_id', 'rating']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
