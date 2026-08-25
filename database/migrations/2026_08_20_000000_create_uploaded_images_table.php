<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('uploaded_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 30)->default('public');
            $table->string('path', 500)->unique();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('attached_type', 50)->nullable();
            $table->unsignedBigInteger('attached_id')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'attached_type', 'attached_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uploaded_images');
    }
};
