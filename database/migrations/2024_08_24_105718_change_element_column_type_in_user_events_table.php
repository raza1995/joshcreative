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
        Schema::table('user_events', function (Blueprint $table) {
            $table->text('element')->nullable()->change(); // Temporarily allow NULLs
        });

        // Then, change the column type to longText
        Schema::table('user_events', function (Blueprint $table) {
            $table->longText('element')->nullable()->change(); // Change the column type to longText
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_events', function (Blueprint $table) {
            $table->string('element', 255)->nullable()->change(); // Revert back to the original string type

        });
    }
};
