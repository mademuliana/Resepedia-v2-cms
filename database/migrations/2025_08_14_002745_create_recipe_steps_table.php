<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete(); //delete when the recipe is deleted

            $table->unsignedInteger('step_no');
            $table->text('instruction');
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->string('media_url')->nullable();

            $table->timestamps();

            // no duplication on recipe step
            $table->unique(['recipe_id', 'step_no'], 'recipe_steps_recipe_id_step_no_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_steps');
    }
};
