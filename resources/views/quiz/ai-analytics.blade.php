@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">🧠 AI Analytics - Detailed Quiz Data & Marketing Insights</h1>
                    <p class="text-muted">Comprehensive analytics with actionable marketing theory based on actual quiz submissions and user behavior</p>
                </div>
                <div>
                    <select id="quizTypeFilter" class="form-select" onchange="updateAnalytics()">
                        <option value="audit" {{ ($quizType ?? 'audit') === 'audit' ? 'selected' : '' }}>AUDIT Quiz</option>
                        <option value="gad7" {{ ($quizType ?? 'audit') === 'gad7' ? 'selected' : '' }}>GAD-7 Quiz</option>
                        <option value="phq9" {{ ($quizType ?? 'audit') === 'phq9' ? 'selected' : '' }}>PHQ-9 Quiz</option>
                        <option value="wellness" {{ ($quizType ?? 'audit') === 'wellness' ? 'selected' : '' }}>Wellness Quiz</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    @if(isset($error))
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-2"></i>{{ $error }}
    </div>
    @endif

    <!-- Performance Overview -->
    @if(isset($performanceOverview))
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <h5 class="mb-0">📊 Performance Overview</h5>
                </div>
                <div class="card-body">
                    <!-- Marketing Theory Alert -->
                    <div class="alert alert-info mb-4">
                        <h6 class="alert-heading"><i class="fas fa-lightbulb me-2"></i>💡 Marketing Theory: Performance Metrics</h6>
                        <p class="mb-2"><strong>Completion Rate:</strong> Industry benchmark is 15-25% for health assessments. Higher rates indicate strong user engagement and quiz relevance.</p>
                        <p class="mb-2"><strong>Email Capture:</strong> 30%+ is excellent for health quizzes. This creates your retargeting audience for Mycolean campaigns.</p>
                        <p class="mb-0"><strong>High Risk Rate:</strong> These users have the highest conversion potential - they need your product most urgently.</p>
                    </div>
                    
                    <div class="row">
                        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <h4 class="fw-bold">{{ number_format($performanceOverview['total_sessions']) }}</h4>
                                    <small class="opacity-90">Total Sessions</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h4 class="fw-bold">{{ $performanceOverview['completion_rate'] }}%</h4>
                                    <small class="opacity-90">Completion Rate</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <h4 class="fw-bold">{{ $performanceOverview['avg_score'] }}</h4>
                                    <small class="opacity-90">Average Score</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center">
                                    <h4 class="fw-bold">{{ $performanceOverview['avg_time_minutes'] }}m</h4>
                                    <small class="opacity-90">Avg Time</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                            <div class="card bg-danger text-white">
                                <div class="card-body text-center">
                                    <h4 class="fw-bold">{{ $performanceOverview['email_rate'] }}%</h4>
                                    <small class="opacity-90">Email Capture</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                            <div class="card bg-dark text-white">
                                <div class="card-body text-center">
                                    <h4 class="fw-bold">{{ $performanceOverview['high_risk_rate'] }}%</h4>
                                    <small class="opacity-90">High Risk Rate</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Score Distribution & Geographic Distribution -->
    <div class="row mb-4">
        @if(isset($scoreDistribution))
        <div class="col-md-6">
            <div class="card">
                <div class="card-header text-white" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);">
                    <h6 class="mb-0">📊 Score Distribution</h6>
                </div>
                <div class="card-body">
                    <!-- Marketing Theory -->
                    <div class="alert alert-warning mb-3">
                        <h6 class="alert-heading">💡 Marketing Theory: Score Psychology</h6>
                        <p class="mb-1"><strong>Low Scores (0-7):</strong> Prevention messaging - "Stay healthy with Mycolean"</p>
                        <p class="mb-1"><strong>Medium Scores (8-15):</strong> Early intervention - "Take control before it's too late"</p>
                        <p class="mb-0"><strong>High Scores (16+):</strong> Urgent solution - "Get help now with Mycolean"</p>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="text-primary">Average Score: {{ round($scoreDistribution['avg_score'], 1) }}</h6>
                        <small class="text-muted">Based on {{ number_format($scoreDistribution['total_completed']) }} completed sessions</small>
                    </div>
                    
                    @foreach($scoreDistribution['distribution'] as $range => $data)
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold">{{ $range }} - {{ $data['label'] }}</span>
                            <span class="badge bg-primary">{{ $data['count'] }} users</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar" style="width: {{ $data['percentage'] }}%"></div>
                        </div>
                        <small class="text-muted">{{ $data['percentage'] }}% of completed sessions</small>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        @if(isset($geographicDistribution))
        <div class="col-md-6">
            <div class="card">
                <div class="card-header text-white" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%) !important;">
                    <h6 class="mb-0">🌍 Geographic Distribution</h6>
                </div>
                <div class="card-body">
                    <!-- Marketing Theory -->
                    <div class="alert alert-info mb-3">
                        <h6 class="alert-heading">💡 Marketing Theory: Geographic Targeting</h6>
                        <p class="mb-1"><strong>Top Domains:</strong> Focus ad spend and partnerships here</p>
                        <p class="mb-0"><strong>Expansion Strategy:</strong> Replicate successful domain strategies in similar markets</p>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="text-primary">Total Domains: {{ $geographicDistribution['total_domains'] }}</h6>
                    </div>
                    
                    @if(count($geographicDistribution['domains']) > 0)
                        @foreach(array_slice($geographicDistribution['domains'], 0, 10) as $domain)
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>{{ $domain['shopify_domain'] ?? 'Unknown' }}</span>
                            <span class="badge bg-info">{{ $domain['count'] }} sessions</span>
                        </div>
                        @endforeach
                    @else
                        <p class="text-muted">No geographic data available yet.</p>
                    @endif
                </div>
            </div>
        </div>
        @endif
    </div>

    <!-- Engagement Metrics & Demographics -->
    <div class="row mb-4">
        @if(isset($engagementMetrics))
        <div class="col-md-6">
            <div class="card">
                <div class="card-header text-white" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <h6 class="mb-0">📈 Engagement Metrics</h6>
                </div>
                <div class="card-body">
                    <!-- Marketing Theory -->
                    <div class="alert alert-success mb-3">
                        <h6 class="alert-heading">💡 Marketing Theory: Engagement Psychology</h6>
                        <p class="mb-1"><strong>High Engagement:</strong> Users are invested - perfect for retargeting campaigns</p>
                        <p class="mb-0"><strong>Time Per Question:</strong> <10s = rushed (low intent), >30s = thoughtful (high intent)</p>
                    </div>
                    
                    <div class="row text-center mb-3">
                        <div class="col-6">
                            <div class="bg-light p-3 rounded">
                                <h5 class="text-primary mb-1">{{ number_format($engagementMetrics['total_events']) }}</h5>
                                <small class="text-muted">Total Events</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-light p-3 rounded">
                                <h5 class="text-success mb-1">{{ $engagementMetrics['engagement_score'] }}</h5>
                                <small class="text-muted">Engagement Score</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="text-info">Event Types</h6>
                        @foreach(array_slice($engagementMetrics['event_types'], 0, 5) as $event)
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span>{{ ucfirst(str_replace('_', ' ', $event['event_type'])) }}</span>
                            <span class="badge bg-secondary">{{ $event['count'] }}</span>
                        </div>
                        @endforeach
                    </div>
                    
                    <div class="alert alert-info">
                        <small><i class="fas fa-clock me-1"></i>Avg time per question: {{ $engagementMetrics['avg_time_per_question'] }}s</small>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if(isset($demographicAnalysis))
        <div class="col-md-6">
            <div class="card">
                <div class="card-header text-white" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <h6 class="mb-0">👥 Demographics</h6>
                </div>
                <div class="card-body">
                    <!-- Marketing Theory -->
                    <div class="alert alert-warning mb-3">
                        <h6 class="alert-heading">💡 Marketing Theory: Demographic Targeting</h6>
                        <p class="mb-1"><strong>Age 25-35:</strong> Career stress, social drinking - focus on "balance" messaging</p>
                        <p class="mb-1"><strong>Age 35-45:</strong> Health awareness peaks - emphasize "prevention" and "family"</p>
                        <p class="mb-0"><strong>Gender Differences:</strong> Men respond to "performance", women to "wellness"</p>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="text-primary">Age Groups</h6>
                        @foreach($demographicAnalysis['age_groups'] as $age => $count)
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span>{{ $age }}</span>
                            <span class="badge bg-primary">{{ $count }}</span>
                        </div>
                        @endforeach
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="text-success">Gender Distribution</h6>
                        @foreach($demographicAnalysis['genders'] as $gender => $count)
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span>{{ ucfirst($gender) }}</span>
                            <span class="badge bg-success">{{ $count }}</span>
                        </div>
                        @endforeach
                    </div>
                    
                    <div class="alert alert-info">
                        <small><i class="fas fa-info-circle me-1"></i>{{ number_format($demographicAnalysis['total_with_demographics']) }} sessions with demographic data</small>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>

    <!-- Risk Level Analysis & Time Analysis -->
    <div class="row mb-4">
        @if(isset($riskLevelAnalysis))
        <div class="col-md-6">
            <div class="card">
                <div class="card-header text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <h6 class="mb-0">⚠️ Risk Level Analysis</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="text-danger">High Risk Sessions: {{ $riskLevelAnalysis['high_risk_count'] }}</h6>
                        <small class="text-muted">Out of {{ number_format($riskLevelAnalysis['total_completed']) }} completed sessions</small>
                    </div>
                    
                    @foreach($riskLevelAnalysis['distribution'] as $level => $data)
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold">{{ ucfirst($level) }} Risk</span>
                            <span class="badge bg-{{ $level === 'dependence' ? 'danger' : ($level === 'higher' ? 'warning' : 'success') }}">{{ $data['count'] }}</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-{{ $level === 'dependence' ? 'danger' : ($level === 'higher' ? 'warning' : 'success') }}" style="width: {{ $data['percentage'] }}%"></div>
                        </div>
                        <small class="text-muted">{{ $data['percentage'] }}% of completed sessions</small>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        @if(isset($timeAnalysis))
        <div class="col-md-6">
            <div class="card">
                <div class="card-header text-white" style="background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);">
                    <h6 class="mb-0">⏱️ Time Analysis</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center mb-3">
                        <div class="col-4">
                            <div class="bg-light p-2 rounded">
                                <h6 class="text-primary mb-0">{{ round($timeAnalysis['avg_time']/60, 1) }}m</h6>
                                <small class="text-muted">Average</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="bg-light p-2 rounded">
                                <h6 class="text-success mb-0">{{ round($timeAnalysis['median_time']/60, 1) }}m</h6>
                                <small class="text-muted">Median</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="bg-light p-2 rounded">
                                <h6 class="text-info mb-0">{{ round($timeAnalysis['max_time']/60, 1) }}m</h6>
                                <small class="text-muted">Maximum</small>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h6 class="text-primary">Time Distribution</h6>
                        @foreach($timeAnalysis['time_ranges'] as $range => $count)
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span>{{ $range }}</span>
                            <span class="badge bg-info">{{ $count }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>

    <!-- Marketing Strategy Insights -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <h5 class="mb-0">🎯 Marketing Strategy & Action Plan</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="alert alert-primary">
                                <h6 class="alert-heading">🎯 Target Audience Strategy</h6>
                                <p class="mb-2"><strong>Primary Target:</strong> High-risk users (scores 16+) - immediate need, highest conversion</p>
                                <p class="mb-2"><strong>Secondary Target:</strong> Medium-risk users (scores 8-15) - prevention messaging</p>
                                <p class="mb-0"><strong>Retargeting Pool:</strong> Email subscribers for nurture campaigns</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="alert alert-success">
                                <h6 class="alert-heading">💰 Revenue Opportunities</h6>
                                <p class="mb-2"><strong>Immediate:</strong> Target high-risk users with urgent messaging</p>
                                <p class="mb-2"><strong>Medium-term:</strong> Email nurture sequences for medium-risk users</p>
                                <p class="mb-0"><strong>Long-term:</strong> Prevention campaigns for low-risk users</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="alert alert-warning">
                                <h6 class="alert-heading">📱 Campaign Recommendations</h6>
                                <p class="mb-1"><strong>Facebook/Instagram:</strong> Target by age groups with highest engagement</p>
                                <p class="mb-1"><strong>Google Ads:</strong> Target "alcohol alternatives" + geographic winners</p>
                                <p class="mb-0"><strong>Email Marketing:</strong> Segment by risk level for personalized messaging</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="alert alert-info">
                                <h6 class="alert-heading">🚀 Conversion Optimization</h6>
                                <p class="mb-1"><strong>Landing Pages:</strong> Create risk-level specific pages</p>
                                <p class="mb-1"><strong>Messaging:</strong> Match urgency to user's risk level</p>
                                <p class="mb-0"><strong>Timing:</strong> Follow up within 24h for high-risk users</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions & Recent High-Risk Sessions -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <h6 class="mb-0">⚡ Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ route('quiz.sessions', ['quiz_type' => $quizType]) }}" class="btn btn-primary">
                            <i class="fas fa-list me-2"></i>View All Sessions
                        </a>
                        <a href="{{ route('quiz.sessions', ['quiz_type' => $quizType, 'completed' => 'false']) }}" class="btn btn-warning">
                            <i class="fas fa-clock me-2"></i>Incomplete Sessions
                        </a>
                        <a href="{{ route('quiz.sessions', ['quiz_type' => $quizType, 'risk_level' => 'dependence']) }}" class="btn btn-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>High Risk Users
                        </a>
                        <a href="{{ route('quiz.export', ['quiz_type' => $quizType]) }}" class="btn btn-success">
                            <i class="fas fa-download me-2"></i>Export Data
                        </a>
                    </div>
                </div>
            </div>
        </div>

        @if(isset($recentHighRiskSessions))
        <div class="col-md-6">
            <div class="card">
                <div class="card-header text-white" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);">
                    <h6 class="mb-0">🚨 Recent High-Risk Sessions</h6>
                </div>
                <div class="card-body">
                    @forelse($recentHighRiskSessions as $session)
                    <div class="d-flex justify-content-between align-items-center mb-3 p-3 rounded {{ $session['risk_level'] === 'dependence' ? 'bg-danger bg-opacity-10' : 'bg-warning bg-opacity-10' }}">
                        <div>
                            <div class="fw-bold">Score: {{ $session['total_score'] }}/40</div>
                            <small class="text-muted">{{ \Carbon\Carbon::parse($session['completed_at'])->diffForHumans() }}</small>
                            <div class="mt-1">
                                <span class="badge bg-{{ $session['risk_level'] === 'dependence' ? 'danger' : 'warning' }}">
                                    {{ ucfirst($session['risk_level']) }} Risk
                                </span>
                                @if($session['has_email'])
                                <span class="badge bg-success ms-1">
                                    <i class="fas fa-envelope"></i>
                                </span>
                                @endif
                            </div>
                        </div>
                        <div class="text-end">
                            <a href="{{ route('quiz.session-detail', $session['session_uuid']) }}" class="btn btn-sm btn-outline-danger">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </div>
                    </div>
                    @empty
                    <div class="text-center py-4">
                        <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                        <p class="text-muted mb-0">No high-risk sessions found.</p>
                        <small class="text-muted">This is good news!</small>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
        @endif
    </div>

    <!-- OpenAI ML Insights -->
    @if(isset($mlInsights) && !empty($mlInsights))
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header text-white" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);">
                    <h6 class="mb-0">🤖 OpenAI Advanced Insights</h6>
                    <small class="opacity-90">Generated: {{ \Carbon\Carbon::parse($mlInsights['generated_at'])->diffForHumans() }}</small>
                </div>
                <div class="card-body">
                    @if(isset($mlInsights['recommendations']))
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="text-primary">AI Recommendations</h6>
                            @if(isset($mlInsights['recommendations']['marketing_focus']))
                            <div class="alert alert-info">
                                <strong>Focus:</strong> {{ $mlInsights['recommendations']['marketing_focus'] }}
                            </div>
                            @endif
                        </div>
                        <div class="col-md-6">
                            @if(isset($mlInsights['predictions']['revenue_opportunities']))
                            <div class="alert alert-warning">
                                <strong>Opportunities:</strong> {{ $mlInsights['predictions']['revenue_opportunities'] }}
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif

                    @if(isset($mlInsights['action_items']) && count($mlInsights['action_items']) > 0)
                    <div class="row">
                        <div class="col-12">
                            <h6 class="text-danger">Priority Action Items</h6>
                            @foreach($mlInsights['action_items'] as $item)
                            <div class="alert {{ $item['priority'] === 'high' ? 'alert-danger' : 'alert-warning' }} d-flex align-items-start">
                                <div class="me-3">
                                    <span class="badge {{ $item['priority'] === 'high' ? 'bg-danger' : 'bg-warning' }}">{{ strtoupper($item['priority']) }}</span>
                                </div>
                                <div>
                                    <strong>{{ $item['action'] }}</strong>
                                    <br><small>Expected Impact: {{ $item['expected_impact'] }}</small>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

<script>
function updateAnalytics() {
    const quizType = document.getElementById('quizTypeFilter').value;
    const url = new URL(window.location);
    url.searchParams.set('quiz_type', quizType);
    window.location = url;
}
</script>
@endsection