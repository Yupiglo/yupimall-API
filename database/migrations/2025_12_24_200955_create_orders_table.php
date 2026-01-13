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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');

            $table->string('shipping_street')->nullable();
            $table->string('shipping_phone')->nullable();
            $table->string('distributor_id')->nullable();

            $table->enum('order_status', ['processing', 'pending', 'shipped', 'delivered', 'canceled'])->default('processing');
            $table->enum('payment_method', ['card', 'cash', 'wallet', 'moneroo', 'axazara'])->default('cash');
            $table->boolean('is_paid')->default(false);
            $table->boolean('is_delivered')->default(false);

            $table->decimal('total_order_price', 12, 2)->default(0);

            $table->dateTime('order_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('delivered_at')->nullable();

            $table->timestamps();

            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
