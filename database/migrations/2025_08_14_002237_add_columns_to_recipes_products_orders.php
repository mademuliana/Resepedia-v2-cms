<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // recipes → add notes
        Schema::table('recipes', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('total_cost_per_portion');
        });

        // products → add notes
        Schema::table('products', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('total_calorie');
        });

        // orders → add ordered_at, required_at, customer_email, deposit_required, deposit_amount, notes
        Schema::table('orders', function (Blueprint $table) {
            $table->dateTime('ordered_at')->nullable()->after('status');
            $table->dateTime('required_at')->nullable()->after('ordered_at');
            $table->string('customer_email')->nullable()->after('customer_phone');
            $table->boolean('deposit_required')->default(false)->after('total_calorie');
            $table->decimal('deposit_amount', 12, 2)->nullable()->after('deposit_required');
            $table->text('notes')->nullable()->after('deposit_amount');
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn('notes');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('notes');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'ordered_at',
                'required_at',
                'customer_email',
                'deposit_required',
                'deposit_amount',
                'notes',
            ]);
        });
    }
};
