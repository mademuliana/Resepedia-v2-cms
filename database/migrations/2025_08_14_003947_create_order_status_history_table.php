<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->string('status_from');
            $table->string('status_to');
            $table->dateTime('changed_at');
            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('note')->nullable();

            $table->timestamps();

            // Lookups
            $table->index('order_id');
            $table->index('changed_at');
            $table->index('changed_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
    }
};
