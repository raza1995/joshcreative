<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopify_orders', function (Blueprint $table) {
            $table->string('utm_id')->nullable()->index();
            $table->string('campaign_id')->nullable()->index();
            $table->string('gclid')->nullable()->index();
            $table->string('fbclid')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('shopify_orders', function (Blueprint $table) {
            $table->dropColumn(['utm_id','campaign_id','gclid','fbclid']);
        });
    }
};

