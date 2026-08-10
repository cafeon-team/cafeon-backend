<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('menu_ingredients', function (Blueprint $table) {
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_id')->constrained()->cascadeOnDelete();
            $table->decimal('required_quantity', 12, 3);
            $table->timestamps();
            $table->primary(['menu_id', 'inventory_id']);
            $table->index('inventory_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_ingredients');
    }
};
