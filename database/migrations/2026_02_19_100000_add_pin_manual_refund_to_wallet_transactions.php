<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE wallet_transactions MODIFY COLUMN reference_type ENUM('pin_generation','pin_usage','pin_expiry','pin_remainder','pin_manual_refund','recharge','treasury')");
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE wallet_transactions DROP CONSTRAINT IF EXISTS wallet_transactions_reference_type_check');
            DB::statement("ALTER TABLE wallet_transactions ADD CONSTRAINT wallet_transactions_reference_type_check CHECK (reference_type::text = ANY (ARRAY['pin_generation','pin_usage','pin_expiry','pin_remainder','pin_manual_refund','recharge','treasury']::text[]))");
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE wallet_transactions MODIFY COLUMN reference_type ENUM('pin_generation','pin_usage','pin_expiry','pin_remainder','recharge','treasury')");
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE wallet_transactions DROP CONSTRAINT IF EXISTS wallet_transactions_reference_type_check');
            DB::statement("ALTER TABLE wallet_transactions ADD CONSTRAINT wallet_transactions_reference_type_check CHECK (reference_type::text = ANY (ARRAY['pin_generation','pin_usage','pin_expiry','pin_remainder','recharge','treasury']::text[]))");
        }
    }
};
