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
        Schema::table('email_drafts', function (Blueprint $table) {
            $table->longText('original_email')->nullable()->after('email_id');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_drafts', function (Blueprint $table) {
            $table->dropColumn('original_email');

        });
    }
};
