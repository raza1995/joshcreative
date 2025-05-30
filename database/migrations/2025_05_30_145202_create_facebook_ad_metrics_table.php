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
        Schema::create('facebook_ad_metrics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('facebook_ad_id')->nullable()->index(); // FK to base ad
            $table->string('ad_id')->index(); // Ad ID for quick access
            $table->string('interval'); // 'daily', 'weekly', 'monthly'
            $table->date('date_key');   // Key date (start of range)

            // Metrics
            $table->integer('impressions')->nullable();
            $table->integer('clicks')->nullable();
            $table->decimal('ctr', 8, 2)->nullable();
            $table->decimal('cpc', 8, 2)->nullable();
            $table->decimal('cpm', 8, 2)->nullable();
            $table->decimal('spend', 10, 2)->nullable();
            $table->json('purchase_roas')->nullable();
            $table->integer('conversions')->nullable();
            $table->decimal('cpa', 10, 2)->nullable();
            $table->unsignedInteger('add_to_cart')->nullable();
            $table->unsignedInteger('initiate_checkout')->nullable();
            $table->unsignedInteger('view_content')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facebook_ad_metrics');
    }
};
