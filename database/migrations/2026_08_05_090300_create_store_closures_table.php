<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('store_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->date('closure_date');
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['store_id', 'closure_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_closures');
    }
};
