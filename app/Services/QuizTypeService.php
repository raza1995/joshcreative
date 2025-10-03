<?php

namespace App\Services;

use App\Models\QuizSession;
use App\Models\QuizQuestion;
use Illuminate\Support\Facades\Config;

class QuizTypeService
{
    /**
     * Get all available quiz types
     */
    public function getAvailableTypes(): array
    {
        return Config::get('quiz-types.types', []);
    }

    /**
     * Get configuration for a specific quiz type
     */
    public function getTypeConfig(string $type): array
    {
        return Config::get("quiz-types.types.{$type}", []);
    }

    /**
     * Get the default quiz type
     */
    public function getDefaultType(): string
    {
        return Config::get('quiz-types.default', 'audit');
    }

    /**
     * Validate if a quiz type exists
     */
    public function isValidType(string $type): bool
    {
        return array_key_exists($type, $this->getAvailableTypes());
    }

    /**
     * Calculate risk level for any quiz type
     */
    public function calculateRiskLevel(QuizSession $session): array
    {
        $config = $this->getTypeConfig($session->quiz_type);
        $score = $session->total_score ?? 0;
        $demographics = $session->demographics ?? [];

        if (empty($config['scoring']['risk_levels'])) {
            return $this->getDefaultRiskLevel($score);
        }

        $riskLevels = $config['scoring']['risk_levels'];
        
        foreach ($riskLevels as $level => $criteria) {
            if ($score >= $criteria['min'] && $score <= $criteria['max']) {
                return [
                    'level' => $level,
                    'label' => $criteria['label'],
                    'color' => $criteria['color'],
                    'message' => $this->getRiskMessage($session->quiz_type, $level, $score, $demographics)
                ];
            }
        }

        return $this->getDefaultRiskLevel($score);
    }

    /**
     * Get risk message based on quiz type and level
     */
    private function getRiskMessage(string $quizType, string $level, int $score, array $demographics): string
    {
        $gender = $demographics['sex'] ?? null;
        
        switch ($quizType) {
            case 'audit':
                return $this->getAuditRiskMessage($level, $score, $gender);
            case 'gad7':
                return $this->getGad7RiskMessage($level, $score);
            case 'phq9':
                return $this->getPhq9RiskMessage($level, $score);
            default:
                return $this->getGenericRiskMessage($level, $score);
        }
    }

    /**
     * Get AUDIT-specific risk messages
     */
    private function getAuditRiskMessage(string $level, int $score, ?string $gender): string
    {
        switch ($level) {
            case 'low':
                if ($score === 0) {
                    return 'Excellent! You appear to be alcohol-free or drink very rarely. This is the healthiest approach.';
                }
                return 'Your drinking pattern suggests low risk for alcohol-related harm. Continue to stay within recommended guidelines.';
                
            case 'increasing':
                $message = 'Your score indicates increasing risk for alcohol-related harm. ';
                $message .= $score >= 12 ? 'Consider reducing your alcohol intake significantly. ' : 'Consider cutting down on your drinking. ';
                $message .= 'Try having 2-3 alcohol-free days per week and avoid binge drinking.';
                return $message;
                
            case 'higher':
                return 'Your score indicates higher risk for alcohol-related problems. It\'s recommended to reduce your drinking and consider speaking with a healthcare professional about your alcohol use.';
                
            case 'dependence':
                $message = 'Your score suggests possible alcohol dependence. ';
                $message .= $score >= 30 
                    ? 'This indicates severe alcohol problems. Please seek professional help immediately from a doctor, counselor, or addiction specialist.'
                    : 'It\'s important to speak with a healthcare professional who can provide proper assessment and support.';
                return $message;
                
            default:
                return 'Please consult with a healthcare professional for proper assessment.';
        }
    }

    /**
     * Get GAD-7 specific risk messages
     */
    private function getGad7RiskMessage(string $level, int $score): string
    {
        switch ($level) {
            case 'minimal':
                return 'Your anxiety levels appear to be minimal. Continue with healthy coping strategies and self-care practices.';
            case 'mild':
                return 'You may be experiencing mild anxiety. Consider stress management techniques, regular exercise, and adequate sleep.';
            case 'moderate':
                return 'Your anxiety levels suggest moderate symptoms. Consider speaking with a healthcare professional or counselor for support and coping strategies.';
            case 'severe':
                return 'Your anxiety levels indicate severe symptoms. It\'s important to seek professional help from a mental health provider for proper assessment and treatment.';
            default:
                return 'Please consult with a healthcare professional for proper assessment.';
        }
    }

    /**
     * Get PHQ-9 specific risk messages
     */
    private function getPhq9RiskMessage(string $level, int $score): string
    {
        switch ($level) {
            case 'minimal':
                return 'Your depression screening suggests minimal symptoms. Continue with healthy lifestyle practices and self-care.';
            case 'mild':
                return 'You may be experiencing mild depression symptoms. Consider lifestyle changes, social support, and monitoring your mood.';
            case 'moderate':
                return 'Your screening suggests moderate depression symptoms. Consider speaking with a healthcare professional for support and treatment options.';
            case 'moderately_severe':
                return 'Your screening indicates moderately severe depression. It\'s recommended to seek professional help from a mental health provider.';
            case 'severe':
                return 'Your screening suggests severe depression symptoms. Please seek immediate professional help from a mental health provider or your doctor.';
            default:
                return 'Please consult with a healthcare professional for proper assessment.';
        }
    }

