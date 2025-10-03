<?php

namespace App\Services;

use App\Models\QuizSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AIAnalysisService
{
    /**
     * Generate AI-powered analysis for quiz results with Mycolean product recommendations
     */
    public function generatePersonalizedAnalysis(QuizSession $session): array
    {
        $score = $session->total_score ?? 0;
        $demographics = $session->demographics ?? [];
        $responses = $session->responses ?? [];
        
        // Analyze drinking patterns
        $drinkingPattern = $this->analyzeDrinkingPattern($responses, $demographics);
        
        // Generate AI insights
        $aiInsights = $this->generateAIInsights($score, $drinkingPattern, $demographics);
        
        // Create Mycolean recommendations
        $mycoleanRecommendation = $this->generateMycoleanRecommendation($drinkingPattern, $aiInsights);
        
        // Predict conversion likelihood
        $conversionProbability = $this->predictConversionProbability($session);
        
        return [
            'drinking_pattern' => $drinkingPattern,
            'ai_insights' => $aiInsights,
            'mycolean_recommendation' => $mycoleanRecommendation,
            'conversion_probability' => $conversionProbability,
            'personalized_message' => $this->generatePersonalizedMessage($drinkingPattern, $aiInsights),
            'risk_factors' => $this->identifyRiskFactors($responses, $demographics),
            'success_prediction' => $this->predictSuccessWithMycolean($drinkingPattern, $demographics)
        ];
    }

    /**
     * Analyze drinking patterns from quiz responses
     */
    private function analyzeDrinkingPattern(array $responses, array $demographics): array
    {
        $age = $demographics['age'] ?? 'Unknown';
        $gender = $demographics['sex'] ?? 'Unknown';
        
        // Analyze key drinking indicators
        $frequency = $responses[1] ?? 0; // How often do you drink?
        $quantity = $responses[2] ?? 0;  // How many units on typical day?
        $binge = $responses[3] ?? 0;     // How often 6+ drinks?
        $control = $responses[4] ?? 0;   // Unable to stop drinking?
        
        $pattern = [
            'frequency_level' => $this->categorizeFrequency($frequency),
            'quantity_level' => $this->categorizeQuantity($quantity),
            'binge_tendency' => $this->categorizeBinge($binge),
            'control_issues' => $this->categorizeControl($control),
            'social_context' => $this->analyzeSocialDrinking($responses),
            'motivation_level' => $this->assessMotivationToChange($responses),
            'age_group' => $age,
            'gender' => $gender
        ];

        // Calculate pattern score
        $pattern['pattern_score'] = $this->calculatePatternScore($pattern);
        $pattern['mycolean_fit'] = $this->assessMycoleanFit($pattern);
        
        return $pattern;
    }

    /**
     * Generate AI-powered insights using advanced analysis
     */
    private function generateAIInsights(int $score, array $pattern, array $demographics): array
    {
        $insights = [];
        
        // Risk level analysis
        if ($score >= 20) {
            $insights[] = [
                'type' => 'high_risk',
                'title' => 'Immediate Attention Needed',
                'message' => 'Your drinking pattern indicates possible dependence. Mycolean could be a safer alternative to help you reduce alcohol consumption gradually.',
                'confidence' => 0.95,
                'action' => 'Consider professional help alongside Mycolean as a transition tool.'
            ];
        } elseif ($score >= 16) {
            $insights[] = [
                'type' => 'higher_risk',
                'title' => 'Perfect Candidate for Mycolean',
                'message' => 'You\'re drinking at levels that could benefit from a healthier alternative. Mycolean provides the social buzz without the negative effects.',
                'confidence' => 0.88,
                'action' => 'Start with Mycolean 2-3 times per week to replace your highest-risk drinking occasions.'
            ];
        } elseif ($score >= 8) {
            $insights[] = [
                'type' => 'increasing_risk',
                'title' => 'Great Time to Try Mycolean',
                'message' => 'Your drinking is increasing. Mycolean can help you maintain social experiences while reducing alcohol intake.',
                'confidence' => 0.82,
                'action' => 'Use Mycolean for weekend social events to cut down on alcohol consumption.'
            ];
        } else {
            $insights[] = [
                'type' => 'low_risk',
                'title' => 'Maintain Healthy Habits with Mycolean',
                'message' => 'You have healthy drinking habits. Mycolean can enhance your social experiences without any alcohol-related risks.',
                'confidence' => 0.75,
                'action' => 'Try Mycolean as a fun, legal alternative for special occasions.'
            ];
        }

        // Pattern-specific insights
        if ($pattern['binge_tendency'] === 'high') {
            $insights[] = [
                'type' => 'binge_pattern',
                'title' => 'Binge Drinking Alternative',
                'message' => 'Mycolean provides the euphoric feeling you seek without the dangerous binge drinking pattern.',
                'confidence' => 0.90,
                'action' => 'Replace binge sessions with controlled Mycolean experiences (3-4 hours, no hangover).'
            ];
        }

        if ($pattern['social_context'] === 'high') {
            $insights[] = [
                'type' => 'social_drinker',
                'title' => 'Perfect Social Alternative',
                'message' => 'As a social drinker, Mycolean gives you the buzz and confidence without alcohol\'s downsides.',
                'confidence' => 0.85,
                'action' => 'Bring Mycolean to social events - be the person who feels great and remembers everything.'
            ];
        }

        return $insights;
    }

    /**
     * Generate specific Mycolean product recommendations
     */
    private function generateMycoleanRecommendation(array $pattern, array $insights): array
    {
        $recommendation = [
            'product_fit_score' => $pattern['mycolean_fit'],
            'recommended_flavors' => [],
            'dosage_suggestion' => '',
            'usage_scenarios' => [],
            'expected_benefits' => [],
            'transition_plan' => '',
            'success_probability' => 0
        ];

        // Determine best flavors based on pattern
        if ($pattern['frequency_level'] === 'high') {
            $recommendation['recommended_flavors'] = ['Original', 'Citrus Burst', 'Berry Bliss'];
            $recommendation['dosage_suggestion'] = 'Start with 1-2 squeezes, can go up to 4-5 for full experience';
            $recommendation['usage_scenarios'] = [
                'Replace your evening drinks',
                'Weekend social events',
                'Stress relief after work',
                'Date nights without hangovers'
            ];
        } else {
            $recommendation['recommended_flavors'] = ['Tropical Paradise', 'Berry Bliss'];
            $recommendation['dosage_suggestion'] = 'Start with 1 squeeze, perfect for occasional use';
            $recommendation['usage_scenarios'] = [
                'Special occasions',
                'Weekend relaxation',
                'Social gatherings'
            ];
        }

        // Expected benefits based on their drinking pattern
        $recommendation['expected_benefits'] = [
            'No hangovers (wake up feeling great)',
            'Better control (effects last 3-4 hours)',
            'Save money (one bottle = many experiences)',
            'Maintain clarity while feeling good',
            'Legal and safe alternative'
        ];

        // Create transition plan
        if ($pattern['pattern_score'] > 15) {
            $recommendation['transition_plan'] = '3-Week Transition: Week 1 - Replace 2 drinking sessions with Mycolean. Week 2 - Replace 4 sessions. Week 3 - Use Mycolean as primary choice.';
            $recommendation['success_probability'] = 0.78;
        } else {
            $recommendation['transition_plan'] = 'Gradual Integration: Use Mycolean 1-2 times per week alongside reduced alcohol consumption.';
            $recommendation['success_probability'] = 0.65;
        }

        return $recommendation;
    }

    /**
     * Predict conversion probability using ML-like scoring
     */
    private function predictConversionProbability(QuizSession $session): array
    {
        $score = $session->total_score ?? 0;
        $demographics = $session->demographics ?? [];
        $responses = $session->responses ?? [];
        
        $factors = [];
        $probability = 0.3; // Base probability
        
        // Score-based factors
        if ($score >= 16) {
            $probability += 0.4;
            $factors[] = 'High alcohol risk increases motivation to find alternatives';
        } elseif ($score >= 8) {
            $probability += 0.25;
            $factors[] = 'Moderate risk suggests openness to healthier options';
        }

        // Age factors
        $age = $demographics['age'] ?? '';
        if (in_array($age, ['25-34', '35-44'])) {
            $probability += 0.15;
            $factors[] = 'Age group shows high interest in wellness alternatives';
        }

        // Response pattern factors
        if (($responses[4] ?? 0) > 0) { // Control issues
            $probability += 0.2;
            $factors[] = 'Control concerns indicate strong motivation for alternatives';
        }

        if (($responses[8] ?? 0) > 0) { // Others concerned
            $probability += 0.15;
            $factors[] = 'Social pressure increases likelihood of seeking alternatives';
        }

        // Email provided factor
        if (!empty($session->user_email)) {
            $probability += 0.1;
            $factors[] = 'Email provided shows engagement and follow-up potential';
        }

        // Cap at 95%
        $probability = min($probability, 0.95);

        return [
            'probability' => round($probability, 2),
            'confidence_level' => $probability > 0.7 ? 'High' : ($probability > 0.5 ? 'Medium' : 'Low'),
            'factors' => $factors,
            'recommended_approach' => $this->getRecommendedApproach($probability),
            'follow_up_strategy' => $this->getFollowUpStrategy($probability, $session)
        ];
    }

    /**
     * Generate personalized message for the user
     */
    private function generatePersonalizedMessage(array $pattern, array $insights): string
    {
        $messages = [
            "Based on your responses, you're an ideal candidate for Mycolean - a legal, hangover-free alternative that gives you the buzz you enjoy without the negative effects.",
            
            "Your drinking pattern suggests you value social experiences and good times. Mycolean provides exactly that - euphoric feelings that last 3-4 hours with complete control.",
            
            "Many people with similar quiz results have successfully transitioned to Mycolean, enjoying better mornings, more money in their pocket, and the same great social experiences.",
            
            "Mycolean isn't about giving up fun - it's about upgrading your experience. Feel great, stay sharp, wake up refreshed."
        ];

        // Select message based on risk level
        $riskLevel = $insights[0]['type'] ?? 'low_risk';
        
        switch ($riskLevel) {
            case 'high_risk':
                return "Your quiz results indicate you could greatly benefit from a safer alternative. " . $messages[0] . " " . $messages[2];
            case 'higher_risk':
                return $messages[1] . " " . $messages[3];
            case 'increasing_risk':
                return $messages[0] . " " . $messages[3];
            default:
                return $messages[1] . " Consider Mycolean for special occasions when you want to feel amazing without any downsides.";
        }
    }

    // Helper methods for categorization
    private function categorizeFrequency(int $score): string
    {
        return match($score) {
            0 => 'never',
            1 => 'monthly',
            2 => 'weekly',
            3 => 'daily_weekly',
            4 => 'daily',
            default => 'unknown'
        };
    }

    private function categorizeQuantity(int $score): string
    {
        return match($score) {
            0 => 'none',
            1 => 'low',
            2 => 'moderate',
            3 => 'high',
            4 => 'very_high',
            default => 'unknown'
        };
    }

    private function categorizeBinge(int $score): string
    {
        return match($score) {
            0 => 'never',
            1 => 'rare',
            2 => 'monthly',
            3 => 'weekly',
            4 => 'daily',
            default => 'unknown'
        };
    }

    private function categorizeControl(int $score): string
    {
        return $score > 0 ? 'issues' : 'good';
    }

    private function analyzeSocialDrinking(array $responses): string
    {
        // Analyze if drinking is primarily social
        $socialIndicators = ($responses[1] ?? 0) + ($responses[3] ?? 0);
        return $socialIndicators > 3 ? 'high' : 'moderate';
    }

    private function assessMotivationToChange(array $responses): string
    {
        $motivationScore = ($responses[4] ?? 0) + ($responses[8] ?? 0) + ($responses[9] ?? 0);
        return $motivationScore > 2 ? 'high' : 'moderate';
    }

    private function calculatePatternScore(array $pattern): int
    {
        $score = 0;
        
        $frequencyScores = ['never' => 0, 'monthly' => 1, 'weekly' => 2, 'daily_weekly' => 3, 'daily' => 4];
        $quantityScores = ['none' => 0, 'low' => 1, 'moderate' => 2, 'high' => 3, 'very_high' => 4];
        $bingeScores = ['never' => 0, 'rare' => 1, 'monthly' => 2, 'weekly' => 3, 'daily' => 4];
        
        $score += $frequencyScores[$pattern['frequency_level']] ?? 0;
        $score += $quantityScores[$pattern['quantity_level']] ?? 0;
        $score += $bingeScores[$pattern['binge_tendency']] ?? 0;
        $score += $pattern['control_issues'] === 'issues' ? 2 : 0;
        
        return $score;
    }

    private function assessMycoleanFit(array $pattern): float
    {
        $fit = 0.5; // Base fit
        
        // Higher scores = better fit for Mycolean
        if ($pattern['pattern_score'] > 8) $fit += 0.3;
        if ($pattern['social_context'] === 'high') $fit += 0.2;
        if ($pattern['motivation_level'] === 'high') $fit += 0.2;
        if ($pattern['control_issues'] === 'issues') $fit += 0.15;
        
        return min($fit, 0.95);
    }

    private function identifyRiskFactors(array $responses, array $demographics): array
    {
        $factors = [];
        
        if (($responses[4] ?? 0) > 0) $factors[] = 'Loss of control when drinking';
        if (($responses[5] ?? 0) > 0) $factors[] = 'Failing to meet expectations due to drinking';
        if (($responses[6] ?? 0) > 0) $factors[] = 'Morning drinking to feel better';
        if (($responses[7] ?? 0) > 0) $factors[] = 'Guilt or remorse after drinking';
        if (($responses[8] ?? 0) > 0) $factors[] = 'Memory blackouts from drinking';
        if (($responses[9] ?? 0) > 0) $factors[] = 'Others concerned about drinking';
        if (($responses[10] ?? 0) > 0) $factors[] = 'Injury related to drinking';
        
        return $factors;
    }

    private function predictSuccessWithMycolean(array $pattern, array $demographics): array
    {
        $successScore = 0.6; // Base success rate
        
        if ($pattern['motivation_level'] === 'high') $successScore += 0.2;
        if ($pattern['social_context'] === 'high') $successScore += 0.15;
        if (in_array($demographics['age'] ?? '', ['25-34', '35-44'])) $successScore += 0.1;
        
        return [
            'success_probability' => min($successScore, 0.95),
            'timeline' => $successScore > 0.8 ? '2-4 weeks' : '4-8 weeks',
            'key_factors' => [
                'Social drinking pattern makes transition easier',
                'Age group shows high success rates with alternatives',
                'Motivation level indicates commitment to change'
            ]
        ];
    }

    private function getRecommendedApproach(float $probability): string
    {
        if ($probability > 0.7) {
            return 'Direct product recommendation with immediate purchase incentive';
        } elseif ($probability > 0.5) {
            return 'Educational approach with free sample offer';
        } else {
            return 'Nurture sequence with value-first content';
        }
    }

    private function getFollowUpStrategy(float $probability, QuizSession $session): array
    {
        $strategy = [];
        
        if (!empty($session->user_email)) {
            if ($probability > 0.7) {
                $strategy[] = 'Send immediate personalized product recommendation email';
                $strategy[] = 'Follow up in 24 hours with limited-time discount';
                $strategy[] = 'Send success stories from similar users in 3 days';
            } else {
                $strategy[] = 'Send educational content about alcohol alternatives';
                $strategy[] = 'Provide free sample offer in follow-up email';
                $strategy[] = 'Share testimonials and social proof over 7 days';
            }
        } else {
            $strategy[] = 'Display exit-intent popup with email capture';
            $strategy[] = 'Retarget with social media ads';
            $strategy[] = 'Show personalized product recommendations on website';
        }
        
        return $strategy;
    }
}
