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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('level')->default('info'); // info, warning, error, success
            $table->string('action'); // ex: "Product Created"
            $table->text('description'); // ex: "L'admin X a modifié le prix du produit Y"
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->json('payload')->nullable(); // Optionnel pour stocker les changements précis
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
