<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('quiz_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_uuid')->unique(); // Unique identifier for each quiz session
            $table->string('quiz_type')->default('audit'); // Type of quiz (audit, future quiz types)
            $table->string('quiz_version')->default('1.0'); // Version tracking for quiz changes
            
            // User identification (optional, for anonymous quizzes)
            $table->string('user_email')->nullable();
            $table->string('user_ip')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('referrer_url')->nullable();
            $table->string('shopify_domain')->nullable(); // If embedded in Shopify
            
            // Demographics data (stored as JSON for flexibility)
            $table->json('demographics')->nullable(); // {sex: 'female', age: '25-34', etc}
            
            // Quiz responses (stored as JSON for flexibility)
            $table->json('responses')->nullable(); // {1: 2, 2: 1, 3: 0, etc} - question_id: score
            
            // Calculated results
            $table->integer('total_score')->nullable();
            $table->string('risk_level')->nullable(); // 'low', 'increasing', 'higher', 'dependence'
            $table->text('risk_message')->nullable();
            
            // Session metadata
            $table->boolean('completed')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('time_taken_seconds')->nullable(); // Time to complete
            
            // Tracking fields
            $table->json('page_views')->nullable(); // Track which questions were viewed
            $table->json('session_metadata')->nullable(); // Any additional data
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['quiz_type', 'completed']);
            $table->index(['created_at']);
            $table->index(['shopify_domain']);
            $table->index(['risk_level']);
            $table->index(['user_email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_sessions');
    }
};
