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
        Schema::table('facebook_ad_metrics', function (Blueprint $table) {
            $table->unsignedInteger('reach')->nullable()->after('impressions');
            $table->decimal('frequency', 8, 3)->nullable()->after('reach');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('facebook_ad_metrics', function (Blueprint $table) {
            $table->dropColumn(['reach', 'frequency']);
        });
    }
};
