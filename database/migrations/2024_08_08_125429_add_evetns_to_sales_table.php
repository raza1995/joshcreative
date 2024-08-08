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
        Schema::table('UserEvent', function (Blueprint $table) {
            $table->string('event_type')->nullable();
            $table->string('element')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('UserEvent', function (Blueprint $table) {
            Schema::dropIfExists('event_type');
            Schema::dropIfExists('element');
        });
    }
};
