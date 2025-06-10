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
        Schema::create('facebook_ad_stats', function (Blueprint $table) {
            $table->id();
          $table->foreignId('facebook_ad_id')->constrained()->onDelete('cascade');

    $table->string('ad_id')->index();
    $table->string('campaign_name')->nullable();
    $table->string('adset_name')->nullable();
    $table->string('ad_name')->nullable();
    $table->string('ad_type')->nullable();
    $table->string('ad_account_name')->nullable();

    $table->text('ad_link')->nullable();
    $table->text('link_url')->nullable();
    $table->text('thumbnail_url')->nullable();
    $table->text('status')->nullable();
    $table->timestamp('updated_time')->nullable();

    $table->string('interval');
    $table->string('date_key');

    $table->integer('order_count')->default(0);
    $table->float('spend')->default(0);
    $table->unsignedBigInteger('clicks')->default(0);
    $table->unsignedBigInteger('impressions')->default(0);
    $table->float('ctr')->nullable();
    $table->float('cpa')->nullable();
    $table->float('roas')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facebook_ad_stats');
    }
};
