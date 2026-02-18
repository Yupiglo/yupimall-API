<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_wallet_seller')->default(false)->after('image_url');
            $table->string('wallet_seller_whatsapp')->nullable()->after('is_wallet_seller');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_wallet_seller', 'wallet_seller_whatsapp']);
        });
    }
};
