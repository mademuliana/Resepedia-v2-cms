<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Recipes
        Schema::table('recipes', function (Blueprint $table) {
            $table->foreignId('company_id')
                ->nullable()
                ->after('id')
                ->constrained('companies')
                ->nullOnDelete();
            $table->index(['company_id', 'created_at']);
        });

        // Products
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('company_id')
                ->nullable()
                ->after('id')
                ->constrained('companies')
                ->nullOnDelete();
            $table->index(['company_id', 'created_at']);
        });

        // Customers
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('company_id')
                ->nullable()
                ->after('id')
                ->constrained('companies')
                ->nullOnDelete();
            $table->index(['company_id', 'created_at']);
        });

        // Addresses (allow ad-hoc; still scoped to a company)
        Schema::table('addresses', function (Blueprint $table) {
            $table->foreignId('company_id')
                ->nullable()
                ->after('id')
                ->constrained('companies')
                ->nullOnDelete();
            $table->index(['company_id', 'customer_id']);
        });

        // Couriers (company-specific)
        Schema::table('couriers', function (Blueprint $table) {
            $table->foreignId('company_id')
                ->nullable()
                ->after('id')
                ->constrained('companies')
                ->nullOnDelete();
            $table->index(['company_id', 'active']);
        });

        // Orders
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('company_id')
                ->nullable()
                ->after('id')
                ->constrained('companies')
                ->nullOnDelete();
            $table->index(['company_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            if (Schema::hasColumn('recipes', 'company_id')) {
                $table->dropConstrainedForeignId('company_id');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'company_id')) {
                $table->dropConstrainedForeignId('company_id');
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'company_id')) {
                $table->dropConstrainedForeignId('company_id');
            }
        });

        Schema::table('addresses', function (Blueprint $table) {
            if (Schema::hasColumn('addresses', 'company_id')) {
                $table->dropConstrainedForeignId('company_id');
            }
        });

        Schema::table('couriers', function (Blueprint $table) {
            if (Schema::hasColumn('couriers', 'company_id')) {
                $table->dropConstrainedForeignId('company_id');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'company_id')) {
                $table->dropConstrainedForeignId('company_id');
            }
        });
    }
};
