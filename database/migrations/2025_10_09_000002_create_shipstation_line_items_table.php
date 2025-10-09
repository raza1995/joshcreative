<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipstation_line_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shipstation_order_id')->index();
            
            // Product identification
            $table->string('sku')->index();
            $table->string('name')->nullable();
            $table->integer('quantity')->default(1);
            
            // Pricing (after consolidation)
            $table->decimal('unit_price', 10, 2)->default(0)->comment('Weighted average if consolidated');
            $table->decimal('line_total', 10, 2)->default(0);
            $table->decimal('line_discount', 10, 2)->default(0)->comment('Total discount for this line');
            
            // Consolidation tracking
            $table->boolean('is_consolidated')->default(false);
            $table->integer('original_line_count')->default(1)->comment('How many Shopify lines merged into this');
            $table->json('original_line_ids')->nullable()->comment('Array of original Shopify line_item IDs');
            $table->json('price_breakdown')->nullable()->comment('Individual prices/qtys before merge');
            
            // Product metadata
            $table->string('image_url')->nullable();
            $table->decimal('weight', 10, 2)->nullable();
            $table->string('weight_unit')->nullable();
            
            $table->timestamps();
            
            // Foreign key
            $table->foreign('shipstation_order_id')
                  ->references('id')
                  ->on('shipstation_orders')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipstation_line_items');
    }
};

