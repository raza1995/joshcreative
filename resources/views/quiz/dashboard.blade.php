@extends('layouts.app')

@section('title', 'Quiz Dashboard')

@push('styles')
<style>
.dashboard-card {
    border: 0;
    box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
    transition: all 0.2s ease-in-out;
    border-radius: 12px;
    overflow: hidden;
}

.dashboard-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
}

.metric-value {
    font-size: 2.25rem;
    font-weight: 800;
    margin-bottom: 0.25rem;
    color: white !important;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
}

.metric-label {
    font-size: 0.875rem;
    opacity: 0.95;
    margin-bottom: 0;
    color: rgba(255,255,255,0.95) !important;
    font-weight: 500;
    text-shadow: 0 1px 2px rgba(0,0,0,0.2);
}

.card-header.bg-gradient {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    min-height: 60px;
}

.card-header.bg-gradient h5 {
    color: white !important;
    font-weight: 600;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
    display: flex;
    align-items: center;
    font-size: 1.1rem;
    line-height: 1.2;
    margin: 0;
    flex: 1;
}

.card-header.bg-gradient i {
    color: rgba(255,255,255,0.95) !important;
    text-shadow: 0 1px 2px rgba(0,0,0,0.2);
    margin-right: 0.5rem;
    flex-shrink: 0;
}

.quick-action-btn {
    border-radius: 10px;
    border: 1px solid rgba(0,0,0,0.1);
    transition: all 0.2s ease;
    text-decoration: none;
}

.quick-action-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    text-decoration: none;
}

.high-risk-item {
    border-radius: 10px;
    transition: all 0.2s ease;
}

.high-risk-item:hover {
    transform: translateY(-1px);
}

.form-select {
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    padding: 0.5rem 0.75rem;
}

.form-select:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
}

.btn {
    border-radius: 8px;
    font-weight: 500;
    padding: 0.5rem 1rem;
}

.breadcrumb {
    background: none;
    padding: 0;
    margin-bottom: 1rem;
}

.breadcrumb-item + .breadcrumb-item::before {
    color: #6b7280;
}

.h3 {
    font-weight: 700;
    color: #1f2937;
}

.text-muted {
    color: #6b7280 !important;
}

.badge {
    font-weight: 500;
    padding: 0.4rem 0.65rem;
    border-radius: 8px;
}

.card-body {
    padding: 1.25rem;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Highlight active quiz nav item
    const quizDropdown = document.getElementById('quizDropdown');
    if (quizDropdown) {
        quizDropdown.classList.add('active');
        // Find the dashboard link and mark it active
        const dashboardLink = document.querySelector('a[href="{{ route('quiz.dashboard') }}"]');
        if (dashboardLink) {
            dashboardLink.classList.add('quiz-nav-active');
        }
    }
});
</script>
@endpush

