<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->enum('type', ['deposit', 'balance', 'full', 'refund']);
            $table->enum('method', ['cash', 'bank_transfer', 'ewallet', 'card', 'other']);
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded']);
            $table->dateTime('paid_at')->nullable();
            $table->string('reference')->nullable(); // bank ref/VA/txn id
            $table->text('notes')->nullable();

            $table->timestamps();

            // Lookups
            $table->index('order_id');
            $table->index('status');
            $table->index(['type', 'method']);
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
