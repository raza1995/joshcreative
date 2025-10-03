@extends('layouts.app')

@section('title', 'Detailed Quiz Analytics')

@push('styles')
<style>
/* Modern Card Styling */
.card {
    border-radius: 15px;
    transition: all 0.3s ease;
    border: none;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

/* Card Headers */
.card-header {
    border-radius: 15px 15px 0 0 !important;
    padding: 1rem 1.25rem;
    border: none;
    position: relative;
    display: flex;
    align-items: center;
    min-height: 60px;
}

.card-header h5 {
    margin: 0;
    font-weight: 600;
    display: flex;
    align-items: center;
    color: white !important;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
    font-size: 1.1rem;
    line-height: 1.2;
    flex: 1;
}

.card-header i {
    opacity: 0.95;
    color: white !important;
    text-shadow: 0 1px 2px rgba(0,0,0,0.2);
    margin-right: 0.5rem;
    flex-shrink: 0;
}

/* Card Body */
.card-body {
    padding: 1.5rem;
}

/* Metric Cards */
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

/* Text on colored backgrounds */
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

/* Funnel Steps */
.funnel-step h4 {
    color: #1f2937 !important;
    font-weight: 700;
}

.funnel-step .text-muted {
    color: #6b7280 !important;
}

/* Badge improvements */
.badge {
    font-weight: 600;
    text-shadow: none;
}

.progress-custom {
    height: 8px;
    border-radius: 4px;
    background-color: rgba(255,255,255,0.2);
}

.progress-custom .progress-bar {
    border-radius: 4px;
}

.chart-container {
    position: relative;
    height: 300px;
}

.insight-badge {
    font-size: 0.75rem;
    padding: 0.35rem 0.65rem;
    border-radius: 12px;
    font-weight: 500;
}

.demographic-item {
    padding: 1rem;
    border-radius: 10px;
    margin-bottom: 0.75rem;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border: 1px solid rgba(0,0,0,0.05);
    transition: all 0.2s ease;
}

.demographic-item:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.funnel-step {
    position: relative;
    padding: 1.25rem;
    margin-bottom: 1rem;
    border-radius: 12px;
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    border-left: 4px solid;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    transition: all 0.2s ease;
}

.funnel-step:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.funnel-step.started { border-left-color: #3b82f6; }
.funnel-step.demographics { border-left-color: #10b981; }
.funnel-step.completed { border-left-color: #059669; }

.device-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border: 1px solid rgba(0,0,0,0.05);
}

.table-hover tbody tr:hover {
    background-color: rgba(0,0,0,0.02);
}

.badge {
    font-weight: 500;
    padding: 0.4rem 0.65rem;
    border-radius: 8px;
}

.text-purple { 
    color: #8b5cf6 !important; 
}

.bg-purple { 
    background-color: #8b5cf6 !important; 
}

.progress-bar.bg-purple { 
    background-color: #8b5cf6 !important; 
}

.badge.bg-purple { 
    background-color: #8b5cf6 !important; 
    color: white !important;
}

/* Card body improvements */
.card-body {
    padding: 1.25rem;
}

/* Better spacing for metrics */
.row.text-center .col-4 {
    padding: 0.75rem;
}

/* Improved button styling */
.btn {
    border-radius: 8px;
    font-weight: 500;
    padding: 0.5rem 1rem;
}

/* Better form controls */
.form-select {
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    padding: 0.5rem 0.75rem;
}

.form-select:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
}

/* Breadcrumb improvements */
.breadcrumb {
    background: none;
    padding: 0;
    margin-bottom: 1rem;
}

.breadcrumb-item + .breadcrumb-item::before {
    color: #6b7280;
}

/* Header improvements */
.h3 {
    font-weight: 700;
    color: #1f2937;
}

.text-muted {
    color: #6b7280 !important;
}

/* Progress Bars */
.progress {
    border-radius: 10px;
    background-color: rgba(0, 0, 0, 0.1);
}

.progress-bar {
    border-radius: 10px;
}

/* Tables */
.table th {
    border-top: none;
    font-weight: 600;
    color: #374151;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.table td {
    vertical-align: middle;
    border-color: #f3f4f6;
}

.table-hover tbody tr:hover {
    background-color: rgba(59, 130, 246, 0.05);
}

/* Form Controls */
.form-select {
    border-radius: 10px;
    border: 2px solid #e5e7eb;
    padding: 0.6rem 1rem;
    font-weight: 500;
    transition: all 0.2s ease;
}

.form-select:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
}

/* Buttons */
.btn {
    border-radius: 10px;
    font-weight: 500;
    padding: 0.6rem 1.2rem;
    transition: all 0.2s ease;
}

.btn:hover {
    transform: translateY(-1px);
}

/* Responsive Grid Improvements */
@media (max-width: 768px) {
    .card-body {
        padding: 1rem;
    }
    
    .metric-value {
        font-size: 1.5rem;
    }
    
    .d-flex.gap-2 {
        flex-direction: column;
        gap: 0.5rem !important;
    }
    
    .col-lg-3 {
        margin-bottom: 1rem;
    }
}

/* Beautiful Animations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.card {
    animation: fadeInUp 0.5s ease-out;
}
</style>
@endpush

@section('content')
<div class="container-fluid">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('quiz.dashboard') }}">Quiz System</a></li>
            <li class="breadcrumb-item active" aria-current="page">Detailed Analytics</li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Detailed Quiz Analytics</h1>
            <p class="text-muted mb-0">Comprehensive insights and performance metrics</p>
        </div>
        <div class="d-flex gap-2">
            <select class="form-select" id="quizType" onchange="updateAnalytics()">
                @foreach($data['quiz_types'] as $type)
                    <option value="{{ $type }}" {{ $quizType == $type ? 'selected' : '' }}>
                        {{ ucfirst($type) }} Quiz
                    </option>
                @endforeach
            </select>
            <select class="form-select" id="dateRange" onchange="updateAnalytics()">
                <option value="7" {{ $dateRange == '7' ? 'selected' : '' }}>Last 7 days</option>
                <option value="30" {{ $dateRange == '30' ? 'selected' : '' }}>Last 30 days</option>
                <option value="90" {{ $dateRange == '90' ? 'selected' : '' }}>Last 90 days</option>
                <option value="365" {{ $dateRange == '365' ? 'selected' : '' }}>Last year</option>
                <option value="all" {{ $dateRange == 'all' ? 'selected' : '' }}>All time</option>
            </select>
            <a href="{{ route('quiz.dashboard') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Performance Overview -->
    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="card-body text-white p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0 fw-bold">{{ number_format($data['performance_metrics']['total_sessions']) }}</h4>
                            <small class="opacity-90">Total Sessions</small>
                        </div>
                        <div class="opacity-75">
                            <i class="fas fa-users fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
            <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                <div class="card-body text-white p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0 fw-bold">{{ $data['performance_metrics']['completion_rate'] }}%</h4>
                            <small class="opacity-90">Completion Rate</small>
                        </div>
                        <div class="opacity-75">
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
            <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                <div class="card-body text-white p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0 fw-bold">{{ $data['performance_metrics']['average_score'] }}</h4>
                            <small class="opacity-90">Average Score</small>
                        </div>
                        <div class="opacity-75">
                            <i class="fas fa-chart-line fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
            <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                <div class="card-body text-white p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0 fw-bold">{{ $data['performance_metrics']['high_risk_rate'] }}%</h4>
                            <small class="opacity-90">High Risk Rate</small>
                        </div>
                        <div class="opacity-75">
                            <i class="fas fa-exclamation-triangle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Conversion Funnel -->
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-header text-white border-0" style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);">
                    <h5 class="mb-0"><i class="fas fa-funnel-dollar me-2"></i>Conversion Funnel Analysis</h5>
                </div>
                <div class="card-body p-4">
                    <div class="funnel-step started mb-3 p-3 rounded" style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border-left: 4px solid #3b82f6;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-1 fw-bold text-primary">{{ number_format($data['conversion_analysis']['started']) }}</h4>
                                <p class="mb-0 text-muted">Users Started Quiz</p>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-primary fs-6">100%</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="funnel-step demographics mb-3 p-3 rounded" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-left: 4px solid #10b981;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-1 fw-bold text-success">{{ number_format($data['conversion_analysis']['demographics_completed']) }}</h4>
                                <p class="mb-0 text-muted">Completed Demographics</p>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-success fs-6">{{ $data['conversion_analysis']['demographics_rate'] }}%</span>
                                <div class="progress mt-2" style="width: 100px; height: 8px;">
                                    <div class="progress-bar bg-success" style="width: {{ $data['conversion_analysis']['demographics_rate'] }}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="funnel-step completed mb-3 p-3 rounded" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-left: 4px solid #059669;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-1 fw-bold text-success">{{ number_format($data['conversion_analysis']['quiz_completed']) }}</h4>
                                <p class="mb-0 text-muted">Completed Full Quiz</p>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-success fs-6">{{ $data['conversion_analysis']['completion_rate'] }}%</span>
                                <div class="progress mt-2" style="width: 100px; height: 8px;">
                                    <div class="progress-bar bg-success" style="width: {{ $data['conversion_analysis']['completion_rate'] }}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3 p-3 bg-light rounded">
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="fw-bold text-primary">{{ $data['conversion_analysis']['email_provided'] }}</div>
                                <small class="text-muted">Emails Provided ({{ $data['conversion_analysis']['email_rate'] }}%)</small>
                            </div>
                            <div class="col-4">
                                <div class="fw-bold text-success">{{ $data['user_journey']['avg_completion_time'] }}m</div>
                                <small class="text-muted">Avg Completion Time</small>
                            </div>
                            <div class="col-4">
                                <div class="fw-bold text-info">{{ $data['engagement_metrics']['total_interactions'] }}</div>
                                <small class="text-muted">Total Interactions</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-header text-white border-0" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                    <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Completion Time Distribution</h5>
                </div>
                <div class="card-body p-4">
                    @foreach($data['user_journey']['completion_time_distribution'] as $range => $count)
                        <div class="d-flex justify-content-between align-items-center mb-3 p-2 rounded" style="background-color: #f8fafc;">
                            <span class="fw-medium text-dark">{{ $range }}</span>
                            <div class="d-flex align-items-center">
                                <div class="progress me-2" style="width: 80px; height: 10px;">
                                    @php
                                        $total = array_sum($data['user_journey']['completion_time_distribution']);
                                        $percentage = $total > 0 ? ($count / $total) * 100 : 0;
                                    @endphp
                                    <div class="progress-bar" style="width: {{ $percentage }}%; background-color: #8b5cf6;"></div>
                                </div>
                                <span class="badge" style="background-color: #8b5cf6; color: white;">{{ $count }}</span>
                            </div>
                        </div>
                    @endforeach
                    
                    <div class="mt-4 pt-3 border-top text-center">
                        <div class="p-3 rounded" style="background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);">
                            <div class="fw-bold fs-4" style="color: #8b5cf6;">{{ $data['user_journey']['median_completion_time'] }}m</div>
                            <small class="text-muted">Median Completion Time</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Demographics & Risk Analysis -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-header text-white border-0" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                    <h5 class="mb-0"><i class="fas fa-users me-2"></i>Demographic Insights</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="fw-bold mb-3 text-primary">Age Distribution</h6>
                            @foreach($data['demographic_insights']['age_groups'] as $age => $count)
                                <div class="mb-3 p-2 rounded" style="background-color: #f8fafc;">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-medium text-dark">{{ $age }}</span>
                                        <div class="d-flex align-items-center">
                                            <span class="badge bg-success me-2">{{ $count }}</span>
                                            @if(isset($data['demographic_insights']['avg_score_by_age'][$age]))
                                                <small class="text-muted">Avg: {{ $data['demographic_insights']['avg_score_by_age'][$age]['average'] }}</small>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        
                        <div class="col-md-6">
                            <h6 class="fw-bold mb-3 text-info">Gender Distribution</h6>
                            @foreach($data['demographic_insights']['gender_distribution'] as $gender => $count)
                                <div class="mb-3 p-2 rounded" style="background-color: #f0f9ff;">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-medium text-dark">{{ ucfirst($gender) }}</span>
                                        <span class="badge bg-info">{{ $count }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-header text-white border-0" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                    <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Risk Analysis by Demographics</h5>
                </div>
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3 text-danger">Risk by Age Group</h6>
                    @foreach($data['demographic_insights']['risk_by_age'] as $age => $risks)
                        <div class="mb-3 p-3 rounded" style="background-color: #fef2f2;">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-medium text-dark">{{ $age }}</span>
                                <small class="text-muted">{{ array_sum($risks) }} users</small>
                            </div>
                            <div class="d-flex flex-wrap gap-1">
                                @foreach(['low' => 'success', 'increasing' => 'warning', 'higher' => 'danger', 'dependence' => 'dark'] as $risk => $color)
                                    @if(isset($risks[$risk]) && $risks[$risk] > 0)
                                        <span class="badge bg-{{ $color }} fs-6">
                                            {{ ucfirst($risk) }}: {{ $risks[$risk] }}
                                        </span>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- Question Performance & Device Analytics -->
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-header text-white border-0" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                    <h5 class="mb-0"><i class="fas fa-question-circle me-2"></i>Question Performance Analysis</h5>
                </div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Question</th>
                                    <th>Responses</th>
                                    <th>Avg Score</th>
                                    <th>Difficulty</th>
                                    <th>Performance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($data['performance_metrics']['question_metrics'] as $questionId => $metrics)
                                    <tr>
                                        <td>
                                            <span class="fw-medium">Question {{ $questionId }}</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary">{{ $metrics['count'] }}</span>
                                        </td>
                                        <td>
                                            <span class="fw-bold">{{ $metrics['average'] }}</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-{{ $metrics['difficulty'] == 'High' ? 'danger' : ($metrics['difficulty'] == 'Medium' ? 'warning' : 'success') }}">
                                                {{ $metrics['difficulty'] }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-{{ $metrics['average'] > 2 ? 'danger' : ($metrics['average'] > 1 ? 'warning' : 'success') }}" 
                                                     style="width: {{ ($metrics['average'] / 4) * 100 }}%"></div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card analytics-card h-100">
                <div class="card-header text-white border-0" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);">
                    <h5 class="mb-0"><i class="fas fa-mobile-alt me-2"></i>Device & Browser Analytics</h5>
                </div>
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Device Types</h6>
                    @foreach($data['device_analytics']['device_types'] as $device => $count)
                        <div class="d-flex align-items-center mb-3">
                            <div class="device-icon bg-light">
                                <i class="fas fa-{{ $device == 'Mobile' ? 'mobile-alt' : ($device == 'Tablet' ? 'tablet-alt' : 'desktop') }} text-primary"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-medium">{{ $device }}</div>
                                <div class="progress progress-custom">
                                    @php
                                        $total = array_sum($data['device_analytics']['device_types']);
                                        $percentage = $total > 0 ? ($count / $total) * 100 : 0;
                                    @endphp
                                    <div class="progress-bar bg-primary" style="width: {{ $percentage }}%"></div>
                                </div>
                            </div>
                            <span class="badge bg-primary ms-2">{{ $count }}</span>
                        </div>
                    @endforeach
                    
                    <h6 class="fw-bold mb-3 mt-4">Top Browsers</h6>
                    @foreach(array_slice($data['device_analytics']['browsers'], 0, 5, true) as $browser => $count)
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-medium">{{ $browser }}</span>
                            <span class="badge bg-secondary">{{ $count }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- Geographic & Engagement Data -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card analytics-card h-100">
                <div class="card-header text-white" style="background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);">
                    <h5 class="mb-0"><i class="fas fa-globe me-2"></i>Geographic Distribution</h5>
                </div>
                <div class="card-body">
                    @if(count($data['geographic_data']['domain_distribution']) > 0)
                        @foreach($data['geographic_data']['domain_distribution'] as $domain => $count)
                            <div class="d-flex justify-content-between align-items-center mb-3 p-2 bg-light rounded">
                                <div>
                                    <div class="fw-medium">{{ $domain }}</div>
                                    <small class="text-muted">Shopify Store</small>
                                </div>
                                <span class="badge bg-info">{{ $count }} sessions</span>
                            </div>
                        @endforeach
                        
                        <div class="mt-3 pt-3 border-top text-center">
                            <div class="fw-bold text-info">{{ $data['geographic_data']['total_domains'] }}</div>
                            <small class="text-muted">Total Domains</small>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-globe fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No geographic data available</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="card analytics-card h-100">
                <div class="card-header text-white" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                    <h5 class="mb-0"><i class="fas fa-chart-pulse me-2"></i>Engagement Metrics</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center mb-4">
                        <div class="col-4">
                            <div class="fw-bold text-purple h4">{{ $data['engagement_metrics']['avg_time_per_question'] }}s</div>
                            <small class="text-muted">Avg Time per Question</small>
                        </div>
                        <div class="col-4">
                            <div class="fw-bold text-purple h4">{{ number_format($data['engagement_metrics']['total_interactions']) }}</div>
                            <small class="text-muted">Total Interactions</small>
                        </div>
                        <div class="col-4">
                            <div class="fw-bold text-purple h4">{{ count($data['engagement_metrics']['event_counts']) }}</div>
                            <small class="text-muted">Event Types</small>
                        </div>
                    </div>
                    
                    <h6 class="fw-bold mb-3">Event Breakdown</h6>
                    @foreach($data['engagement_metrics']['event_counts'] as $event => $count)
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-medium">{{ str_replace('_', ' ', ucfirst($event)) }}</span>
                            <span class="badge bg-purple">{{ $count }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateAnalytics() {
    const dateRange = document.getElementById('dateRange').value;
    const quizType = document.getElementById('quizType').value;
    const shopifyDomain = new URLSearchParams(window.location.search).get('domain') || '';
    
    const params = new URLSearchParams({
        range: dateRange,
        quiz_type: quizType
    });
    
    if (shopifyDomain) {
        params.append('domain', shopifyDomain);
    }
    
    window.location.href = `{{ route('quiz.analytics') }}?${params.toString()}`;
}

// Add custom CSS for purple color
document.addEventListener('DOMContentLoaded', function() {
    const style = document.createElement('style');
    style.textContent = `
        .text-purple { color: #8b5cf6 !important; }
        .bg-purple { background-color: #8b5cf6 !important; }
        .progress-bar.bg-purple { background-color: #8b5cf6 !important; }
        .badge.bg-purple { background-color: #8b5cf6 !important; }
    `;
    document.head.appendChild(style);
});
</script>
@endsection