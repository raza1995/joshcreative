<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('shopify_orders', function (Blueprint $table) {
            $table->foreignId('email_draft_id')->nullable()->constrained('email_drafts')->onDelete('cascade')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shopify_orders', function (Blueprint $table) {
            $table->dropForeign(['email_draft_id']);
            $table->dropColumn('email_draft_id');
        });
    }
};
