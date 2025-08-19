<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('customer_id')
                ->nullable()
                ->after('id')
                ->constrained('customers')
                ->nullOnDelete();

            // Optional helpful index for status dashboards, etc.
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['orders_customer_id_status_index']);
            $table->dropConstrainedForeignId('customer_id');
        });
    }
};
