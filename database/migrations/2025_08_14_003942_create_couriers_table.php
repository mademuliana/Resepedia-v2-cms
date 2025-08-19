<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('couriers', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // GoSend, Grab, JNE, Internal, …
            $table->enum('type', ['internal', 'third_party']);
            $table->string('phone')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['type', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('couriers');
    }
};
