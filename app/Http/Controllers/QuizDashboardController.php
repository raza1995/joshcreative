<?php

namespace App\Http\Controllers;

use App\Models\QuizSession;
use App\Models\QuizAnalytic;
use App\Models\QuizQuestion;
use App\Services\OpenAIAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class QuizDashboardController extends Controller
{
    /**
     * Main dashboard view
     */
    public function index(Request $request)
    {
        try {
            $dateRange = $request->get('range', '30'); // Default 30 days
            $shopifyDomain = $request->get('domain');
            $quizType = $request->get('quiz_type', 'audit'); // Default to audit
            
            $stats = $this->getOverviewStats($dateRange, $shopifyDomain, $quizType);
            $chartData = $this->getChartData($dateRange, $shopifyDomain, $quizType);
            $aiAnalytics = $this->getAIAnalytics($dateRange, $shopifyDomain, $quizType);
            
            // Get ML insights from OpenAI
            $openAIService = new OpenAIAnalysisService();
            $mlInsights = $openAIService->getCurrentMLInsights();
            $apiUsageStats = $openAIService->getAPIUsageStats();
            
            return view('quiz.dashboard', compact('stats', 'chartData', 'aiAnalytics', 'mlInsights', 'apiUsageStats', 'dateRange', 'shopifyDomain', 'quizType'));
        } catch (\Exception $e) {
            \Log::error('Quiz Dashboard Error: ' . $e->getMessage());
            
            // Return with empty data if there's an error
            $stats = [
                'total_sessions' => 0,
                'completed_sessions' => 0,
                'completion_rate' => 0,
                'average_score' => 0,
                'average_time_minutes' => 0,
                'email_provided' => 0,
                'email_rate' => 0,
                'risk_distribution' => [
                    'low' => 0,
                    'increasing' => 0,
                    'higher' => 0,
                    'dependence' => 0,
                ],
            ];
            
            $chartData = [
                'daily_completions' => [],
                'score_distribution' => [],
            ];
            
            $dateRange = $request->get('range', '30');
            $shopifyDomain = $request->get('domain');
            $quizType = $request->get('quiz_type', 'audit');
            
            // Add empty data for error case
            $aiAnalytics = ['total_analyzed' => 0];
            $mlInsights = null;
            $apiUsageStats = ['submission_count' => 0, 'total_ml_analyses' => 0, 'total_user_insights' => 0, 'next_analysis_at' => 'N/A'];
            
            return view('quiz.dashboard', compact('stats', 'chartData', 'aiAnalytics', 'mlInsights', 'apiUsageStats', 'dateRange', 'shopifyDomain', 'quizType'))
                ->with('error', 'There was an issue loading the dashboard data. Please check the logs.');
        }
    }

    /**
     * Sessions list view
     */
    public function sessions(Request $request)
    {
        $quizType = $request->get('quiz_type', 'audit');
        
        $query = QuizSession::with(['analytics'])
            ->where('quiz_type', $quizType)
            ->orderBy('created_at', 'desc');
            
        // Apply filters
        if ($request->filled('risk_level')) {
            $query->where('risk_level', $request->risk_level);
        }
        
        if ($request->filled('completed')) {
            $query->where('completed', $request->completed === 'true');
        }
        
        if ($request->filled('domain')) {
            $query->where('shopify_domain', $request->domain);
        }
        
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        
        $sessions = $query->paginate(50);
        
        // Get filter options
        $domains = QuizSession::where('quiz_type', $quizType)
            ->whereNotNull('shopify_domain')
            ->distinct()
            ->pluck('shopify_domain')
            ->sort();
            
        return view('quiz.sessions', compact('sessions', 'domains', 'quizType'));
    }

    /**
     * Individual session details
     */
    public function sessionDetail($sessionUuid)
    {
        $session = QuizSession::with(['analytics'])
            ->where('session_uuid', $sessionUuid)
            ->firstOrFail();
            
        $questions = QuizQuestion::byQuizType($session->quiz_type)
            ->byVersion($session->quiz_version)
            ->ordered()
            ->get();
            
        return view('quiz.session-detail', compact('session', 'questions'));
    }

    /**
     * Analytics overview
     */
    public function analytics(Request $request)
    {
        $dateRange = $request->get('range', '30');
        $shopifyDomain = $request->get('domain');
        $quizType = $request->get('quiz_type', 'audit');
        
        $data = [
            'completion_funnel' => $this->getCompletionFunnel($dateRange, $shopifyDomain, $quizType),
            'question_analytics' => $this->getQuestionAnalytics($dateRange, $shopifyDomain, $quizType),
            'time_analytics' => $this->getTimeAnalytics($dateRange, $shopifyDomain, $quizType),
            'risk_trends' => $this->getRiskTrends($dateRange, $shopifyDomain, $quizType),
            'demographic_insights' => $this->getDemographicInsights($dateRange, $shopifyDomain, $quizType),
            'user_journey' => $this->getUserJourneyAnalytics($dateRange, $shopifyDomain, $quizType),
            'performance_metrics' => $this->getPerformanceMetrics($dateRange, $shopifyDomain, $quizType),
            'conversion_analysis' => $this->getConversionAnalysis($dateRange, $shopifyDomain, $quizType),
            'engagement_metrics' => $this->getEngagementMetrics($dateRange, $shopifyDomain, $quizType),
            'geographic_data' => $this->getGeographicData($dateRange, $shopifyDomain, $quizType),
            'device_analytics' => $this->getDeviceAnalytics($dateRange, $shopifyDomain, $quizType),
            'quiz_types' => $this->getAvailableQuizTypes(),
        ];
        
        return view('quiz.analytics', compact('data', 'dateRange', 'shopifyDomain', 'quizType'));
    }

    /**
     * Export data to CSV
     */
    public function export(Request $request)
    {
        $dateRange = $request->get('range', '30');
        $shopifyDomain = $request->get('domain');
        $quizType = $request->get('quiz_type', 'audit');
        
        $query = QuizSession::where('quiz_type', $quizType)->completed();
        
        if ($dateRange !== 'all') {
            $query->where('created_at', '>=', Carbon::now()->subDays($dateRange));
        }
        
        if ($shopifyDomain) {
            $query->where('shopify_domain', $shopifyDomain);
        }
        
        $sessions = $query->get();
        
        $filename = 'quiz_results_' . date('Y-m-d') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];
        
        $callback = function() use ($sessions) {
            $file = fopen('php://output', 'w');
            
            // CSV headers
            fputcsv($file, [
                'Session UUID',
                'Email',
                'Completed At',
                'Total Score',
                'Risk Level',
                'Age',
                'Sex',
                'Shopify Domain',
                'Time Taken (seconds)',
                'Q1', 'Q2', 'Q3', 'Q4', 'Q5', 'Q6', 'Q7', 'Q8', 'Q9', 'Q10'
            ]);
            
            foreach ($sessions as $session) {
                $demographics = $session->demographics ?? [];
                $responses = $session->responses ?? [];
                
                fputcsv($file, [
                    $session->session_uuid,
                    $session->user_email ?? '',
                    $session->completed_at?->format('Y-m-d H:i:s'),
                    $session->total_score,
                    $session->risk_level,
                    $demographics['age'] ?? '',
                    $demographics['sex'] ?? '',
                    $session->shopify_domain ?? '',
                    $session->time_taken_seconds,
                    $responses[1] ?? '',
                    $responses[2] ?? '',
                    $responses[3] ?? '',
                    $responses[4] ?? '',
                    $responses[5] ?? '',
                    $responses[6] ?? '',
                    $responses[7] ?? '',
                    $responses[8] ?? '',
                    $responses[9] ?? '',
                    $responses[10] ?? '',
                ]);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get overview statistics
     */
    private function getOverviewStats($dateRange, $shopifyDomain = null, $quizType = 'audit')
    {
        $query = QuizSession::where('quiz_type', $quizType);
        
        if ($dateRange !== 'all') {
            $query->where('created_at', '>=', Carbon::now()->subDays($dateRange));
        }
        
        if ($shopifyDomain) {
            $query->where('shopify_domain', $shopifyDomain);
        }
        
        $totalSessions = $query->count();
        $completedSessions = $query->where('completed', true)->count();
        $completionRate = $totalSessions > 0 ? round(($completedSessions / $totalSessions) * 100, 1) : 0;
        
        $avgScore = $query->where('completed', true)->avg('total_score');
        $avgTime = $query->where('completed', true)->avg('time_taken_seconds');
        
        // Email statistics
        $emailProvided = $query->whereNotNull('user_email')->count();
        $emailRate = $totalSessions > 0 ? round(($emailProvided / $totalSessions) * 100, 1) : 0;
        
        $riskDistribution = $query->where('completed', true)
            ->select('risk_level', DB::raw('count(*) as count'))
            ->groupBy('risk_level')
            ->pluck('count', 'risk_level')
            ->toArray();
            
        return [
            'total_sessions' => $totalSessions,
            'completed_sessions' => $completedSessions,
            'completion_rate' => $completionRate,
            'average_score' => $avgScore ? round($avgScore, 1) : 0,
            'average_time_minutes' => $avgTime ? round($avgTime / 60, 1) : 0,
            'email_provided' => $emailProvided,
            'email_rate' => $emailRate,
            'risk_distribution' => [
                'low' => $riskDistribution['low'] ?? 0,
                'increasing' => $riskDistribution['increasing'] ?? 0,
                'higher' => $riskDistribution['higher'] ?? 0,
                'dependence' => $riskDistribution['dependence'] ?? 0,
            ],
        ];
    }

    /**
     * Get chart data for dashboard
     */
    private function getChartData($dateRange, $shopifyDomain = null, $quizType = 'audit')
    {
        $query = QuizSession::where('quiz_type', $quizType);
        
        if ($dateRange !== 'all') {
            $query->where('created_at', '>=', Carbon::now()->subDays($dateRange));
        }
        
        if ($shopifyDomain) {
            $query->where('shopify_domain', $shopifyDomain);
        }
        
        // Daily completions
        $dailyCompletions = $query->where('completed', true)
            ->select(DB::raw('DATE(completed_at) as date'), DB::raw('count(*) as count'))
            ->groupBy(DB::raw('DATE(completed_at)'))
            ->orderBy(DB::raw('DATE(completed_at)'))
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'count' => $item->count,
                ];
            });
            
        // Score distribution
        $scoreDistribution = $query->where('completed', true)
            ->select('total_score', DB::raw('count(*) as count'))
            ->groupBy('total_score')
            ->orderBy('total_score')
            ->get()
            ->map(function ($item) {
                return [
                    'score' => $item->total_score,
                    'count' => $item->count,
                ];
            });
            
        return [
            'daily_completions' => $dailyCompletions,
            'score_distribution' => $scoreDistribution,
        ];
    }

    /**
     * Get completion funnel data
     */
    private function getCompletionFunnel($dateRange, $shopifyDomain = null, $quizType = 'audit')
    {
        $query = QuizSession::where('quiz_type', $quizType);
        
        if ($dateRange !== 'all') {
            $query->where('created_at', '>=', Carbon::now()->subDays($dateRange));
        }
        
        if ($shopifyDomain) {
            $query->where('shopify_domain', $shopifyDomain);
        }
        
        $totalStarted = $query->count();
        $withDemographics = $query->whereNotNull('demographics')->count();
        $withResponses = $query->whereNotNull('responses')->count();
        $completed = $query->where('completed', true)->count();
        
        return [
            'started' => $totalStarted,
            'demographics' => $withDemographics,
            'responses' => $withResponses,
            'completed' => $completed,
        ];
    }

    /**
     * Get question-specific analytics
     */
    private function getQuestionAnalytics($dateRange, $shopifyDomain = null, $quizType = 'audit')
    {
        $query = QuizSession::where('completed', true)->where('quiz_type', $quizType);
        
        if ($dateRange !== 'all') {
            $query->where('created_at', '>=', Carbon::now()->subDays($dateRange));
        }
        
        if ($shopifyDomain) {
            $query->where('shopify_domain', $shopifyDomain);
        }
        
        $sessions = $query->get();
        $questionStats = [];
        
        for ($i = 1; $i <= 10; $i++) {
            $scores = $sessions->pluck('responses')->filter()->map(function ($responses) use ($i) {
                return $responses[$i] ?? null;
            })->filter();
            
            $questionStats[$i] = [
                'question_number' => $i,
                'total_responses' => $scores->count(),
                'average_score' => $scores->avg(),
                'score_distribution' => $scores->countBy()->toArray(),
            ];
        }
        
        return $questionStats;
    }

    /**
     * Get time analytics
     */
    private function getTimeAnalytics($dateRange, $shopifyDomain = null, $quizType = 'audit')
    {
        $query = QuizSession::where('completed', true)->where('quiz_type', $quizType);
        
        if ($dateRange !== 'all') {
            $query->where('created_at', '>=', Carbon::now()->subDays($dateRange));
        }
        
        if ($shopifyDomain) {
            $query->where('shopify_domain', $shopifyDomain);
        }
        
        $times = $query->whereNotNull('time_taken_seconds')->pluck('time_taken_seconds');
        
        return [
            'average_seconds' => $times->avg(),
            'median_seconds' => $times->median(),
            'min_seconds' => $times->min(),
            'max_seconds' => $times->max(),
            'distribution' => $times->map(function ($time) {
                return round($time / 60); // Convert to minutes
            })->countBy()->toArray(),
        ];
    }

    /**
     * Get risk level trends over time
     */
    private function getRiskTrends($dateRange, $shopifyDomain = null, $quizType = 'audit')
    {
        $query = QuizSession::where('completed', true)->where('quiz_type', $quizType);
        
        if ($dateRange !== 'all') {
            $query->where('created_at', '>=', Carbon::now()->subDays($dateRange));
        }
        
        if ($shopifyDomain) {
            $query->where('shopify_domain', $shopifyDomain);
        }
        
        return $query->select(
                DB::raw('DATE(completed_at) as date'),
                'risk_level',
                DB::raw('count(*) as count')
            )
            ->groupBy(DB::raw('DATE(completed_at)'), 'risk_level')
            ->orderBy(DB::raw('DATE(completed_at)'))
            ->get()
            ->groupBy('date')
            ->map(function ($dayData) {
                return $dayData->pluck('count', 'risk_level')->toArray();
            });
    }

    /**
     * Get demographic insights
     */
    private function getDemographicInsights($dateRange, $shopifyDomain = null, $quizType = 'audit')
    {
        $query = QuizSession::where('completed', true)->where('quiz_type', $quizType);
        
        if ($dateRange !== 'all') {
            $query->where('created_at', '>=', Carbon::now()->subDays($dateRange));
        }
        
        if ($shopifyDomain) {
            $query->where('shopify_domain', $shopifyDomain);
        }
        
        $sessions = $query->get();
        
        $ageGroups = [];
        $genderDistribution = [];
        $riskByAge = [];
        $riskByGender = [];
        $avgScoreByDemographic = [];
        
        foreach ($sessions as $session) {
            $demographics = $session->demographics ?? [];
            $age = $demographics['age'] ?? 'Unknown';
            $gender = $demographics['sex'] ?? 'Unknown';
            $risk = $session->risk_level ?? 'unknown';
            $score = $session->total_score ?? 0;
            
            // Age distribution
            $ageGroups[$age] = ($ageGroups[$age] ?? 0) + 1;
            
            // Gender distribution
            $genderDistribution[$gender] = ($genderDistribution[$gender] ?? 0) + 1;
            
            // Risk by age
            if (!isset($riskByAge[$age])) $riskByAge[$age] = [];
            $riskByAge[$age][$risk] = ($riskByAge[$age][$risk] ?? 0) + 1;
            
            // Risk by gender
            if (!isset($riskByGender[$gender])) $riskByGender[$gender] = [];
            $riskByGender[$gender][$risk] = ($riskByGender[$gender][$risk] ?? 0) + 1;
            
            // Average score by demographic
            if (!isset($avgScoreByDemographic[$age])) $avgScoreByDemographic[$age] = ['total' => 0, 'count' => 0];
            $avgScoreByDemographic[$age]['total'] += $score;
            $avgScoreByDemographic[$age]['count']++;
        }
        
        // Calculate averages
        foreach ($avgScoreByDemographic as $age => &$data) {
            $data['average'] = $data['count'] > 0 ? round($data['total'] / $data['count'], 1) : 0;
        }
        
        return [
            'age_groups' => $ageGroups,
            'gender_distribution' => $genderDistribution,
            'risk_by_age' => $riskByAge,
            'risk_by_gender' => $riskByGender,
            'avg_score_by_age' => $avgScoreByDemographic,
        ];
    }

    /**
     * Get user journey analytics
     */
    private function getUserJourneyAnalytics($dateRange, $shopifyDomain = null, $quizType = 'audit')
    {
        $query = QuizSession::where('quiz_type', $quizType);
        
        if ($dateRange !== 'all') {
            $query->where('created_at', '>=', Carbon::now()->subDays($dateRange));
        }
        
        if ($shopifyDomain) {
            $query->where('shopify_domain', $shopifyDomain);
        }
        
        $sessions = $query->get();
        
        $dropOffPoints = [];
        $completionTimes = [];
        $emailProvisionRate = 0;
        
        foreach ($sessions as $session) {
            $responses = $session->responses ?? [];
            $responseCount = count($responses);
            
            // Track drop-off points
            if (!$session->completed) {
                $dropOffPoints["question_$responseCount"] = ($dropOffPoints["question_$responseCount"] ?? 0) + 1;
            }
            
            // Completion times
            if ($session->completed && $session->time_taken_seconds) {
                $completionTimes[] = $session->time_taken_seconds;
            }
            
            // Email provision rate
            if ($session->user_email) {
                $emailProvisionRate++;
            }
        }
        
        $totalSessions = $sessions->count();
        $emailProvisionRate = $totalSessions > 0 ? round(($emailProvisionRate / $totalSessions) * 100, 1) : 0;
        
        return [
            'drop_off_points' => $dropOffPoints,
            'avg_completion_time' => count($completionTimes) > 0 ? round(array_sum($completionTimes) / count($completionTimes) / 60, 1) : 0,
            'median_completion_time' => count($completionTimes) > 0 ? round($this->median($completionTimes) / 60, 1) : 0,
            'email_provision_rate' => $emailProvisionRate,
            'completion_time_distribution' => $this->getTimeDistribution($completionTimes),
        ];
    }

    /**
     * Get performance metrics
     */
    private function getPerformanceMetrics($dateRange, $shopifyDomain = null, $quizType = 'audit')
    {
        $query = QuizSession::where('quiz_type', $quizType);
        
        if ($dateRange !== 'all') {
            $query->where('created_at', '>=', Carbon::now()->subDays($dateRange));
        }
        
        if ($shopifyDomain) {
            $query->where('shopify_domain', $shopifyDomain);
        }
        
        $totalSessions = $query->count();
        $completedSessions = $query->where('completed', true)->count();
        $avgScore = $query->where('completed', true)->avg('total_score') ?? 0;
        $highRiskSessions = $query->whereIn('risk_level', ['higher', 'dependence'])->count();
        
        // Question-specific metrics
        $questionMetrics = [];
        $completedQuery = clone $query;
        $sessions = $completedQuery->where('completed', true)->get();
        
        foreach ($sessions as $session) {
            $responses = $session->responses ?? [];
            foreach ($responses as $questionId => $score) {
                if (!isset($questionMetrics[$questionId])) {
                    $questionMetrics[$questionId] = ['scores' => [], 'count' => 0];
                }
                $questionMetrics[$questionId]['scores'][] = $score;
                $questionMetrics[$questionId]['count']++;
            }
        }
        
        // Calculate question averages
        foreach ($questionMetrics as $questionId => &$metrics) {
            $metrics['average'] = count($metrics['scores']) > 0 ? round(array_sum($metrics['scores']) / count($metrics['scores']), 2) : 0;
            $metrics['difficulty'] = $metrics['average'] > 2 ? 'High' : ($metrics['average'] > 1 ? 'Medium' : 'Low');
        }
        
        return [
            'total_sessions' => $totalSessions,
            'completion_rate' => $totalSessions > 0 ? round(($completedSessions / $totalSessions) * 100, 1) : 0,
            'average_score' => round($avgScore, 1),
            'high_risk_rate' => $completedSessions > 0 ? round(($highRiskSessions / $completedSessions) * 100, 1) : 0,
            'question_metrics' => $questionMetrics,
        ];
    }

    /**
     * Get conversion analysis
     */
    private function getConversionAnalysis($dateRange, $shopifyDomain = null, $quizType = 'audit')
    {
        $query = QuizSession::where('quiz_type', $quizType);
        
        if ($dateRange !== 'all') {
            $query->where('created_at', '>=', Carbon::now()->subDays($dateRange));
        }
        
        if ($shopifyDomain) {
            $query->where('shopify_domain', $shopifyDomain);
        }
        
        $totalStarted = $query->count();
        $completedDemographics = $query->whereNotNull('demographics')->count();
        $completedQuiz = $query->where('completed', true)->count();
        $providedEmail = $query->whereNotNull('user_email')->count();
        
        return [
            'started' => $totalStarted,
            'demographics_completed' => $completedDemographics,
            'quiz_completed' => $completedQuiz,
            'email_provided' => $providedEmail,
            'demographics_rate' => $totalStarted > 0 ? round(($completedDemographics / $totalStarted) * 100, 1) : 0,
            'completion_rate' => $totalStarted > 0 ? round(($completedQuiz / $totalStarted) * 100, 1) : 0,
            'email_rate' => $totalStarted > 0 ? round(($providedEmail / $totalStarted) * 100, 1) : 0,
        ];
    }

    /**
     * Get engagement metrics
     */
    private function getEngagementMetrics($dateRange, $shopifyDomain = null, $quizType = 'audit')
    {
        $analyticsQuery = QuizAnalytic::whereHas('quizSession', function($q) use ($quizType, $dateRange, $shopifyDomain) {
            $q->where('quiz_type', $quizType);
            if ($dateRange !== 'all') {
                $q->where('created_at', '>=', Carbon::now()->subDays($dateRange));
            }
            if ($shopifyDomain) {
                $q->where('shopify_domain', $shopifyDomain);
            }
        });
        
        $eventCounts = $analyticsQuery->select('event_type', DB::raw('count(*) as count'))
            ->groupBy('event_type')
            ->pluck('count', 'event_type')
            ->toArray();
        
        $avgTimePerQuestion = $analyticsQuery->where('event_type', 'question_answered')
            ->whereNotNull('time_on_question')
            ->avg('time_on_question') ?? 0;
        
        return [
            'event_counts' => $eventCounts,
            'avg_time_per_question' => round($avgTimePerQuestion, 1),
            'total_interactions' => array_sum($eventCounts),
        ];
    }

    /**
     * Get geographic data
     */
    private function getGeographicData($dateRange, $shopifyDomain = null, $quizType = 'audit')
    {
        $query = QuizSession::where('quiz_type', $quizType);
        
        if ($dateRange !== 'all') {
            $query->where('created_at', '>=', Carbon::now()->subDays($dateRange));
        }
        
        if ($shopifyDomain) {
            $query->where('shopify_domain', $shopifyDomain);
        }
        
        $domainCounts = $query->whereNotNull('shopify_domain')
            ->select('shopify_domain', DB::raw('count(*) as count'))
            ->groupBy('shopify_domain')
            ->orderBy('count', 'desc')
            ->get()
            ->pluck('count', 'shopify_domain')
            ->toArray();
        
        return [
            'domain_distribution' => $domainCounts,
            'total_domains' => count($domainCounts),
        ];
    }

    /**
     * Get device analytics
     */
    private function getDeviceAnalytics($dateRange, $shopifyDomain = null, $quizType = 'audit')
    {
        $query = QuizSession::where('quiz_type', $quizType);
        
        if ($dateRange !== 'all') {
            $query->where('created_at', '>=', Carbon::now()->subDays($dateRange));
        }
        
        if ($shopifyDomain) {
            $query->where('shopify_domain', $shopifyDomain);
        }
        
        $sessions = $query->get();
        $deviceTypes = [];
        $browsers = [];
        
        foreach ($sessions as $session) {
            $userAgent = $session->user_agent ?? '';
            $deviceType = $this->detectDeviceType($userAgent);
            $browser = $this->detectBrowser($userAgent);
            
            $deviceTypes[$deviceType] = ($deviceTypes[$deviceType] ?? 0) + 1;
            $browsers[$browser] = ($browsers[$browser] ?? 0) + 1;
        }
        
        return [
            'device_types' => $deviceTypes,
            'browsers' => $browsers,
        ];
    }

    /**
     * Get available quiz types
     */
    private function getAvailableQuizTypes()
    {
        return QuizSession::select('quiz_type')
            ->distinct()
            ->pluck('quiz_type')
            ->toArray();
    }

    /**
     * Helper method to calculate median
     */
    private function median($array)
    {
        if (empty($array)) return 0;
        
        sort($array);
        $count = count($array);
        $middle = floor($count / 2);
        
        if ($count % 2 == 0) {
            return ($array[$middle - 1] + $array[$middle]) / 2;
        } else {
            return $array[$middle];
        }
    }

    /**
     * Get time distribution
     */
    private function getTimeDistribution($times)
    {
        if (empty($times)) return [];
        
        $distribution = [
            '0-2 min' => 0,
            '2-5 min' => 0,
            '5-10 min' => 0,
            '10+ min' => 0,
        ];
        
        foreach ($times as $time) {
            $minutes = $time / 60;
            if ($minutes <= 2) {
                $distribution['0-2 min']++;
            } elseif ($minutes <= 5) {
                $distribution['2-5 min']++;
            } elseif ($minutes <= 10) {
                $distribution['5-10 min']++;
            } else {
                $distribution['10+ min']++;
            }
        }
        
        return $distribution;
    }

    /**
     * Detect device type from user agent
     */
    private function detectDeviceType($userAgent)
    {
        if (preg_match('/Mobile|Android|iPhone/', $userAgent)) {
            return 'Mobile';
        } elseif (preg_match('/Tablet|iPad/', $userAgent)) {
            return 'Tablet';
        } else {
            return 'Desktop';
        }
    }

    /**
     * Detect browser from user agent
     */
    private function detectBrowser($userAgent)
    {
        if (preg_match('/Chrome/', $userAgent)) return 'Chrome';
        if (preg_match('/Firefox/', $userAgent)) return 'Firefox';
        if (preg_match('/Safari/', $userAgent)) return 'Safari';
        if (preg_match('/Edge/', $userAgent)) return 'Edge';
        if (preg_match('/Opera/', $userAgent)) return 'Opera';
        return 'Other';
    }

    /**
     * Get AI analytics data for Mycolean conversion insights
     */
    private function getAIAnalytics($dateRange, $shopifyDomain = null, $quizType = 'audit'): array
    {
        $startDate = Carbon::now()->subDays($dateRange);
        
        $query = QuizSession::where('completed', true)
            ->where('quiz_type', $quizType)
            ->where('completed_at', '>=', $startDate);
            
        if ($shopifyDomain) {
            $query->where('shopify_domain', $shopifyDomain);
        }
        
        $sessions = $query->get();
        
        // Extract AI analysis data from session metadata
        $conversionData = [];
        $mycoleanFitScores = [];
        $riskToConversionMapping = [];
        
        foreach ($sessions as $session) {
            $aiAnalysis = $session->session_metadata['ai_analysis'] ?? null;
            if ($aiAnalysis) {
                $conversionProb = $aiAnalysis['conversion_probability']['probability'] ?? 0;
                $fitScore = $aiAnalysis['mycolean_recommendation']['product_fit_score'] ?? 0;
                $riskLevel = $session->risk_level ?? 'unknown';
                
                $conversionData[] = $conversionProb;
                $mycoleanFitScores[] = $fitScore;
                
                if (!isset($riskToConversionMapping[$riskLevel])) {
                    $riskToConversionMapping[$riskLevel] = [];
                }
                $riskToConversionMapping[$riskLevel][] = $conversionProb;
            }
        }
        
        // Calculate conversion insights
        $highConversionUsers = count(array_filter($conversionData, fn($prob) => $prob >= 0.7));
        $mediumConversionUsers = count(array_filter($conversionData, fn($prob) => $prob >= 0.5 && $prob < 0.7));
        $lowConversionUsers = count(array_filter($conversionData, fn($prob) => $prob < 0.5));
        
        // Calculate average fit scores by risk level
        $riskFitAverages = [];
        foreach ($riskToConversionMapping as $risk => $probs) {
            $riskFitAverages[$risk] = [
                'avg_conversion' => round(array_sum($probs) / count($probs), 2),
                'count' => count($probs)
            ];
        }
        
        return [
            'total_analyzed' => count($conversionData),
            'avg_conversion_probability' => count($conversionData) > 0 ? round(array_sum($conversionData) / count($conversionData), 2) : 0,
            'avg_mycolean_fit' => count($mycoleanFitScores) > 0 ? round(array_sum($mycoleanFitScores) / count($mycoleanFitScores), 2) : 0,
            'conversion_segments' => [
                'high' => ['count' => $highConversionUsers, 'percentage' => count($conversionData) > 0 ? round(($highConversionUsers / count($conversionData)) * 100) : 0],
                'medium' => ['count' => $mediumConversionUsers, 'percentage' => count($conversionData) > 0 ? round(($mediumConversionUsers / count($conversionData)) * 100) : 0],
                'low' => ['count' => $lowConversionUsers, 'percentage' => count($conversionData) > 0 ? round(($lowConversionUsers / count($conversionData)) * 100) : 0]
            ],
            'risk_conversion_mapping' => $riskFitAverages,
            'revenue_potential' => $this->calculateRevenuePotential($conversionData, $sessions->count()),
            'top_conversion_factors' => $this->getTopConversionFactors($sessions)
        ];
    }
    
    /**
     * Calculate potential revenue based on conversion probabilities
     */
    private function calculateRevenuePotential(array $conversionData, int $totalSessions): array
    {
        $avgOrderValue = 59.99; // Mycolean average price from website
        $totalPotentialRevenue = 0;
        
        foreach ($conversionData as $prob) {
            $totalPotentialRevenue += $prob * $avgOrderValue;
        }
        
        return [
            'potential_revenue' => round($totalPotentialRevenue, 2),
            'avg_revenue_per_session' => $totalSessions > 0 ? round($totalPotentialRevenue / $totalSessions, 2) : 0,
            'high_value_prospects' => count(array_filter($conversionData, fn($prob) => $prob >= 0.8))
        ];
    }
    
    /**
     * Identify top factors that drive conversion
     */
    private function getTopConversionFactors(object $sessions): array
    {
        $factors = [
            'control_issues' => 0,
            'social_drinking' => 0,
            'binge_tendency' => 0,
            'age_25_44' => 0,
            'email_provided' => 0
        ];
        
        $highConversionCount = 0;
        
        foreach ($sessions as $session) {
            $aiAnalysis = $session->session_metadata['ai_analysis'] ?? null;
            if ($aiAnalysis && ($aiAnalysis['conversion_probability']['probability'] ?? 0) >= 0.7) {
                $highConversionCount++;
                
                // Analyze patterns in high-conversion users
                $responses = $session->responses ?? [];
                $demographics = $session->demographics ?? [];
                
                if (($responses[4] ?? 0) > 0) $factors['control_issues']++;
                if (($responses[1] ?? 0) >= 2) $factors['social_drinking']++;
                if (($responses[3] ?? 0) >= 2) $factors['binge_tendency']++;
                if (in_array($demographics['age'] ?? '', ['25-34', '35-44'])) $factors['age_25_44']++;
                if (!empty($session->user_email)) $factors['email_provided']++;
            }
        }
        
        // Calculate percentages
        $result = [];
        foreach ($factors as $factor => $count) {
            $result[$factor] = [
                'count' => $count,
                'percentage' => $highConversionCount > 0 ? round(($count / $highConversionCount) * 100) : 0
            ];
        }
        
        return $result;
    }
}
