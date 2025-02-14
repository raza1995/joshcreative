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
        Schema::create('email_drafts', function (Blueprint $table) {
            $table->id();
            $table->string('email_id')->nullable(); // Gmail message ID
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->enum('status', ['pending', 'approved', 'disapproved','draft'])->default('pending');
            $table->string('shopify_order_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_drafts');
    }
};
