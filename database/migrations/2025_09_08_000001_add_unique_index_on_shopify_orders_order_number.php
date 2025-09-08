<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure there are no duplicates before running this migration
        Schema::table('shopify_orders', function (Blueprint $table) {
            $table->unique('order_number', 'shopify_orders_order_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('shopify_orders', function (Blueprint $table) {
            $table->dropUnique('shopify_orders_order_number_unique');
        });
    }
};

