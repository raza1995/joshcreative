<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('manual_reviews', function (Blueprint $table) {
            // Drop the foreign key constraint
            // $table->dropForeign(['assigned_to']);
        });
    }

    public function down(): void
    {
        Schema::table('manual_reviews', function (Blueprint $table) {
            // Restore the foreign key constraint
            $table->foreign('assigned_to')->constrained('users')->onDelete('set null');
        });
    }
};
