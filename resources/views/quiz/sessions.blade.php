@extends('layouts.app')

@section('title', 'Quiz Sessions')

@section('content')
<div class="container-fluid">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('quiz.dashboard') }}">Quiz System</a></li>
            <li class="breadcrumb-item active" aria-current="page">All Sessions</li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Quiz Sessions</h1>
            <p class="text-muted mb-0">Browse and filter all quiz sessions</p>
        </div>
        <a href="{{ route('quiz.dashboard') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Risk Level</label>
                    <select name="risk_level" class="form-select">
                        <option value="">All Levels</option>
                        <option value="low" {{ request('risk_level') === 'low' ? 'selected' : '' }}>Low Risk</option>
                        <option value="increasing" {{ request('risk_level') === 'increasing' ? 'selected' : '' }}>Increasing Risk</option>
                        <option value="higher" {{ request('risk_level') === 'higher' ? 'selected' : '' }}>Higher Risk</option>
                        <option value="dependence" {{ request('risk_level') === 'dependence' ? 'selected' : '' }}>Possible Dependence</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="completed" class="form-select">
                        <option value="">All Sessions</option>
                        <option value="true" {{ request('completed') === 'true' ? 'selected' : '' }}>Completed</option>
                        <option value="false" {{ request('completed') === 'false' ? 'selected' : '' }}>Incomplete</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Domain</label>
                    <select name="domain" class="form-select">
                        <option value="">All Domains</option>
                        @foreach($domains as $domain)
                            <option value="{{ $domain }}" {{ request('domain') === $domain ? 'selected' : '' }}>
                                {{ $domain }}
                            </option>
                        @endforeach
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">From Date</label>
                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">To Date</label>
                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                </div>
                
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Filter</button>
                    <a href="{{ route('quiz.sessions') }}" class="btn btn-outline-secondary">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Sessions Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Sessions ({{ $sessions->total() }} total)</h5>
            <div>
                <a href="{{ route('quiz.export') }}{{ request()->getQueryString() ? '?' . request()->getQueryString() : '' }}" 
                   class="btn btn-sm btn-success">
                    <i class="fas fa-download"></i> Export Filtered
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Session</th>
                            <th>Email</th>
                            <th>Started</th>
                            <th>Completed</th>
                            <th>Score</th>
                            <th>Risk Level</th>
                            <th>Demographics</th>
                            <th>Domain</th>
                            <th>Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sessions as $session)
                            <tr>
                                <td>
                                    <code class="small">{{ substr($session->session_uuid, 0, 8) }}...</code>
                                </td>
                                <td>
                                    @if($session->user_email)
                                        <small class="text-primary">{{ $session->user_email }}</small>
                                    @else
                                        <span class="text-muted">Anonymous</span>
                                    @endif
                                </td>
                                <td>
                                    <small>{{ $session->started_at?->format('M j, Y H:i') }}</small>
                                </td>
                                <td>
                                    @if($session->completed)
                                        <span class="badge bg-success">
                                            <i class="fas fa-check"></i> {{ $session->completed_at?->format('M j, H:i') }}
                                        </span>
                                    @else
                                        <span class="badge bg-warning">
                                            <i class="fas fa-clock"></i> In Progress
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if($session->total_score !== null)
                                        <strong>{{ $session->total_score }}/40</strong>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($session->risk_level)
                                        @php
                                            $badgeClass = match($session->risk_level) {
                                                'low' => 'success',
                                                'increasing' => 'warning',
                                                'higher' => 'danger',
                                                'dependence' => 'dark',
                                                default => 'secondary'
                                            };
                                        @endphp
                                        <span class="badge bg-{{ $badgeClass }}">
                                            {{ ucfirst(str_replace('_', ' ', $session->risk_level)) }}
                                        </span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($session->demographics)
                                        <small>
                                            {{ $session->demographics['age'] ?? 'Unknown' }}, 
                                            {{ ucfirst($session->demographics['sex'] ?? 'Unknown') }}
                                        </small>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($session->shopify_domain)
                                        <small class="text-primary">{{ $session->shopify_domain }}</small>
                                    @else
                                        <span class="text-muted">Direct</span>
                                    @endif
                                </td>
                                <td>
                                    @if($session->time_taken_seconds)
                                        <small>{{ gmdate('i:s', $session->time_taken_seconds) }}</small>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('quiz.session-detail', $session->session_uuid) }}" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-4 text-muted">
                                    No sessions found matching your criteria.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        
        @if($sessions->hasPages())
            <div class="card-footer">
                {{ $sessions->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
