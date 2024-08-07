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
        Schema::table('pages', function (Blueprint $table) {
            $table->integer('focus_time')->default(0)->after('total_stay_duration')->comment('Time in seconds');
        });

        Schema::table('user_events', function (Blueprint $table) {
            $table->integer('focus_time')->default(0)->after('end_time')->comment('Time in seconds');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('focus_time');
        });

        Schema::table('user_events', function (Blueprint $table) {
            $table->dropColumn('focus_time');
        });
    }
};
