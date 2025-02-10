<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('email_drafts', function (Blueprint $table) {
            $table->boolean('auto_sent')->default(false);
            $table->float('ai_confidence')->nullable();
            $table->string('ai_decision_reason')->nullable();
            $table->enum('tone_check', ['Pass', 'Fail'])->nullable();
            $table->enum('content_check', ['Pass', 'Fail'])->nullable();
            $table->enum('risk_assessment', ['Low', 'Medium', 'High'])->nullable();
            $table->enum('policy_compliance', ['Pass', 'Fail'])->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('email_drafts', function (Blueprint $table) {
            $table->dropColumn([
                'auto_sent',
                'ai_confidence',
                'ai_decision_reason',
                'tone_check',
                'content_check',
                'risk_assessment',
                'policy_compliance',
            ]);
        });
    }
};
