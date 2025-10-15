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
        Schema::create('shipstation_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_type')->nullable();
            $table->string('resource_url')->nullable();
            $table->string('resource_type')->nullable();
            $table->string('order_id')->nullable();
            $table->string('order_number')->nullable();
            $table->json('raw_request')->nullable(); // Full webhook request payload
            $table->json('headers')->nullable(); // Request headers
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('status')->default('pending'); // pending, processing, success, failed
            $table->json('processing_result')->nullable(); // Result of processing
            $table->text('error_message')->nullable();
            $table->text('error_trace')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->integer('processing_time_ms')->nullable();
            $table->timestamps();
            
            // Indexes for faster queries
            $table->index('event_type');
            $table->index('order_number');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipstation_webhook_logs');
    }
};
