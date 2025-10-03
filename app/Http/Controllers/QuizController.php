<?php

namespace App\Http\Controllers;

use App\Models\QuizSession;
use App\Models\QuizQuestion;
use App\Models\QuizAnalytic;
use App\Services\AIAnalysisService;
use App\Services\MycoleanEmailService;
use App\Services\OpenAIAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;

class QuizController extends Controller
{
    /**
     * Start a new quiz session
     */
    public function startSession(Request $request)
    {
        $data = Validator::validate($request->all(), [
            'quiz_type' => ['nullable', 'string', 'max:50'],
            'user_email' => ['nullable', 'email'],
            'shopify_domain' => ['nullable', 'string', 'max:255'],
            'referrer_url' => ['nullable', 'url', 'max:500'],
            'session_metadata' => ['nullable', 'array'],
        ]);

        $session = QuizSession::create([
            'session_uuid' => Str::uuid(),
            'quiz_type' => $data['quiz_type'] ?? 'audit',
            'quiz_version' => '1.0',
            'user_email' => $data['user_email'] ?? null,
            'user_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referrer_url' => $data['referrer_url'] ?? $request->header('Referer'),
            'shopify_domain' => $data['shopify_domain'] ?? null,
            'session_metadata' => $data['session_metadata'] ?? null,
            'started_at' => now(),
        ]);

        // Track session start event
        $this->recordEvent($session->id, 'quiz_started', null, [
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'session_uuid' => $session->session_uuid,
            'quiz_type' => $session->quiz_type,
            'started_at' => $session->started_at->toIso8601String(),
        ]);
    }

    /**
     * Get quiz questions (for dynamic loading)
     */
    public function getQuestions(Request $request)
    {
        $data = Validator::validate($request->all(), [
            'quiz_type' => ['nullable', 'string', 'max:50'],
            'quiz_version' => ['nullable', 'string', 'max:10'],
        ]);

        $questions = QuizQuestion::active()
            ->byQuizType($data['quiz_type'] ?? 'audit')
            ->byVersion($data['quiz_version'] ?? '1.0')
            ->ordered()
            ->get()
            ->map(function ($question) {
                return [
                    'id' => $question->question_number,
                    'question_id' => $question->question_id,
                    'title' => $question->question_text,
                    'options' => $question->options,
                    'help_text' => $question->help_text,
                    'required' => $question->required,
                ];
            });

        return response()->json([
            'questions' => $questions,
            'total_questions' => $questions->count(),
        ]);
    }

    /**
     * Update session email
     */
    public function updateEmail(Request $request)
    {
        $data = Validator::validate($request->all(), [
            'session_uuid' => ['required', 'string', 'exists:quiz_sessions,session_uuid'],
            'user_email' => ['required', 'email', 'max:255'],
        ]);

        $session = QuizSession::where('session_uuid', $data['session_uuid'])->firstOrFail();
        
        $session->update([
            'user_email' => $data['user_email']
        ]);

        // Track email provided event
        $this->recordEvent($session->id, 'email_provided', null, [
            'email_domain' => substr(strrchr($data['user_email'], "@"), 1)
        ]);

        return response()->json([
            'status' => 'updated',
            'session_uuid' => $session->session_uuid,
        ]);
    }

    /**
     * Save demographics data
     */
    public function saveDemographics(Request $request)
    {
        $data = Validator::validate($request->all(), [
            'session_uuid' => ['required', 'string', 'exists:quiz_sessions,session_uuid'],
            'demographics' => ['required', 'array'],
            'demographics.sex' => ['nullable', 'string', 'in:male,female,other'],
            'demographics.age' => ['nullable', 'string'],
        ]);

        $session = QuizSession::where('session_uuid', $data['session_uuid'])->firstOrFail();
        
        $session->update([
            'demographics' => $data['demographics']
        ]);

        // Track demographics completion
        $this->recordEvent($session->id, 'demographics_completed', null, $data['demographics']);

        return response()->json([
            'status' => 'saved',
            'session_uuid' => $session->session_uuid,
        ]);
    }