@section('content')
<div class="container-fluid">
    <!-- Error Alert -->
    @if(isset($error))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle"></i> {{ $error }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('quiz.dashboard') }}">Quiz System</a></li>
            <li class="breadcrumb-item active" aria-current="page">Analytics Dashboard</li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Quiz Analytics Dashboard</h1>
            <p class="text-muted mb-0">Monitor quiz performance and user insights</p>
        </div>
        <div class="d-flex gap-2">
            <select class="form-select" id="dateRange" onchange="updateDashboard()">
                <option value="7" {{ $dateRange == '7' ? 'selected' : '' }}>Last 7 days</option>
                <option value="30" {{ $dateRange == '30' ? 'selected' : '' }}>Last 30 days</option>
                <option value="90" {{ $dateRange == '90' ? 'selected' : '' }}>Last 90 days</option>
                <option value="365" {{ $dateRange == '365' ? 'selected' : '' }}>Last year</option>
                <option value="all" {{ $dateRange == 'all' ? 'selected' : '' }}>All time</option>
            </select>
            <select class="form-select" id="shopifyDomain" onchange="updateDashboard()">
                <option value="">All domains</option>
                @foreach(\App\Models\QuizSession::where('quiz_type', $quizType ?? 'audit')->whereNotNull('shopify_domain')->distinct()->pluck('shopify_domain') as $domain)
                    <option value="{{ $domain }}" {{ $shopifyDomain == $domain ? 'selected' : '' }}>{{ $domain }}</option>
                @endforeach
            </select>
            <a href="{{ route('quiz.export') }}?range={{ $dateRange }}&domain={{ $shopifyDomain }}&quiz_type={{ $quizType ?? 'audit' }}" class="btn btn-success">
                <i class="fas fa-download"></i> Export CSV
            </a>
        </div>
    </div>

    <!-- Quiz Type Selector - PROMINENT -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-filter text-primary me-3 fa-lg"></i>
                            <div>
                                <h6 class="mb-0 fw-bold">Quiz Type Filter</h6>
                                <small class="text-muted">Switch between different quiz analytics</small>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <select class="form-select form-select-lg" id="quizTypeFilter" onchange="updateDashboard()" style="min-width: 200px;">
                                <option value="audit" {{ ($quizType ?? 'audit') == 'audit' ? 'selected' : '' }}>
                                    🍺 AUDIT (Alcohol Assessment)
                                </option>
                                <option value="wellness" {{ ($quizType ?? 'audit') == 'wellness' ? 'selected' : '' }}>
                                    💚 Wellness Assessment
                                </option>
                                <option value="gad7" {{ ($quizType ?? 'audit') == 'gad7' ? 'selected' : '' }}>
                                    🧠 GAD-7 (Anxiety)
                                </option>
                                <option value="phq9" {{ ($quizType ?? 'audit') == 'phq9' ? 'selected' : '' }}>
                                    😔 PHQ-9 (Depression)
                                </option>
                            </select>
                            <button class="btn btn-primary" onclick="updateDashboard()">
                                <i class="fas fa-sync-alt"></i> Apply Filter
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Overview Stats -->
    <div class="row g-3 mb-4">
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="card-body text-white p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0 fw-bold">{{ number_format($stats['total_sessions']) }}</h4>
                            <small class="opacity-90">Total Sessions</small>
                        </div>
                        <div class="opacity-75">
                            <i class="fas fa-users fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                <div class="card-body text-white p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0 fw-bold">{{ number_format($stats['completed_sessions']) }}</h4>
                            <small class="opacity-90">Completed ({{ $stats['completion_rate'] }}%)</small>
                        </div>
                        <div class="opacity-75">
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);">
                <div class="card-body text-white p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0 fw-bold">{{ number_format($stats['average_score'], 1) }}</h4>
                            <small class="opacity-90">Average Score</small>
                        </div>
                        <div class="opacity-75">
                            <i class="fas fa-chart-line fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                <div class="card-body text-white p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0 fw-bold">{{ $stats['average_time_minutes'] }}m</h4>
                            <small class="opacity-90">Avg Time</small>
                        </div>
                        <div class="opacity-75">
                            <i class="fas fa-clock fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                <div class="card-body text-white p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0 fw-bold">{{ number_format($stats['email_provided']) }}</h4>
                            <small class="opacity-90">Emails ({{ $stats['email_rate'] }}%)</small>
                        </div>
                        <div class="opacity-75">
                            <i class="fas fa-envelope fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                <div class="card-body text-white p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0 fw-bold">{{ number_format($stats['risk_distribution']['higher'] + $stats['risk_distribution']['dependence']) }}</h4>
                            <small class="opacity-90">High Risk</small>
                        </div>
                        <div class="opacity-75">
                            <i class="fas fa-exclamation-triangle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-3 mb-4">
        <!-- Daily Completions Chart -->
        <div class="col-lg-8">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-header text-white border-0" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Daily Quiz Completions</h5>
                </div>
                <div class="card-body">
                    <canvas id="dailyCompletionsChart" height="100"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Risk Distribution -->
        <div class="col-lg-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-header text-white border-0" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Risk Level Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="riskDistributionChart"></canvas>
                    <div class="mt-3">
                        <div class="d-flex justify-content-between align-items-center mb-2 p-2 rounded" style="background-color: #f0f9ff;">
                            <span class="fw-medium" style="color: #059669;"><i class="fas fa-circle me-2"></i>Low Risk</span>
                            <span class="badge bg-success">{{ $stats['risk_distribution']['low'] }}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2 p-2 rounded" style="background-color: #fffbeb;">
                            <span class="fw-medium" style="color: #ca8a04;"><i class="fas fa-circle me-2"></i>Increasing Risk</span>
                            <span class="badge bg-warning">{{ $stats['risk_distribution']['increasing'] }}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2 p-2 rounded" style="background-color: #fef2f2;">
                            <span class="fw-medium" style="color: #dc2626;"><i class="fas fa-circle me-2"></i>Higher Risk</span>
                            <span class="badge bg-danger">{{ $stats['risk_distribution']['higher'] }}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center p-2 rounded" style="background-color: #fafafa;">
                            <span class="fw-medium" style="color: #7f1d1d;"><i class="fas fa-circle me-2"></i>Possible Dependence</span>
                            <span class="badge bg-dark">{{ $stats['risk_distribution']['dependence'] }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Score Distribution Chart -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-gradient text-white" style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Score Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="scoreDistributionChart" height="80"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card dashboard-card h-100">
                <div class="card-header text-white" style="background: #8b5cf6; background-image: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); border-top-left-radius: .5rem; border-top-right-radius: .5rem;">
                    <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-3">
                        <a href="{{ route('quiz.sessions') }}?quiz_type={{ $quizType ?? 'audit' }}" class="btn btn-outline-primary btn-lg d-flex align-items-center quick-action-btn">
                            <i class="fas fa-list me-3"></i>
                            <div class="text-start">
                                <div class="fw-bold">View All Sessions</div>
                                <small class="text-muted">Browse complete session history</small>
                            </div>
                        </a>
                        <a href="{{ route('quiz.analytics') }}?quiz_type={{ $quizType ?? 'audit' }}" class="btn btn-outline-info btn-lg d-flex align-items-center quick-action-btn">
                            <i class="fas fa-chart-bar me-3"></i>
                            <div class="text-start">
                                <div class="fw-bold">Detailed Analytics</div>
                                <small class="text-muted">Advanced insights and trends</small>
                            </div>
                        </a>
                        <a href="{{ route('quiz.sessions') }}?completed=false&quiz_type={{ $quizType ?? 'audit' }}" class="btn btn-outline-warning btn-lg d-flex align-items-center quick-action-btn">
                            <i class="fas fa-exclamation-triangle me-3"></i>
                            <div class="text-start">
                                <div class="fw-bold">Incomplete Sessions</div>
                                <small class="text-muted">Users who didn't finish</small>
                            </div>
                        </a>
                        <a href="{{ route('quiz.sessions') }}?risk_level=dependence&quiz_type={{ $quizType ?? 'audit' }}" class="btn btn-outline-danger btn-lg d-flex align-items-center quick-action-btn">
                            <i class="fas fa-exclamation-circle me-3"></i>
                            <div class="text-start">
                                <div class="fw-bold">High Risk Results</div>
                                <small class="text-muted">Users needing immediate attention</small>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="card dashboard-card h-100">
                <div class="card-header text-white" style="background-color: #ef4444;">
                    <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Recent High-Risk Sessions</h5>
                </div>
                <div class="card-body">
                    @php
                        $highRiskSessions = \App\Models\QuizSession::where('quiz_type', $quizType ?? 'audit')
                            ->where(function($q) {
                                $q->where('risk_level', 'dependence')
                                  ->orWhere('risk_level', 'higher');
                            })
                            ->orderBy('completed_at', 'desc')
                            ->limit(5)
                            ->get();
                    @endphp
                    
                    @forelse($highRiskSessions as $session)
                        <div class="d-flex justify-content-between align-items-center mb-3 p-3 rounded-3 high-risk-item" style="background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); border-left: 4px solid #ef4444;">
                            <div>
                                <div class="fw-bold text-dark">Score: {{ $session->total_score }}/40</div>
                                <div class="d-flex align-items-center mt-1">
                                    <span class="badge {{ $session->risk_level === 'dependence' ? 'bg-danger' : 'bg-warning' }} me-2">
                                        {{ $session->risk_level === 'dependence' ? 'Possible Dependence' : 'Higher Risk' }}
                                    </span>
                                    @if($session->user_email)
                                        <i class="fas fa-envelope text-muted" title="Email provided"></i>
                                    @endif
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="text-muted small">{{ $session->completed_at?->diffForHumans() }}</div>
                                <a href="{{ route('quiz.session-detail', $session->session_uuid) }}" class="btn btn-sm btn-outline-danger mt-1">
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
    </div>
</div>

<!-- AI Analytics Section -->
@if(isset($aiAnalytics) && $aiAnalytics['total_analyzed'] > 0)
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <h5 class="mb-0"><i class="fas fa-robot me-2" style="color: #FFD700;"></i>🤖 AI-Powered Mycolean Conversion Analytics</h5>
            </div>
            <div class="card-body">
                <!-- AI Overview Metrics -->
                <div class="row mb-4">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h4 class="fw-bold">{{ $aiAnalytics['total_analyzed'] }}</h4>
                                <small class="opacity-90">Users Analyzed</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h4 class="fw-bold">{{ round($aiAnalytics['avg_conversion_probability'] * 100) }}%</h4>
                                <small class="opacity-90">Avg Conversion Probability</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h4 class="fw-bold">{{ round($aiAnalytics['avg_mycolean_fit'] * 100) }}%</h4>
                                <small class="opacity-90">Avg Mycolean Fit Score</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <h4 class="fw-bold">${{ number_format($aiAnalytics['revenue_potential']['potential_revenue'], 2) }}</h4>
                                <small class="opacity-90">Potential Revenue</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Conversion Segments -->
                <div class="row mb-4">
                    <div class="col-md-6">
                            <div class="card">
                                <div class="card-header text-white" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                                    <h6 class="mb-0"><i class="fas fa-target me-2" style="color: #FFD700;"></i>🎯 Conversion Segments</h6>
                                </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-bold text-success">High Conversion (70%+)</span>
                                        <span class="badge bg-success">{{ $aiAnalytics['conversion_segments']['high']['count'] }} users</span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar bg-success" style="width: {{ $aiAnalytics['conversion_segments']['high']['percentage'] }}%"></div>
                                    </div>
                                    <small class="text-muted">{{ $aiAnalytics['conversion_segments']['high']['percentage'] }}% of analyzed users</small>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-bold text-warning">Medium Conversion (50-69%)</span>
                                        <span class="badge bg-warning">{{ $aiAnalytics['conversion_segments']['medium']['count'] }} users</span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar bg-warning" style="width: {{ $aiAnalytics['conversion_segments']['medium']['percentage'] }}%"></div>
                                    </div>
                                    <small class="text-muted">{{ $aiAnalytics['conversion_segments']['medium']['percentage'] }}% of analyzed users</small>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-bold text-danger">Low Conversion (<50%)</span>
                                        <span class="badge bg-danger">{{ $aiAnalytics['conversion_segments']['low']['count'] }} users</span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar bg-danger" style="width: {{ $aiAnalytics['conversion_segments']['low']['percentage'] }}%"></div>
                                    </div>
                                    <small class="text-muted">{{ $aiAnalytics['conversion_segments']['low']['percentage'] }}% of analyzed users</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header text-white" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                <h6 class="mb-0"><i class="fas fa-dollar-sign me-2" style="color: #00FF00;"></i>💰 Revenue Insights</h6>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-12 mb-3">
                                        <div class="bg-light p-3 rounded">
                                            <h5 class="text-primary mb-1">${{ number_format($aiAnalytics['revenue_potential']['avg_revenue_per_session'], 2) }}</h5>
                                            <small class="text-muted">Average Revenue Per Session</small>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="bg-light p-3 rounded">
                                            <h5 class="text-success mb-1">{{ $aiAnalytics['revenue_potential']['high_value_prospects'] }}</h5>
                                            <small class="text-muted">High-Value Prospects (80%+ conversion)</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="alert alert-info">
                                    <small><i class="fas fa-info-circle me-1"></i>Based on $59.99 average Mycolean order value</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Conversion Factors -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header text-white" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                                <h6 class="mb-0"><i class="fas fa-search me-2" style="color: #FF4500;"></i>🔍 Top Conversion Factors</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    @foreach($aiAnalytics['top_conversion_factors'] as $factor => $data)
                                    <div class="col-md-4 col-sm-6 mb-3">
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                @switch($factor)
                                                    @case('control_issues')
                                                        <i class="fas fa-exclamation-triangle text-warning"></i>
                                                        @break
                                                    @case('social_drinking')
                                                        <i class="fas fa-users text-primary"></i>
                                                        @break
                                                    @case('binge_tendency')
                                                        <i class="fas fa-wine-glass-alt text-danger"></i>
                                                        @break
                                                    @case('age_25_44')
                                                        <i class="fas fa-user text-info"></i>
                                                        @break
                                                    @case('email_provided')
                                                        <i class="fas fa-envelope text-success"></i>
                                                        @break
                                                @endswitch
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="fw-bold">{{ ucfirst(str_replace('_', ' ', $factor)) }}</div>
                                                <div class="progress" style="height: 6px;">
                                                    <div class="progress-bar" style="width: {{ $data['percentage'] }}%"></div>
                                                </div>
                                                <small class="text-muted">{{ $data['percentage'] }}% ({{ $data['count'] }} users)</small>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

<!-- OpenAI ML Insights Section -->
@if(isset($mlInsights) && !empty($mlInsights))
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header text-white" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);">
                <h5 class="mb-0"><i class="fas fa-brain me-2" style="color: #00FFFF;"></i>🧠 OpenAI Machine Learning Insights</h5>
                <small class="opacity-90">Generated: {{ \Carbon\Carbon::parse($mlInsights['generated_at'])->diffForHumans() }}</small>
            </div>
            <div class="card-body">
                <!-- ML Overview -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h4 class="fw-bold">{{ $apiUsageStats['total_ml_analyses'] ?? 0 }}</h4>
                                <small class="opacity-90">ML Analyses Run</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h4 class="fw-bold">{{ $apiUsageStats['total_user_insights'] ?? 0 }}</h4>
                                <small class="opacity-90">User Insights Generated</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <h4 class="fw-bold">{{ $apiUsageStats['submission_count'] ?? 0 }}</h4>
                                <small class="opacity-90">Total Submissions</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <small class="fw-bold">{{ $apiUsageStats['next_analysis_at'] ?? 'N/A' }}</small>
                                <small class="opacity-90 d-block">Next ML Analysis</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Key Patterns -->
                @if(isset($mlInsights['patterns']))
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header text-white" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                                <h6 class="mb-0"><i class="fas fa-arrow-up me-2" style="color: #00FF00;"></i>🎯 High Conversion Factors</h6>
                            </div>
                            <div class="card-body">
                                @if(isset($mlInsights['patterns']['high_conversion_factors']))
                                    <ul class="list-unstyled">
                                        @foreach($mlInsights['patterns']['high_conversion_factors'] as $factor)
                                        <li class="mb-2">
                                            <i class="fas fa-arrow-up text-success me-2"></i>{{ $factor }}
                                        </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p class="text-muted">No specific factors identified yet.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header text-white" style="background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);">
                                <h6 class="mb-0"><i class="fas fa-arrow-down me-2" style="color: #FF0000;"></i>⚠️ Low Conversion Factors</h6>
                            </div>
                            <div class="card-body">
                                @if(isset($mlInsights['patterns']['low_conversion_factors']))
                                    <ul class="list-unstyled">
                                        @foreach($mlInsights['patterns']['low_conversion_factors'] as $factor)
                                        <li class="mb-2">
                                            <i class="fas fa-arrow-down text-danger me-2"></i>{{ $factor }}
                                        </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p class="text-muted">No specific factors identified yet.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Recommendations -->
                @if(isset($mlInsights['recommendations']))
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                <h6 class="mb-0"><i class="fas fa-lightbulb me-2" style="color: #FFD700;"></i>💡 AI Recommendations</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    @if(isset($mlInsights['recommendations']['marketing_focus']))
                                    <div class="col-md-6 mb-3">
                                        <div class="bg-light p-3 rounded">
                                            <h6 class="text-primary">🎯 Marketing Focus</h6>
                                            <p class="mb-0">{{ $mlInsights['recommendations']['marketing_focus'] }}</p>
                                        </div>
                                    </div>
                                    @endif
                                    
                                    @if(isset($mlInsights['recommendations']['product_positioning']))
                                    <div class="col-md-6 mb-3">
                                        <div class="bg-light p-3 rounded">
                                            <h6 class="text-success">📦 Product Positioning</h6>
                                            <p class="mb-0">{{ $mlInsights['recommendations']['product_positioning'] }}</p>
                                        </div>
                                    </div>
                                    @endif
                                    
                                    @if(isset($mlInsights['recommendations']['email_optimization']))
                                    <div class="col-md-6 mb-3">
                                        <div class="bg-light p-3 rounded">
                                            <h6 class="text-info">📧 Email Optimization</h6>
                                            <p class="mb-0">{{ $mlInsights['recommendations']['email_optimization'] }}</p>
                                        </div>
                                    </div>
                                    @endif
                                    
                                    @if(isset($mlInsights['predictions']['revenue_opportunities']))
                                    <div class="col-md-6 mb-3">
                                        <div class="bg-light p-3 rounded">
                                            <h6 class="text-warning">💰 Revenue Opportunities</h6>
                                            <p class="mb-0">{{ $mlInsights['predictions']['revenue_opportunities'] }}</p>
                                        </div>
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Action Items -->
                @if(isset($mlInsights['action_items']) && count($mlInsights['action_items']) > 0)
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header text-white" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                                <h6 class="mb-0"><i class="fas fa-bolt me-2" style="color: #FF4500;"></i>⚡ Priority Action Items</h6>
                            </div>
                            <div class="card-body">
                                @foreach($mlInsights['action_items'] as $item)
                                <div class="d-flex align-items-start mb-3 p-3 rounded {{ $item['priority'] === 'high' ? 'bg-danger bg-opacity-10' : 'bg-warning bg-opacity-10' }}">
                                    <div class="me-3">
                                        @if($item['priority'] === 'high')
                                            <span class="badge bg-danger">HIGH</span>
                                        @else
                                            <span class="badge bg-warning">MEDIUM</span>
                                        @endif
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1">{{ $item['action'] }}</h6>
                                        <small class="text-muted">Expected Impact: {{ $item['expected_impact'] }}</small>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Insights Summary -->
                @if(isset($mlInsights['insights']))
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="alert alert-info">
                            <h6><i class="fas fa-lightbulb me-2"></i>Key Insights Summary</h6>
                            @if(isset($mlInsights['insights']['surprising_findings']))
                                <p><strong>Surprising Findings:</strong></p>
                                <ul>
                                    @foreach($mlInsights['insights']['surprising_findings'] as $finding)
                                    <li>{{ $finding }}</li>
                                    @endforeach
                                </ul>
                            @endif
                            
                            @if(isset($mlInsights['insights']['behavioral_patterns']))
                                <p><strong>Behavioral Patterns:</strong> {{ $mlInsights['insights']['behavioral_patterns'] }}</p>
                            @endif
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@elseif(isset($apiUsageStats))
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="fas fa-clock me-2"></i>OpenAI ML Analysis Status</h5>
            </div>
            <div class="card-body text-center">
                <div class="row">
                    <div class="col-md-4">
                        <h4 class="text-primary">{{ $apiUsageStats['submission_count'] ?? 0 }}</h4>
                        <small class="text-muted">Total Submissions</small>
                    </div>
                    <div class="col-md-4">
                        <h4 class="text-info">{{ $apiUsageStats['total_ml_analyses'] ?? 0 }}</h4>
                        <small class="text-muted">ML Analyses Completed</small>
                    </div>
                    <div class="col-md-4">
                        <h4 class="text-success">{{ $apiUsageStats['next_analysis_at'] ?? 'N/A' }}</h4>
                        <small class="text-muted">Next Analysis</small>
                    </div>
                </div>
                <div class="mt-3">
                    <p class="text-muted mb-0">
                        <i class="fas fa-info-circle me-1"></i>
                        ML analysis runs automatically every 5 quiz submissions to provide advanced insights.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Update dashboard when filters change
