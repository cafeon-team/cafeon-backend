<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('store_favorites', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete(); $table->timestamps();
            $table->unique(['user_id', 'store_id']);
        });
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('order_notifications')->default(true); $table->boolean('location_enabled')->default(false);
            $table->boolean('marketing_notifications')->default(false); $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable(); $table->json('preferred_tags')->nullable(); $table->timestamps();
        });
        Schema::create('faqs', function (Blueprint $table) {
            $table->id(); $table->string('category', 50)->default('GENERAL'); $table->string('question');
            $table->text('answer'); $table->unsignedInteger('sort_order')->default(0); $table->boolean('is_active')->default(true); $table->timestamps();
        });
        Schema::create('customer_inquiries', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->string('category', 50);
            $table->string('title'); $table->text('content'); $table->enum('status', ['PENDING', 'ANSWERED', 'CLOSED'])->default('PENDING');
            $table->text('answer')->nullable(); $table->foreignId('answered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('answered_at')->nullable(); $table->timestamps(); $table->index(['user_id', 'status']);
        });
        Schema::create('membership_stamp_events', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete(); $table->integer('amount');
            $table->string('reason', 255)->nullable(); $table->timestamp('created_at')->useCurrent(); $table->index(['user_id', 'store_id', 'created_at']);
        });
        Schema::create('referrals', function (Blueprint $table) {
            $table->id(); $table->foreignId('inviter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('invitee_id')->nullable()->unique()->constrained('users')->cascadeOnDelete();
            $table->string('code', 20)->unique(); $table->enum('status', ['AVAILABLE', 'COMPLETED'])->default('AVAILABLE');
            $table->unsignedInteger('reward_points')->default(1000); $table->timestamp('completed_at')->nullable(); $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('referrals'); Schema::dropIfExists('membership_stamp_events');
        Schema::dropIfExists('customer_inquiries'); Schema::dropIfExists('faqs');
        Schema::dropIfExists('user_preferences'); Schema::dropIfExists('store_favorites');
    }
};
