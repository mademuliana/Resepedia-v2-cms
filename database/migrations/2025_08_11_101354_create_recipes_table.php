<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('prep_time_minutes')->default(0);
            $table->decimal('portion_size', 8, 2)->default(0); // in grams/ml
            $table->decimal('total_calorie_per_portion', 8, 2)->default(0);
            $table->decimal('total_price_per_portion', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};