<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->unsignedBigInteger('wallet_pin_id')->nullable()->after('payment_status');
            $table->foreign('wallet_pin_id')->references('id')->on('wallet_pins')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropForeign(['wallet_pin_id']);
            $table->dropColumn('wallet_pin_id');
        });
    }
};
