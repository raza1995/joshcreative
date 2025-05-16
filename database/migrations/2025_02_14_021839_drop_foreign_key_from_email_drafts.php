<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Drop the foreign key from email_drafts
        Schema::table('email_drafts', function (Blueprint $table) {
            // $table->dropForeign(['shopify_order_id']);
        });

        // Truncate the table
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('email_drafts')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    public function down(): void
    {
        // Restore the foreign key constraint
        Schema::table('email_drafts', function (Blueprint $table) {
            $table->foreign('shopify_order_id')
                ->references('id')
                ->on('shopify_orders')
                ->onDelete('cascade');
        });
    }
};
