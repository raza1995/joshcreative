<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shopify_product_id')->unique()->index();
            $table->unsignedBigInteger('shopify_variant_id')->nullable()->index();
            $table->string('title');
            $table->string('handle')->nullable();
            $table->string('sku')->nullable()->index();
            $table->string('barcode')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('compare_at_price', 10, 2)->nullable();
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->decimal('weight', 10, 3)->nullable();
            $table->string('weight_unit')->nullable();
            $table->integer('inventory_quantity')->nullable();
            $table->string('inventory_policy')->nullable();
            $table->string('inventory_management')->nullable();
            $table->string('fulfillment_service')->nullable();
            $table->string('product_type')->nullable();
            $table->string('vendor')->nullable();
            $table->json('tags')->nullable();
            $table->json('options')->nullable();
            $table->string('status')->nullable();
            $table->boolean('requires_shipping')->default(true);
            $table->boolean('taxable')->default(true);
            $table->string('tax_code')->nullable();
            $table->json('images')->nullable();
            $table->text('body_html')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->json('metafields')->nullable();
            $table->json('raw_data')->nullable()->comment('Full product data from Shopify API');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['sku', 'status']);
            $table->index(['shopify_product_id', 'shopify_variant_id']);
            $table->index('last_synced_at');
            $table->index('product_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_products');
    }
};