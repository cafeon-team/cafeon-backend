<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30)->default('TOSS');
            $table->string('transmission_id', 150)->unique();
            $table->string('event_type', 100);
            $table->string('payment_key', 200)->nullable()->index();
            $table->json('payload');
            $table->enum('status', ['RECEIVED', 'PROCESSED', 'FAILED'])->default('RECEIVED');
            $table->string('failure_message', 500)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
    }
};
