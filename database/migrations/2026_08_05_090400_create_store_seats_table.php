<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('store_seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('seat_code', 30);
            $table->string('seat_name', 100);
            $table->enum('seat_type', ['WINDOW', 'NORMAL', 'GROUP', 'OUTDOOR'])->default('NORMAL');
            $table->unsignedInteger('capacity');
            $table->integer('floor_number')->default(1);
            $table->integer('position_x')->nullable();
            $table->integer('position_y')->nullable();
            $table->enum('status', ['AVAILABLE', 'UNAVAILABLE', 'MAINTENANCE'])->default('AVAILABLE');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['store_id', 'seat_code']);
            $table->index(['store_id', 'status', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_seats');
    }
};
