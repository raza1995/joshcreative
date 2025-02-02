<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shopify_orders', function (Blueprint $table) {
            $table->id();
            $table->string('product_name')->nullable();
            $table->dateTime('order_date')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('order_number')->nullable();
            $table->string('email_address')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('tracking_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_orders');
    }
};
