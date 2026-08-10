<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('store_noshow_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('deposit_required')->default(false);
            $table->decimal('deposit_amount', 12, 2)->default(0);
            $table->unsignedInteger('free_cancellation_minutes')->default(60);
            $table->enum('penalty_type', ['NONE', 'POINT', 'RESERVATION_BLOCK'])->default('NONE');
            $table->unsignedInteger('penalty_point_amount')->default(0);
            $table->unsignedInteger('reservation_block_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_noshow_policies');
    }
};
