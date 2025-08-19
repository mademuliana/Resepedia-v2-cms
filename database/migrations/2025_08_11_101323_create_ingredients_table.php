<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->nullable(); // meat, veg, spice, etc.
            $table->string('unit', 20); // gram, kg, ml
            $table->decimal('calorie_per_unit', 8, 2)->default(0);
            $table->decimal('cost_per_unit', 10, 2)->default(0);
            $table->decimal('stock_quantity', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredients');
    }
};
