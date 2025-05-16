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
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');         // Foreign key (internal products table)
            $table->unsignedBigInteger('variant_id')->unique(); // Shopify Variant ID
            $table->string('title');
            $table->string('sku')->nullable();
            $table->decimal('price', 10, 2)->nullable();       // Selling price
            $table->decimal('cost', 10, 2)->nullable();        // Cost per item (COGS)
            $table->decimal('profit', 10, 2)->nullable();
            $table->decimal('margin', 5, 2)->nullable();
            $table->timestamps();
        
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
