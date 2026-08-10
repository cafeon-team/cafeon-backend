<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('STAFF');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['store_id', 'user_id']);
            $table->index(['user_id', 'role', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_members');
    }
};