    /**
     * Save individual question response
     */
    public function saveResponse(Request $request)
    {
        $data = Validator::validate($request->all(), [
            'session_uuid' => ['required', 'string', 'exists:quiz_sessions,session_uuid'],
            'question_id' => ['required', 'integer'],
            'score' => ['required', 'integer', 'min:0', 'max:10'],
            'time_on_question' => ['nullable', 'integer', 'min:0'],
        ]);

        $session = QuizSession::where('session_uuid', $data['session_uuid'])->firstOrFail();
        
        // Get current responses and update
        $responses = $session->responses ?? [];
        $responses[$data['question_id']] = $data['score'];
        
        $session->update([
            'responses' => $responses
        ]);

        // Track question response
        $this->recordEvent($session->id, 'question_answered', "audit_q{$data['question_id']}", [
            'question_id' => $data['question_id'],
            'score' => $data['score'],
            'time_on_question' => $data['time_on_question'] ?? null,
        ], $data['time_on_question'] ?? null);

        return response()->json([
            'status' => 'saved',
            'session_uuid' => $session->session_uuid,
            'total_responses' => count($responses),
        ]);
    }

    /**
     * Complete quiz and calculate results
     */
    public function completeQuiz(Request $request)
    {
        $data = Validator::validate($request->all(), [
            'session_uuid' => ['required', 'string', 'exists:quiz_sessions,session_uuid'],
            'responses' => ['required', 'array'],
            'responses.*' => ['integer', 'min:0', 'max:10'],
        ]);

        $session = QuizSession::where('session_uuid', $data['session_uuid'])->firstOrFail();
        
        // Calculate total score
        $totalScore = array_sum($data['responses']);
        
        // Update session with score first so calculateRiskLevel can use it
        $session->update([
            'responses' => $data['responses'],
            'total_score' => $totalScore,
        ]);
        
        // Get risk assessment using the updated score
        $riskData = $session->calculateRiskLevel();
        
        // Generate AI analysis with Mycolean recommendations
        $aiAnalysisService = new AIAnalysisService();
        $aiAnalysis = $aiAnalysisService->generatePersonalizedAnalysis($session);
        
        // Enhanced OpenAI analysis for personalized insights
        $openAIService = new OpenAIAnalysisService();
        $openAIInsights = $openAIService->generatePersonalizedUserInsights($session);
        
        // Merge OpenAI insights with existing analysis
        if (!empty($openAIInsights)) {
            $aiAnalysis['openai_insights'] = $openAIInsights;
            $openAIService->incrementUsageCounter('user_insights');
        }
        
        // Update session with final data including AI analysis
        $session->update([
            'risk_level' => $riskData['level'],
            'risk_message' => $riskData['message'],
            'completed' => true,
            'completed_at' => now(),
            'time_taken_seconds' => $session->started_at ? now()->diffInSeconds($session->started_at) : null,
            'session_metadata' => array_merge($session->session_metadata ?? [], [
                'ai_analysis' => $aiAnalysis
            ])
        ]);

        // Track completion with AI insights
        $this->recordEvent($session->id, 'quiz_completed', null, [
            'total_score' => $totalScore,
            'risk_level' => $riskData['level'],
            'time_taken' => $session->time_taken_seconds,
            'conversion_probability' => $aiAnalysis['conversion_probability']['probability'],
            'mycolean_fit_score' => $aiAnalysis['mycolean_recommendation']['product_fit_score'],
        ]);

        // Send personalized follow-up email if user provided email
        if (!empty($session->user_email)) {
            try {
                $emailService = new MycoleanEmailService();
                $emailService->scheduleFollowUpSequence($session);
                
                $this->recordEvent($session->id, 'email_follow_up_scheduled', null, [
                    'conversion_probability' => $aiAnalysis['conversion_probability']['probability'],
                    'email_type' => $aiAnalysis['conversion_probability']['probability'] >= 0.7 ? 'high_conversion' : 
                                   ($aiAnalysis['conversion_probability']['probability'] >= 0.5 ? 'medium_conversion' : 'educational')
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to schedule follow-up email', [
                    'session_uuid' => $session->session_uuid,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Check if we should run ML analysis (every 5 submissions)
        $submissionCount = $openAIService->incrementSubmissionCount();
        if ($openAIService->shouldRunMLAnalysis()) {
            try {
                // Run ML analysis in background (you could use queues for this)
                $mlResults = $openAIService->runMLAnalysis();
                $openAIService->incrementUsageCounter('ml_analyses');
                
                $this->recordEvent($session->id, 'ml_analysis_triggered', null, [
                    'submission_count' => $submissionCount,
                    'analysis_status' => $mlResults['status'] ?? 'unknown'
                ]);
            } catch (\Exception $e) {
                \Log::error('ML Analysis failed during quiz completion', [
                    'session_uuid' => $session->session_uuid,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return response()->json([
            'session_uuid' => $session->session_uuid,
            'total_score' => $totalScore,
            'risk_level' => $riskData['level'],
            'risk_label' => $riskData['label'],
            'risk_color' => $riskData['color'],
            'risk_message' => $riskData['message'],
            'ai_analysis' => $aiAnalysis,
            'completed_at' => $session->completed_at->toIso8601String(),
            'time_taken_seconds' => $session->time_taken_seconds,
            'email_follow_up' => !empty($session->user_email) ? 'scheduled' : 'not_applicable',
        ]);
    }

    /**
     * Track analytics events
     */
    public function trackEvent(Request $request)
    {
        $data = Validator::validate($request->all(), [
            'session_uuid' => ['required', 'string', 'exists:quiz_sessions,session_uuid'],
            'event_type' => ['required', 'string', 'max:100'],
            'question_id' => ['nullable', 'string', 'max:50'],
            'event_data' => ['nullable', 'array'],
            'time_on_question' => ['nullable', 'integer', 'min:0'],
            'user_action' => ['nullable', 'string', 'max:50'],
            'interaction_data' => ['nullable', 'array'],
        ]);

        $session = QuizSession::where('session_uuid', $data['session_uuid'])->firstOrFail();
        
        $this->recordEvent(
            $session->id,
            $data['event_type'],
            $data['question_id'] ?? null,
            $data['event_data'] ?? [],
            $data['time_on_question'] ?? null,
            $data['user_action'] ?? null,
            $data['interaction_data'] ?? []
        );

        return response()->json(['status' => 'tracked']);
    }

    /**
     * Get session data (for resuming incomplete sessions)
     */
    public function getSession(Request $request, $sessionUuid)
    {
        $session = QuizSession::where('session_uuid', $sessionUuid)->firstOrFail();
        
        return response()->json([
            'session_uuid' => $session->session_uuid,
            'quiz_type' => $session->quiz_type,
            'demographics' => $session->demographics,
            'responses' => $session->responses ?? [],
            'completed' => $session->completed,
            'started_at' => $session->started_at?->toIso8601String(),
            'completed_at' => $session->completed_at?->toIso8601String(),
            'total_score' => $session->total_score,
            'risk_level' => $session->risk_level,
        ]);
    }

    /**
     * Get quiz statistics (for dashboard)
     */
    public function getStats(Request $request)
    {
        $data = Validator::validate($request->all(), [
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'shopify_domain' => ['nullable', 'string'],
        ]);

        $days = $data['days'] ?? 30;
        $since = Carbon::now()->subDays($days);
        
        $query = QuizSession::where('created_at', '>=', $since);
        
        if (!empty($data['shopify_domain'])) {
            $query->where('shopify_domain', $data['shopify_domain']);
        }

        $stats = [
            'total_sessions' => $query->count(),
            'completed_sessions' => $query->where('completed', true)->count(),
            'completion_rate' => 0,
            'average_score' => 0,
            'risk_distribution' => [
                'low' => $query->where('risk_level', 'low')->count(),
                'increasing' => $query->where('risk_level', 'increasing')->count(),
                'higher' => $query->where('risk_level', 'higher')->count(),
                'dependence' => $query->where('risk_level', 'dependence')->count(),
            ],
            'daily_completions' => [],
        ];

        $completedCount = $stats['completed_sessions'];
        if ($stats['total_sessions'] > 0) {
            $stats['completion_rate'] = round(($completedCount / $stats['total_sessions']) * 100, 1);
        }

        if ($completedCount > 0) {
            $stats['average_score'] = round($query->where('completed', true)->avg('total_score'), 1);
        }

        // Daily completions for chart
        $dailyData = $query->where('completed', true)
            ->selectRaw('DATE(completed_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $stats['daily_completions'] = $dailyData->map(function ($item) {
            return [
                'date' => $item->date,
                'count' => $item->count,
            ];
        });

        return response()->json($stats);
    }

    /**
     * Helper method to track events
     */
    private function recordEvent($sessionId, $eventType, $questionId = null, $eventData = [], $timeOnQuestion = null, $userAction = null, $interactionData = [])
    {
        QuizAnalytic::create([
            'quiz_session_id' => $sessionId,
            'event_type' => $eventType,
            'question_id' => $questionId,
            'event_data' => $eventData,
            'event_timestamp' => now(),
            'time_on_question' => $timeOnQuestion,
            'user_action' => $userAction,
            'interaction_data' => $interactionData,
        ]);
    }

}
