<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipstation_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shipstation_order_id')->nullable()->index();
            $table->string('shopify_order_number')->nullable()->index();
            
            // Action tracking
            $table->enum('action', ['consolidate', 'push', 'update', 'error'])->index();
            $table->enum('status', ['success', 'failed', 'pending'])->default('pending');
            
            // Details
            $table->text('message')->nullable();
            $table->json('metadata')->nullable()->comment('Transformation details, API response, etc');
            
            // Performance metrics
            $table->integer('items_before')->nullable();
            $table->integer('items_after')->nullable();
            $table->integer('api_response_time_ms')->nullable();
            $table->string('api_status_code')->nullable();
            
            // Error tracking
            $table->text('error_message')->nullable();
            $table->json('error_trace')->nullable();
            
            $table->timestamps();
            
            // Foreign key (nullable because log might exist even if order creation failed)
            $table->foreign('shipstation_order_id')
                  ->references('id')
                  ->on('shipstation_orders')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipstation_sync_logs');
    }
};

