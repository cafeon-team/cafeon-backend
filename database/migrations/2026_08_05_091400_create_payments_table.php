<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->string('provider', 30)->default('TOSS');
            $table->string('payment_key')->nullable()->unique();
            $table->string('toss_order_id', 64)->unique();
            $table->string('method', 50)->nullable();
            $table->decimal('amount', 12, 2);
            $table->decimal('cancelled_amount', 12, 2)->default(0);
            $table->enum('status', ['READY', 'DONE', 'FAILED', 'CANCELLED', 'PARTIAL_CANCELLED'])->default('READY');
            $table->string('failure_code', 100)->nullable();
            $table->string('failure_message', 500)->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
