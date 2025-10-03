@extends('layouts.app')

@section('title', 'Session Detail')

@section('content')
<div class="container-fluid">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('quiz.dashboard') }}">Quiz System</a></li>
            <li class="breadcrumb-item"><a href="{{ route('quiz.sessions') }}">All Sessions</a></li>
            <li class="breadcrumb-item active" aria-current="page">Session {{ substr($session->session_uuid, 0, 8) }}...</li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Session Detail</h1>
            <p class="text-muted mb-0">Detailed view of quiz session {{ substr($session->session_uuid, 0, 8) }}...</p>
        </div>
        <a href="{{ route('quiz.sessions') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Sessions
        </a>
    </div>

    <div class="row">
        <!-- Session Overview -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Session Overview</h5>
                </div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-5">Session UUID:</dt>
                        <dd class="col-sm-7"><code class="small">{{ $session->session_uuid }}</code></dd>
                        
                        <dt class="col-sm-5">Quiz Type:</dt>
                        <dd class="col-sm-7">{{ strtoupper($session->quiz_type) }}</dd>
                        
                        <dt class="col-sm-5">Started:</dt>
                        <dd class="col-sm-7">{{ $session->started_at?->format('M j, Y H:i:s') }}</dd>
                        
                        <dt class="col-sm-5">Completed:</dt>
                        <dd class="col-sm-7">
                            @if($session->completed)
                                <span class="badge bg-success">{{ $session->completed_at?->format('M j, Y H:i:s') }}</span>
                            @else
                                <span class="badge bg-warning">In Progress</span>
                            @endif
                        </dd>
                        
                        <dt class="col-sm-5">Time Taken:</dt>
                        <dd class="col-sm-7">
                            @if($session->time_taken_seconds)
                                {{ gmdate('H:i:s', $session->time_taken_seconds) }}
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </dd>
                        
                        <dt class="col-sm-5">User Email:</dt>
                        <dd class="col-sm-7">{{ $session->user_email ?? 'Anonymous' }}</dd>
                        
                        <dt class="col-sm-5">IP Address:</dt>
                        <dd class="col-sm-7">{{ $session->user_ip ?? 'Unknown' }}</dd>
                        
                        <dt class="col-sm-5">Shopify Domain:</dt>
                        <dd class="col-sm-7">{{ $session->shopify_domain ?? 'Direct access' }}</dd>
                    </dl>
                </div>
            </div>

            <!-- Demographics -->
            @if($session->demographics)
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Demographics</h5>
                </div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-4">Age:</dt>
                        <dd class="col-sm-8">{{ $session->demographics['age'] ?? 'Not provided' }}</dd>
                        
                        <dt class="col-sm-4">Sex:</dt>
                        <dd class="col-sm-8">{{ ucfirst($session->demographics['sex'] ?? 'Not provided') }}</dd>
                    </dl>
                </div>
            </div>
            @endif

            <!-- Results -->
            @if($session->completed)
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Results</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <h2 class="display-4">{{ $session->total_score }}/40</h2>
                        @php
                            $badgeClass = match($session->risk_level) {
                                'low' => 'success',
                                'increasing' => 'warning',
                                'higher' => 'danger',
                                'dependence' => 'dark',
                                default => 'secondary'
                            };
                        @endphp
                        <span class="badge bg-{{ $badgeClass }} fs-6">
                            {{ ucfirst(str_replace('_', ' ', $session->risk_level)) }}
                        </span>
                    </div>
                    
                    @if($session->risk_message)
                        <div class="alert alert-info">
                            {{ $session->risk_message }}
                        </div>
                    @endif
                    
                    <div class="small text-muted">
                        <strong>Scoring bands:</strong><br>
                        0–7: Low risk<br>
                        8–15: Increasing risk<br>
                        16–19: Higher risk<br>
                        20+: Possible dependence
                    </div>
                </div>
            </div>
            @endif
        </div>

        <!-- Question Responses -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Question Responses</h5>
                </div>
                <div class="card-body">
                    @if($session->responses)
                        <div class="accordion" id="questionsAccordion">
                            @foreach($questions as $question)
                                @php
                                    $response = $session->responses[$question->question_number] ?? null;
                                    $selectedOption = null;
                                    if ($response !== null) {
                                        $selectedOption = collect($question->options)->firstWhere('score', $response);
                                    }
                                @endphp
                                
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="heading{{ $question->question_number }}">
                                        <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" 
                                                type="button" 
                                                data-bs-toggle="collapse" 
                                                data-bs-target="#collapse{{ $question->question_number }}"
                                                aria-expanded="{{ $loop->first ? 'true' : 'false' }}" 
                                                aria-controls="collapse{{ $question->question_number }}">
                                            <div class="d-flex justify-content-between w-100 me-3">
                                                <span>Q{{ $question->question_number }}: {{ Str::limit($question->question_text, 60) }}</span>
                                                <div>
                                                    @if($response !== null)
                                                        <span class="badge bg-primary">Score: {{ $response }}</span>
                                                    @else
                                                        <span class="badge bg-secondary">No Response</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </button>
                                    </h2>
                                    <div id="collapse{{ $question->question_number }}" 
                                         class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" 
                                         aria-labelledby="heading{{ $question->question_number }}" 
                                         data-bs-parent="#questionsAccordion">
                                        <div class="accordion-body">
                                            <h6>{{ $question->question_text }}</h6>
                                            
                                            @if($question->help_text)
                                                <div class="alert alert-info small">
                                                    {{ $question->help_text }}
                                                </div>
                                            @endif
                                            
                                            <div class="mt-3">
                                                <strong>Options:</strong>
                                                <ul class="list-unstyled mt-2">
                                                    @foreach($question->options as $option)
                                                        <li class="mb-1">
                                                            <div class="d-flex justify-content-between align-items-center p-2 rounded {{ $selectedOption && $selectedOption['score'] === $option['score'] ? 'bg-primary text-white' : 'bg-light' }}">
                                                                <span>{{ $option['label'] }}</span>
                                                                <span class="badge {{ $selectedOption && $selectedOption['score'] === $option['score'] ? 'bg-light text-dark' : 'bg-secondary' }}">
                                                                    {{ $option['score'] }} points
                                                                </span>
                                                            </div>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                            
                                            @if($selectedOption)
                                                <div class="mt-3 p-3 bg-success bg-opacity-10 border border-success rounded">
                                                    <strong>Selected:</strong> {{ $selectedOption['label'] }} ({{ $selectedOption['score'] }} points)
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4 text-muted">
                            No responses recorded yet.
                        </div>
                    @endif
                </div>
            </div>

            <!-- Analytics Events -->
            @if($session->analytics->count() > 0)
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Analytics Events ({{ $session->analytics->count() }})</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>Event Type</th>
                                    <th>Question</th>
                                    <th>Time on Question</th>
                                    <th>Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($session->analytics->sortBy('event_timestamp') as $event)
                                    <tr>
                                        <td class="small">{{ $event->event_timestamp->format('H:i:s') }}</td>
                                        <td>
                                            <span class="badge bg-info">{{ $event->event_type }}</span>
                                        </td>
                                        <td>{{ $event->question_id ?? '-' }}</td>
                                        <td>
                                            @if($event->time_on_question)
                                                {{ $event->time_on_question }}s
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            @if($event->event_data)
                                                <button class="btn btn-sm btn-outline-secondary" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#eventModal{{ $event->id }}">
                                                    View Data
                                                </button>
                                                
                                                <!-- Modal for event data -->
                                                <div class="modal fade" id="eventModal{{ $event->id }}" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Event Data</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <pre class="small">{{ json_encode($event->event_data, JSON_PRETTY_PRINT) }}</pre>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
