<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // nullable: orders can exist before an address is chosen
            $table->foreignId('address_id')
                ->nullable()
                ->after('customer_id') // keep keys near the top
                ->constrained('addresses')
                ->nullOnDelete();

            // helpful composite index for dashboards/filters
            $table->index(['customer_id', 'address_id', 'status'], 'orders_cust_addr_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_cust_addr_status_idx');
            $table->dropConstrainedForeignId('address_id');
        });
    }
};
