<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAnalyticsSchema extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Devices Dimension Table
        Schema::create('devices_dim', function (Blueprint $table) {
            $table->id();
            $table->uuid('device_uuid')->unique();
            $table->timestamps();
        });

        // Referring Domains Dimension Table
        Schema::create('ref_domains_dim', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique(); // e.g., m.facebook.com, google.com
        });

        // Products Dimension Table
        Schema::create('products_dim', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('shopify_product_id')->nullable();
            $table->bigInteger('shopify_variant_id')->nullable();
            $table->string('title');
            $table->string('sku')->nullable();
            $table->decimal('list_price', 10, 2)->nullable();
            $table->timestamps(); // for tracking price change history
        });

        // Fact Events Table
        Schema::create('fact_events', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->foreignId('device_id')->constrained('devices_dim');
            $table->foreignId('ref_domain_id')->nullable()->constrained('ref_domains_dim');
            $table->foreignId('product_id')->nullable()->constrained('products_dim');

            $table->enum('event_type', ['view', 'atc', 'cko', 'buy']);
            $table->timestamp('event_ts')->index();

            $table->bigInteger('order_id')->nullable();
            $table->decimal('revenue', 10, 2)->nullable();
            $table->unsignedInteger('qty')->default(1);

            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_term')->nullable();

            $table->json('raw_props')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fact_events');
        Schema::dropIfExists('products_dim');
        Schema::dropIfExists('ref_domains_dim');
        Schema::dropIfExists('devices_dim');
    }
}
