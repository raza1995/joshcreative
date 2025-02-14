<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('feedback_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('email_reviews')->onDelete('cascade');
            $table->string('reason'); // e.g., "Tone Issue", "Policy Error"
            $table->text('comments')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('feedback_logs', function (Blueprint $table) {
            $table->dropForeign(['review_id']);
        });
        Schema::dropIfExists('feedback_logs');
    }
};
