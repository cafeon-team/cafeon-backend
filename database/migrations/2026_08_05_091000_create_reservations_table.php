<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->foreignId('reservation_slot_id')->constrained('reservation_slots')->restrictOnDelete();
            $table->string('reservation_number', 50)->unique();
            $table->unsignedInteger('guest_count');
            $table->string('customer_name', 50);
            $table->string('customer_phone', 30);
            $table->text('customer_request')->nullable();
            $table->enum('status', [
                'PENDING_APPROVAL', 'AWAITING_PAYMENT', 'CONFIRMED', 'REJECTED',
                'CANCELLED', 'COMPLETED', 'NO_SHOW', 'PAYMENT_FAILED', 'EXPIRED',
            ])->default('PENDING_APPROVAL');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamp('approval_expires_at')->nullable();
            $table->timestamp('payment_expires_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['store_id', 'status', 'created_at']);
            $table->index(['reservation_slot_id', 'status']);
            $table->index('approval_expires_at');
            $table->index('payment_expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
