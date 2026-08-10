<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reservation_seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_slot_id')->constrained('reservation_slots')->restrictOnDelete();
            $table->foreignId('seat_id')->constrained('store_seats')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['reservation_id', 'seat_id']);
            $table->unique(['reservation_slot_id', 'seat_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_seats');
    }
};
