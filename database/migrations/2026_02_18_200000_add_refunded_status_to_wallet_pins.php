<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE wallet_pins MODIFY COLUMN status ENUM('active', 'used', 'expired', 'refunded') DEFAULT 'active'");
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE wallet_pins DROP CONSTRAINT IF EXISTS wallet_pins_status_check');
            DB::statement("ALTER TABLE wallet_pins ADD CONSTRAINT wallet_pins_status_check CHECK (status::text = ANY (ARRAY['active','used','expired','refunded']::text[]))");
        }
        // SQLite: varchar column, 'refunded' accepted without migration change
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE wallet_pins MODIFY COLUMN status ENUM('active', 'used', 'expired') DEFAULT 'active'");
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE wallet_pins DROP CONSTRAINT IF EXISTS wallet_pins_status_check');
            DB::statement("ALTER TABLE wallet_pins ADD CONSTRAINT wallet_pins_status_check CHECK (status::text = ANY (ARRAY['active','used','expired']::text[]))");
        }
    }
};
