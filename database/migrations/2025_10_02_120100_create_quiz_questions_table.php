<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->string('quiz_type')->default('audit'); // Type of quiz
            $table->string('quiz_version')->default('1.0'); // Version tracking
            $table->integer('question_number'); // 1, 2, 3, etc.
            $table->string('question_id')->unique(); // 'audit_q1', 'audit_q2', etc.
            
            $table->text('question_text'); // The actual question
            $table->json('options'); // Array of options with scores: [{label: 'Never', score: 0}, ...]
            $table->text('help_text')->nullable(); // Additional guidance (like unit guide)
            
            $table->boolean('required')->default(true);
            $table->boolean('active')->default(true);
            $table->integer('sort_order')->default(0);
            
            $table->timestamps();
            
            // Indexes
            $table->index(['quiz_type', 'quiz_version', 'active']);
            $table->index(['sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_questions');
    }
};
