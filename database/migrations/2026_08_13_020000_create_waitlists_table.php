<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waitlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('queued_on');
            $table->unsignedInteger('queue_number');
            $table->unsignedTinyInteger('guest_count');
            $table->unsignedInteger('estimated_wait_minutes')->nullable();
            $table->enum('status', ['WAITING', 'CALLED', 'SEATED', 'CANCELLED', 'EXPIRED'])->default('WAITING');
            $table->timestamp('called_at')->nullable();
            $table->timestamp('seated_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'queued_on', 'queue_number']);
            $table->index(['store_id', 'queued_on', 'status', 'queue_number']);
            $table->index(['user_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlists');
    }
};
