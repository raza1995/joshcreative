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
        Schema::create('shopify_event_logs', function (Blueprint $table) {
            $table->id();
            $table->string('anon_id')->index();
            $table->string('event_type');
            $table->string('funnel_stage')->nullable();
            $table->text('element')->nullable();
            $table->string('page_url');
            $table->string('page_type')->nullable();
            $table->string('referrer')->nullable();
            $table->timestamp('timestamp')->nullable();
            $table->float('focus_time')->nullable();
            $table->json('utm')->nullable();
            $table->json('screen')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('platform')->default('shopify');
            $table->timestamps();
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopify_event_logs');
    }
};
