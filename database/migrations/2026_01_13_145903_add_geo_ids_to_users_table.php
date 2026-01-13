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
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('country_id')->nullable()->after('role');
            $table->uuid('region_id')->nullable()->after('country_id');

            $table->foreign('country_id')->references('id')->on('countries')->onDelete('set null');
            $table->foreign('region_id')->references('id')->on('regions')->onDelete('set null');
        });

        // Migration des données "GLOBAL" existantes
        DB::table('users')->where('country', 'GLOBAL')->update(['country_id' => null]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('country');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