    /**
     * Get generic risk message for custom quiz types
     */
    private function getGenericRiskMessage(string $level, int $score): string
    {
        return "Your score indicates {$level} level results. Please consult with a healthcare professional for proper assessment and guidance.";
    }

    /**
     * Get default risk level when configuration is missing
     */
    private function getDefaultRiskLevel(int $score): array
    {
        return [
            'level' => 'unknown',
            'label' => 'Assessment Complete',
            'color' => '#6b7280',
            'message' => 'Your assessment has been completed. Please consult with a healthcare professional for proper interpretation of your results.'
        ];
    }

    /**
     * Get questions for a specific quiz type
     */
    public function getQuestions(string $quizType, string $version = '1.0'): array
    {
        return QuizQuestion::where('quiz_type', $quizType)
            ->where('version', $version)
            ->orderBy('question_id')
            ->get()
            ->toArray();
    }

    /**
     * Validate quiz response based on quiz type
     */
    public function validateResponse(string $quizType, int $questionId, int $score): bool
    {
        $config = $this->getTypeConfig($quizType);
        
        // Basic validation - score should be non-negative
        if ($score < 0) {
            return false;
        }

        // For most standardized assessments, max score per question is 4
        $maxQuestionScore = 4;
        
        // Custom validation based on quiz type
        switch ($quizType) {
            case 'audit':
                // AUDIT questions have different max scores
                if (in_array($questionId, [1, 2, 3])) {
                    $maxQuestionScore = 4; // Questions 1-3: 0-4 points
                } else {
                    $maxQuestionScore = 4; // Questions 4-10: 0, 2, 4 points typically
                }
                break;
                
            case 'gad7':
            case 'phq9':
                $maxQuestionScore = 3; // 0-3 scale for GAD-7 and PHQ-9
                break;
                
            default:
                // For custom quizzes, we'll be more lenient
                $maxQuestionScore = 10;
                break;
        }

        return $score <= $maxQuestionScore;
    }

    /**
     * Get analytics configuration for a quiz type
     */
    public function getAnalyticsConfig(string $quizType): array
    {
        $config = $this->getTypeConfig($quizType);
        return $config['analytics'] ?? [];
    }

    /**
     * Get demographic configuration for a quiz type
     */
    public function getDemographicConfig(string $quizType): array
    {
        $config = $this->getTypeConfig($quizType);
        return $config['demographics'] ?? [];
    }

    /**
     * Generate quiz-specific insights
     */
    public function generateInsights(string $quizType, array $sessions): array
    {
        $config = $this->getTypeConfig($quizType);
        $insights = [];

        // Calculate type-specific metrics
        switch ($quizType) {
            case 'audit':
                $insights = $this->generateAuditInsights($sessions);
                break;
            case 'gad7':
                $insights = $this->generateGad7Insights($sessions);
                break;
            case 'phq9':
                $insights = $this->generatePhq9Insights($sessions);
                break;
            default:
                $insights = $this->generateGenericInsights($sessions);
                break;
        }

        return $insights;
    }

    /**
     * Generate AUDIT-specific insights
     */
    private function generateAuditInsights(array $sessions): array
    {
        $insights = [];
        
        // Calculate binge drinking indicators (questions 3, 4, 5)
        $bingeDrinkingScores = [];
        foreach ($sessions as $session) {
            $responses = $session['responses'] ?? [];
            $bingeScore = ($responses[3] ?? 0) + ($responses[4] ?? 0) + ($responses[5] ?? 0);
            $bingeDrinkingScores[] = $bingeScore;
        }
        
        $insights['binge_drinking_average'] = count($bingeDrinkingScores) > 0 ? round(array_sum($bingeDrinkingScores) / count($bingeDrinkingScores), 1) : 0;
        $insights['high_binge_risk'] = count(array_filter($bingeDrinkingScores, fn($score) => $score >= 6));
        
        return $insights;
    }

    /**
     * Generate GAD-7 specific insights
     */
    private function generateGad7Insights(array $sessions): array
    {
        $insights = [];
        
        // Analyze worry patterns (question 1)
        $worryScores = [];
        foreach ($sessions as $session) {
            $responses = $session['responses'] ?? [];
            $worryScores[] = $responses[1] ?? 0;
        }
        
        $insights['worry_average'] = count($worryScores) > 0 ? round(array_sum($worryScores) / count($worryScores), 1) : 0;
        $insights['high_worry'] = count(array_filter($worryScores, fn($score) => $score >= 2));
        
        return $insights;
    }

    /**
     * Generate PHQ-9 specific insights
     */
    private function generatePhq9Insights(array $sessions): array
    {
        $insights = [];
        
        // Analyze mood patterns (questions 1, 2)
        $moodScores = [];
        foreach ($sessions as $session) {
            $responses = $session['responses'] ?? [];
            $moodScore = ($responses[1] ?? 0) + ($responses[2] ?? 0);
            $moodScores[] = $moodScore;
        }
        
        $insights['mood_average'] = count($moodScores) > 0 ? round(array_sum($moodScores) / count($moodScores), 1) : 0;
        $insights['low_mood_risk'] = count(array_filter($moodScores, fn($score) => $score >= 4));
        
        return $insights;
    }

    /**
     * Generate generic insights for custom quiz types
     */
    private function generateGenericInsights(array $sessions): array
    {
        return [
            'total_sessions' => count($sessions),
            'completed_sessions' => count(array_filter($sessions, fn($s) => $s['completed'] ?? false)),
            'average_score' => count($sessions) > 0 ? round(array_sum(array_column($sessions, 'total_score')) / count($sessions), 1) : 0,
        ];
    }
}
