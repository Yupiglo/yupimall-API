<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('orders', 'payment_proof')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('payment_proof')->nullable()->after('payment_method');
            });
        }

        // Use raw SQL to update the enum because Doctrine DBAL doesn't support changing enum values easily
        // and we want avoid installing dependencies if possible.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY COLUMN order_status ENUM('pending', 'validated', 'reached_warehouse', 'shipped_to_stockist', 'reached_stockist', 'out_for_delivery', 'delivered', 'canceled', 'processing', 'shipped') DEFAULT 'pending'");
        } elseif (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE orders ALTER COLUMN order_status TYPE VARCHAR(255)");
            DB::statement("ALTER TABLE orders ALTER COLUMN order_status SET DEFAULT 'pending'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('payment_proof');
        });

        // Revert enum (simplified)
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY COLUMN order_status ENUM('processing', 'pending', 'shipped', 'delivered', 'canceled') DEFAULT 'pending'");
        }
    }
};
