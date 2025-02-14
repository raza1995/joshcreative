<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('feedback_logs', function (Blueprint $table) {
            // Drop the foreign key constraint
            $table->dropForeign(['review_id']);
            
            // Optional: Drop the column if needed
            // $table->dropColumn('review_id');
        });
    }

    public function down(): void
    {
        Schema::table('feedback_logs', function (Blueprint $table) {
            // Restore the foreign key constraint
            $table->foreign('review_id')->constrained('email_reviews')->onDelete('cascade');
        });
    }
};
