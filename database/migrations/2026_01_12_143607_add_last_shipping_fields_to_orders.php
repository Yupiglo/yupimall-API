<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'shipping_name')) {
                $table->string('shipping_name')->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('orders', 'shipping_city')) {
                $table->string('shipping_city')->nullable()->after('shipping_street');
            }
            if (!Schema::hasColumn('orders', 'shipping_country')) {
                $table->string('shipping_country')->nullable()->after('shipping_city');
            }
            if (!Schema::hasColumn('orders', 'shipping_zip')) {
                $table->string('shipping_zip')->nullable()->after('shipping_country');
            }
            if (!Schema::hasColumn('orders', 'shipping_email')) {
                $table->string('shipping_email')->nullable()->after('shipping_zip');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['shipping_name', 'shipping_city', 'shipping_country', 'shipping_zip', 'shipping_email']);
        });
    }
};
