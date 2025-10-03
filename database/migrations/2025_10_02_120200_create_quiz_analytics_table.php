<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('quiz_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_session_id')->constrained()->onDelete('cascade');
            
            // Event tracking
            $table->string('event_type'); // 'question_viewed', 'question_answered', 'quiz_started', 'quiz_completed', 'quiz_abandoned'
            $table->string('question_id')->nullable(); // Which question this event relates to
            $table->json('event_data')->nullable(); // Additional event-specific data
            
            // Timing data
            $table->timestamp('event_timestamp');
            $table->integer('time_on_question')->nullable(); // Seconds spent on this question
            
            // User interaction data
            $table->string('user_action')->nullable(); // 'click', 'change', 'submit', etc.
            $table->json('interaction_data')->nullable(); // Mouse movements, scroll data, etc.
            
            $table->timestamps();
            
            // Indexes for analytics queries
            $table->index(['event_type', 'event_timestamp']);
            $table->index(['quiz_session_id', 'event_type']);
            $table->index(['question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_analytics');
    }
};
