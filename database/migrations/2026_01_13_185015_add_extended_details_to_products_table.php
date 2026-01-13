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
        Schema::table('products', function (Blueprint $table) {
            $table->json('benefits')->nullable()->after('variants');
            $table->json('ingredients')->nullable()->after('benefits');
            $table->text('how_to_use')->nullable()->after('ingredients');
            $table->text('clinical_research')->nullable()->after('how_to_use');
            $table->json('reviews')->nullable()->after('clinical_research');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['benefits', 'ingredients', 'how_to_use', 'clinical_research', 'reviews']);
        });
    }
};
