<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('wallet_pins', function (Blueprint $table) {
            $table->unsignedBigInteger('used_by_registration_id')->nullable()->after('used_by_order_id');
            $table->foreign('used_by_registration_id')->references('id')->on('registrations')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_pins', function (Blueprint $table) {
            $table->dropForeign(['used_by_registration_id']);
            $table->dropColumn('used_by_registration_id');
        });
    }
};
