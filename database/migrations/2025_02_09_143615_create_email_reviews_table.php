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
        Schema::create('email_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('draft_id')->nullable();
            $table->unsignedBigInteger('reviewer_id')->nullable(); // Assuming a users table exists
            $table->enum('status', ['approved', 'disapproved']);
            $table->text('feedback')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_reviews');
    }
};
