<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 60);
            $table->unsignedInteger('monthly_price')->default(0);
            $table->unsignedInteger('yearly_price')->default(0);
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->enum('billing_cycle', ['MONTHLY', 'YEARLY']);
            $table->enum('status', ['PENDING_PAYMENT', 'ACTIVE', 'CANCELLED', 'EXPIRED'])->default('PENDING_PAYMENT');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status', 'ends_at']);
        });
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('user_subscriptions')->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->string('provider', 30)->default('TOSS');
            $table->string('provider_transaction_id')->nullable()->unique();
            $table->enum('status', ['PENDING', 'PAID', 'FAILED', 'REFUNDED'])->default('PENDING');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['subscription_id', 'created_at']);
        });
        DB::table('plans')->insert([
            ['code' => 'BASIC', 'name' => 'Basic', 'monthly_price' => 0, 'yearly_price' => 0, 'features' => json_encode(['basic_dashboard', 'orders']), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'PRO', 'name' => 'Pro', 'monthly_price' => 29000, 'yearly_price' => 290000, 'features' => json_encode(['sales_analytics', 'inventory', 'staff', 'notifications']), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('user_subscriptions');
        Schema::dropIfExists('plans');
    }
};
