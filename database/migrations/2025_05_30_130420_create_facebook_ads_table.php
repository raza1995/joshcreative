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
        Schema::create('facebook_ads', function (Blueprint $table) {
            $table->id();
            $table->string('ad_account_name')->nullable();
            $table->string('ad_id')->unique();
            $table->string('campaign_id')->nullable();
            $table->string('campaign_name')->nullable();
            $table->string('adset_id')->nullable();
            $table->string('adset_name')->nullable();
            $table->string('ad_name')->nullable();
            $table->string('ad_type')->nullable();
            $table->string('ad_link')->nullable();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->text('description')->nullable();
            $table->text('thumbnail_url')->nullable();
            $table->string('video_id')->nullable();
            $table->text('image_url')->nullable();
            $table->text('link_url')->nullable();
            $table->text('display_url')->nullable();
            $table->string('call_to_action')->nullable();
            $table->integer('impressions')->nullable();
            $table->integer('clicks')->nullable();
            $table->decimal('ctr', 8, 2)->nullable();
            $table->decimal('cpc', 8, 2)->nullable();
            $table->decimal('cpm', 8, 2)->nullable();
            $table->decimal('spend', 10, 2)->nullable();
            $table->string('status')->nullable();
            $table->timestamp('updated_time')->nullable();
            $table->json('purchase_roas')->nullable();
            $table->integer('conversions')->nullable();
            $table->decimal('cpa', 10, 2)->nullable();
            $table->text('video_url')->nullable();
            $table->text('full_picture')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facebook_ads');
    }
};