function updateDashboard() {
    const range = document.getElementById('dateRange').value;
    const domain = document.getElementById('shopifyDomain').value;
    const quizType = document.getElementById('quizTypeFilter').value;
    
    const url = new URL(window.location);
    url.searchParams.set('range', range);
    url.searchParams.set('quiz_type', quizType);
    
    if (domain) {
        url.searchParams.set('domain', domain);
    } else {
        url.searchParams.delete('domain');
    }
    window.location = url;
}

// Chart data from backend
const chartData = @json($chartData);

// Daily Completions Chart
const dailyCtx = document.getElementById('dailyCompletionsChart').getContext('2d');
new Chart(dailyCtx, {
    type: 'line',
    data: {
        labels: chartData.daily_completions.map(d => d.date),
        datasets: [{
            label: 'Completions',
            data: chartData.daily_completions.map(d => d.count),
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Risk Distribution Pie Chart
const riskCtx = document.getElementById('riskDistributionChart').getContext('2d');
new Chart(riskCtx, {
    type: 'doughnut',
    data: {
        labels: ['Low Risk', 'Increasing Risk', 'Higher Risk', 'Possible Dependence'],
        datasets: [{
            data: [
                {{ $stats['risk_distribution']['low'] }},
                {{ $stats['risk_distribution']['increasing'] }},
                {{ $stats['risk_distribution']['higher'] }},
                {{ $stats['risk_distribution']['dependence'] }}
            ],
            backgroundColor: [
                '#28a745',
                '#ffc107',
                '#dc3545',
                '#6c757d'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true
    }
});

// Score Distribution Chart
const scoreCtx = document.getElementById('scoreDistributionChart').getContext('2d');
new Chart(scoreCtx, {
    type: 'bar',
    data: {
        labels: chartData.score_distribution.map(d => `Score ${d.score}`),
        datasets: [{
            label: 'Number of Users',
            data: chartData.score_distribution.map(d => d.count),
            backgroundColor: 'rgba(54, 162, 235, 0.5)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
</script>
@endsection

@push('styles')
<style>
    .dashboard-card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .dashboard-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
    }
    
    .metric-value {
        font-size: 2rem;
        font-weight: 700;
        line-height: 1;
        margin-bottom: 5px;
    }
    
    .metric-label {
        font-size: 0.9rem;
        opacity: 0.9;
        font-weight: 500;
    }
    
    .chart-container {
        position: relative;
        height: 300px;
        width: 100%;
    }
    
    .quick-action-card {
        border: 2px dashed #e2e8f0;
        transition: all 0.2s ease;
    }
    
    .quick-action-card:hover {
        border-color: #667eea;
        background-color: #f7fafc;
    }
    
    .high-risk-session {
        border-left: 4px solid #dc2626;
        background: linear-gradient(135deg, #fef2f2 0%, #ffffff 100%);
    }
    
    .email-indicator {
        width: 8px;
        height: 8px;
        background: #10b981;
        border-radius: 50%;
        display: inline-block;
        margin-left: 5px;
    }
    
    .no-email-indicator {
        width: 8px;
        height: 8px;
        background: #e5e7eb;
        border-radius: 50%;
        display: inline-block;
        margin-left: 5px;
    }
    
    .breadcrumb {
        background: none;
        padding: 0;
        margin-bottom: 1rem;
    }
    
    .breadcrumb-item + .breadcrumb-item::before {
        content: ">";
        color: #6c757d;
    }
    
.quiz-nav-active {
    background-color: rgba(255, 255, 255, 0.1) !important;
    border-radius: 4px;
}

/* Text readability improvements */
.text-white h4, .text-white .fw-bold {
    color: white !important;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
}

.text-white small, .text-white .opacity-90 {
    color: rgba(255,255,255,0.95) !important;
    text-shadow: 0 1px 2px rgba(0,0,0,0.2);
}

.text-white i {
    color: rgba(255,255,255,0.9) !important;
    text-shadow: 0 1px 2px rgba(0,0,0,0.2);
}

/* Card header text improvements */
.card-header.text-white h5 {
    color: white !important;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
    font-weight: 600;
}

.card-header.text-white i {
    color: rgba(255,255,255,0.95) !important;
    text-shadow: 0 1px 2px rgba(0,0,0,0.2);
}
</style>
@endpush
