<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Drop foreign key from email_reviews
        Schema::table('email_reviews', function (Blueprint $table) {
            $table->dropForeign(['draft_id']);
        });

        // Now you can truncate the email_drafts table safely
        \DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        \DB::table('email_drafts')->truncate();
        \DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    public function down(): void
    {
        // Restore the foreign key constraint
        Schema::table('email_reviews', function (Blueprint $table) {
            $table->foreign('draft_id')
                ->references('id')
                ->on('email_drafts')
                ->onDelete('cascade');
        });
    }
};
