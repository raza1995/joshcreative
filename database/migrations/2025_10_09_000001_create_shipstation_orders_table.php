<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipstation_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shopify_order_id')->index();
            $table->string('order_number')->index();
            $table->string('order_key')->unique()->comment('ShipStation order key');
            
            // Customer info
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            
            // Order details
            $table->decimal('order_total', 10, 2)->default(0);
            $table->decimal('shipping_amount', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            
            // Shipping address (flatten for ShipStation)
            $table->string('ship_name')->nullable();
            $table->string('ship_company')->nullable();
            $table->string('ship_street1')->nullable();
            $table->string('ship_street2')->nullable();
            $table->string('ship_city')->nullable();
            $table->string('ship_state')->nullable();
            $table->string('ship_postal_code')->nullable();
            $table->string('ship_country')->nullable();
            $table->string('ship_phone')->nullable();
            
            // Status tracking
            $table->enum('consolidation_status', ['pending', 'consolidated', 'pushed', 'failed'])->default('pending');
            $table->string('shipstation_order_id')->nullable()->comment('ShipStation internal order ID');
            $table->timestamp('pushed_at')->nullable();
            $table->text('push_error')->nullable();
            
            // Metadata
            $table->json('original_line_items')->nullable()->comment('Original Shopify line items before consolidation');
            $table->json('shopify_raw')->nullable()->comment('Full Shopify order payload');
            
            $table->timestamps();
            
            // Foreign key
            $table->foreign('shopify_order_id')
                  ->references('id')
                  ->on('shopify_orders')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipstation_orders');
    }
};

