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
            $table->string('subcategory')->nullable()->after('category');
            $table->unsignedBigInteger('category_id')->nullable()->after('subcategory');
            $table->unsignedBigInteger('subcategory_id')->nullable()->after('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['subcategory', 'category_id', 'subcategory_id']);
        });
    }
};
