<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Drop known foreign keys from shopify_orders
        Schema::table('shopify_orders', function (Blueprint $table) {
            // Drop individual foreign keys by their column names
            $table->dropForeign(['email_draft_id']);
        });
    }

    public function down(): void
    {
        Schema::table('shopify_orders', function (Blueprint $table) {
            // Recreate the foreign keys
            $table->foreign('email_draft_id')->constrained('email_drafts')->onDelete('cascade');
        });
    }
};
