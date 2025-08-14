<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_delivery', function (Blueprint $table) {
            $table->id();

            // 1:1 with orders
            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete(); // delete snapshot if order is deleted
            $table->unique('order_id');

            // links (optional)
            $table->foreignId('address_id')
                ->nullable()
                ->constrained('addresses')
                ->nullOnDelete();
            $table->foreignId('courier_id')
                ->nullable()
                ->constrained('couriers')
                ->nullOnDelete();

            // contact snapshot
            $table->string('contact_name');
            $table->string('contact_phone');

            // address snapshot
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city');
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->default('ID');

            // geopin
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // courier snapshot + tracking
            $table->string('courier_name')->nullable(); // snapshot if courier record changes
            $table->string('tracking_code')->nullable();

            // delivery windows
            $table->dateTime('delivery_window_start')->nullable();
            $table->dateTime('delivery_window_end')->nullable();

            // delivered timestamp + instructions
            $table->dateTime('delivered_at')->nullable();
            $table->text('delivery_instructions')->nullable();

            $table->timestamps();

            // Helpful lookups
            $table->index(['courier_id']);
            $table->index(['address_id']);
            $table->index(['delivery_window_start', 'delivery_window_end']);
            $table->index(['delivered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_delivery');
    }
};
