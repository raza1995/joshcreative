<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipstation_processed_orders', function (Blueprint $table) {
            $table->id();
            $table->string('shipstation_order_id')->unique()->index();
            $table->string('order_number')->index();
            $table->string('order_key')->index();
            
            // What we did
            $table->enum('action', ['consolidated', 'skipped_no_duplicates', 'skipped_shipped', 'failed'])->index();
            $table->integer('items_before')->nullable();
            $table->integer('items_after')->nullable();
            
            // When
            $table->timestamp('processed_at')->index();
            $table->timestamp('last_checked_at')->nullable();
            
            // Details
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipstation_processed_orders');
    }
};

