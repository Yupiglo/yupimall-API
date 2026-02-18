<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     * Removes the CHECK constraint on payment_method to allow any string value
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_payment_method_check');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Optionally recreate the constraint with expanded values
        // Not adding it back since we want flexibility
    }
};
