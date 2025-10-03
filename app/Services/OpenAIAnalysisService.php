<?php

namespace App\Services;

use App\Models\QuizSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class OpenAIAnalysisService
{
    private $apiKey;
    private $baseUrl = 'https://api.openai.com/v1';
    
    public function __construct()
    {
        $this->apiKey = env('OPENAI_API_KEY');
    }

    /**
     * Check if we should run ML analysis (every 5 submissions)
     */
    public function shouldRunMLAnalysis(): bool
    {
        $submissionCount = Cache::get('quiz_submission_count', 0);
        return $submissionCount % 5 === 0 && $submissionCount > 0;
    }

    /**
     * Increment submission counter
     */
    public function incrementSubmissionCount(): int
    {
        $count = Cache::get('quiz_submission_count', 0) + 1;
        Cache::put('quiz_submission_count', $count, now()->addDays(30));
        return $count;
    }

    /**
     * Run comprehensive ML analysis on recent quiz data
     */
    public function runMLAnalysis(int $analysisCount = 50): array
    {
        try {
            // Get recent completed sessions
            $sessions = QuizSession::where('completed', true)
                ->where('completed_at', '>=', Carbon::now()->subDays(7))
                ->orderBy('completed_at', 'desc')
                ->limit($analysisCount)
                ->get();

            if ($sessions->count() < 5) {
                Log::info('Not enough data for ML analysis', ['session_count' => $sessions->count()]);
                return ['status' => 'insufficient_data'];
            }

            // Prepare data for OpenAI analysis
            $analysisData = $this->prepareDataForAnalysis($sessions);
            
            // Get insights from OpenAI
            $mlInsights = $this->getOpenAIInsights($analysisData);
            
            // Store insights for dashboard
            $this->storeMLInsights($mlInsights);
            
            Log::info('ML Analysis completed successfully', [
                'sessions_analyzed' => $sessions->count(),
                'insights_generated' => count($mlInsights['patterns'] ?? [])
            ]);

            return [
                'status' => 'success',
                'sessions_analyzed' => $sessions->count(),
                'insights' => $mlInsights,
                'analyzed_at' => now()->toIso8601String()
            ];

        } catch (\Exception $e) {
            Log::error('ML Analysis failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Prepare quiz data for OpenAI analysis
     */
    private function prepareDataForAnalysis($sessions): array
    {
        $data = [
            'total_sessions' => $sessions->count(),
            'analysis_period' => '7 days',
            'sessions' => []
        ];

        foreach ($sessions as $session) {
            $sessionData = [
                'score' => $session->total_score,
                'risk_level' => $session->risk_level,
                'demographics' => $session->demographics,
                'responses' => $session->responses,
                'time_taken' => $session->time_taken_seconds,
                'has_email' => !empty($session->user_email),
                'shopify_domain' => $session->shopify_domain,
                'completed_at' => $session->completed_at->format('Y-m-d H:i:s')
            ];

            // Include AI analysis if available
            $aiAnalysis = $session->session_metadata['ai_analysis'] ?? null;
            if ($aiAnalysis) {
                $sessionData['conversion_probability'] = $aiAnalysis['conversion_probability']['probability'] ?? null;
                $sessionData['mycolean_fit_score'] = $aiAnalysis['mycolean_recommendation']['product_fit_score'] ?? null;
            }

            $data['sessions'][] = $sessionData;
        }

        return $data;
    }

    /**
     * Get insights from OpenAI API
     */
    private function getOpenAIInsights(array $data): array
    {
        $prompt = $this->buildAnalysisPrompt($data);
        
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(60)->post($this->baseUrl . '/chat/completions', [
            'model' => 'gpt-4o-mini', // Cost-effective model
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an expert data analyst specializing in alcohol consumption patterns and customer conversion analysis for Mycolean, an alcohol alternative product. Analyze the provided quiz data and provide actionable business insights in JSON format.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.3, // Lower temperature for more consistent analysis
            'max_tokens' => 2000,
            'response_format' => ['type' => 'json_object']
        ]);

        if (!$response->successful()) {
            throw new \Exception('OpenAI API request failed: ' . $response->body());
        }

        $result = $response->json();
        $content = $result['choices'][0]['message']['content'] ?? '{}';
        
        return json_decode($content, true) ?: [];
    }

    /**
     * Build comprehensive analysis prompt for OpenAI
     */
    private function buildAnalysisPrompt(array $data): string
    {
        $sessionsJson = json_encode($data, JSON_PRETTY_PRINT);
        
        return "
Analyze this Mycolean alcohol quiz data and provide insights in JSON format with these sections:

**Data to analyze:**
{$sessionsJson}

**Required JSON response format:**
{
  \"patterns\": {
    \"high_conversion_factors\": [\"factor1\", \"factor2\"],
    \"low_conversion_factors\": [\"factor1\", \"factor2\"],
    \"optimal_demographics\": {\"age_groups\": [], \"risk_levels\": []},
    \"seasonal_trends\": \"description\",
    \"completion_patterns\": \"description\"
  },
  \"recommendations\": {
    \"marketing_focus\": \"specific recommendation\",
    \"product_positioning\": \"how to position Mycolean\",
    \"email_optimization\": \"email strategy improvements\",
    \"conversion_improvements\": [\"improvement1\", \"improvement2\"]
  },
  \"predictions\": {
    \"next_week_conversions\": \"estimated percentage\",
    \"high_value_segments\": [\"segment1\", \"segment2\"],
    \"revenue_opportunities\": \"specific opportunities\"
  },
  \"insights\": {
    \"surprising_findings\": [\"finding1\", \"finding2\"],
    \"risk_level_analysis\": \"analysis of risk levels vs conversion\",
    \"demographic_insights\": \"key demographic patterns\",
    \"behavioral_patterns\": \"user behavior insights\"
  },
  \"action_items\": [
    {\"priority\": \"high\", \"action\": \"specific action\", \"expected_impact\": \"impact description\"},
    {\"priority\": \"medium\", \"action\": \"specific action\", \"expected_impact\": \"impact description\"}
  ]
}

Focus on:
1. What makes users most likely to convert to Mycolean customers
2. Patterns in drinking behavior that predict product fit
3. Demographic and psychographic insights
4. Actionable recommendations for improving conversion rates
5. Revenue optimization opportunities
";
    }

    /**
     * Store ML insights in cache for dashboard
     */
    private function storeMLInsights(array $insights): void
    {
        $insightsWithTimestamp = array_merge($insights, [
            'generated_at' => now()->toIso8601String(),
            'expires_at' => now()->addHours(24)->toIso8601String()
        ]);

        Cache::put('ml_insights', $insightsWithTimestamp, now()->addHours(24));
        
        // Also store historical insights
        $historicalInsights = Cache::get('historical_ml_insights', []);
        $historicalInsights[] = $insightsWithTimestamp;
        
        // Keep only last 10 analyses
        if (count($historicalInsights) > 10) {
            $historicalInsights = array_slice($historicalInsights, -10);
        }
        
        Cache::put('historical_ml_insights', $historicalInsights, now()->addDays(30));
    }

    /**
     * Get current ML insights from cache
     */
    public function getCurrentMLInsights(): ?array
    {
        return Cache::get('ml_insights');
    }

    /**
     * Get historical ML insights
     */
    public function getHistoricalMLInsights(): array
    {
        return Cache::get('historical_ml_insights', []);
    }

    /**
     * Generate personalized user insights using OpenAI
     */
    public function generatePersonalizedUserInsights(QuizSession $session): array
    {
        try {
            // Get recent similar users for context
            $similarUsers = $this->findSimilarUsers($session);
            
            $prompt = $this->buildUserInsightPrompt($session, $similarUsers);
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($this->baseUrl . '/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a personalized wellness coach specializing in alcohol alternatives. Provide empathetic, actionable advice for users considering Mycolean based on their quiz results.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.7, // More creative for personalized advice
                'max_tokens' => 1000,
                'response_format' => ['type' => 'json_object']
            ]);

            if (!$response->successful()) {
                Log::warning('OpenAI user insights request failed', ['error' => $response->body()]);
                return [];
            }

            $result = $response->json();
            $content = $result['choices'][0]['message']['content'] ?? '{}';
            
            return json_decode($content, true) ?: [];

        } catch (\Exception $e) {
            Log::error('Failed to generate personalized user insights', [
                'session_uuid' => $session->session_uuid,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Find similar users for context
     */
    private function findSimilarUsers(QuizSession $session): array
    {
        $scoreRange = 3; // +/- 3 points
        $minScore = max(0, $session->total_score - $scoreRange);
        $maxScore = min(40, $session->total_score + $scoreRange);

        return QuizSession::where('completed', true)
            ->where('id', '!=', $session->id)
            ->whereBetween('total_score', [$minScore, $maxScore])
            ->where('completed_at', '>=', Carbon::now()->subDays(30))
            ->limit(10)
            ->get()
            ->map(function($s) {
                return [
                    'score' => $s->total_score,
                    'risk_level' => $s->risk_level,
                    'demographics' => $s->demographics,
                    'has_email' => !empty($s->user_email),
                    'conversion_probability' => $s->session_metadata['ai_analysis']['conversion_probability']['probability'] ?? null
                ];
            })
            ->toArray();
    }

    /**
     * Build personalized user insight prompt
     */
    private function buildUserInsightPrompt(QuizSession $session, array $similarUsers): string
    {
        $userJson = json_encode([
            'score' => $session->total_score,
            'risk_level' => $session->risk_level,
            'demographics' => $session->demographics,
            'responses' => $session->responses,
            'ai_analysis' => $session->session_metadata['ai_analysis'] ?? null
        ], JSON_PRETTY_PRINT);

        $similarUsersJson = json_encode($similarUsers, JSON_PRETTY_PRINT);

        return "
Provide personalized insights for this Mycolean quiz user in JSON format:

**User Data:**
{$userJson}

**Similar Users (for context):**
{$similarUsersJson}

**Required JSON response format:**
{
  \"personalized_message\": \"Warm, empathetic message addressing their specific situation\",
  \"key_insights\": [
    \"insight about their drinking pattern\",
    \"insight about their risk factors\",
    \"insight about their readiness for change\"
  ],
  \"mycolean_benefits\": [
    \"specific benefit for their situation\",
    \"another relevant benefit\"
  ],
  \"success_tips\": [
    \"practical tip for their transition\",
    \"another actionable tip\"
  ],
  \"motivation_message\": \"Encouraging message based on their readiness to change\",
  \"next_steps\": [
    \"immediate action they can take\",
    \"follow-up action\"
  ],
  \"peer_comparison\": \"How they compare to similar users (encouraging tone)\"
}

Make it:
1. Personal and empathetic
2. Specific to their quiz responses
3. Encouraging and supportive
4. Focused on Mycolean as a solution
5. Actionable and practical
";
    }

    /**
     * Get API usage statistics
     */
    public function getAPIUsageStats(): array
    {
        return [
            'total_ml_analyses' => Cache::get('total_ml_analyses', 0),
            'total_user_insights' => Cache::get('total_user_insights', 0),
            'last_analysis' => Cache::get('last_ml_analysis_time'),
            'submission_count' => Cache::get('quiz_submission_count', 0),
            'next_analysis_at' => $this->getNextAnalysisTime()
        ];
    }

    /**
     * Get next scheduled analysis time
     */
    private function getNextAnalysisTime(): ?string
    {
        $currentCount = Cache::get('quiz_submission_count', 0);
        $nextAnalysisCount = (floor($currentCount / 5) + 1) * 5;
        $submissionsNeeded = $nextAnalysisCount - $currentCount;
        
        return "After {$submissionsNeeded} more submissions";
    }

    /**
     * Increment API usage counters
     */
    public function incrementUsageCounter(string $type): void
    {
        $key = "total_{$type}";
        $current = Cache::get($key, 0);
        Cache::put($key, $current + 1, now()->addDays(30));
        
        if ($type === 'ml_analyses') {
            Cache::put('last_ml_analysis_time', now()->toIso8601String(), now()->addDays(30));
        }
    }
}
