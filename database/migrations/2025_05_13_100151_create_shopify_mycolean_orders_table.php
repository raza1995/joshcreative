<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shopify_mycolean_orders', function (Blueprint $table) {
            $table->id();
          
            $table->string('order_id');
            $table->string('product_title');
            $table->string('variant_id')->nullable();
            $table->integer('quantity');
            $table->decimal('total_price', 10, 2);
            $table->date('order_date');
         
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopify_mycolean_orders');
    }
};
