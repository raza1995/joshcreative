@extends('layouts.app')

@section('content')
<div class="container">
    <div class="mb-4">
        <h2>📊 KPI Trends for: <strong>{{ $ad['ad_name'] ?? 'N/A' }}</strong></h2>
        <h5 class="text-muted">{{ $ad['campaign_name'] ?? '' }}</h5>
    </div>

    {{-- Filter Form --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Primary Interval</label>
                    <select name="primary" class="form-select">
                        @foreach(['daily', 'weekly', 'monthly'] as $option)
                            <option value="{{ $option }}" {{ $primary === $option ? 'selected' : '' }}>{{ ucfirst($option) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Comparison Interval</label>
                    <select name="comparison" class="form-select">
                        @foreach(['daily', 'weekly', 'monthly'] as $option)
                            <option value="{{ $option }}" {{ $comparison === $option ? 'selected' : '' }}>{{ ucfirst($option) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="{{ $startDate }}">
                </div>

                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="{{ $endDate }}">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Compare Date 1</label>
                    <input type="date" name="compare_date_1" class="form-control" value="{{ $compareDate1 }}">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Compare Date 2</label>
                    <input type="date" name="compare_date_2" class="form-control" value="{{ $compareDate2 }}">
                </div>

                <div class="col-md-12 mt-2">
                    <button type="submit" class="btn btn-primary me-2">🔍 Compare Trends</button>
                    <a href="{{ url()->previous() }}" class="btn btn-outline-secondary">← Back</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Ad Preview --}}
    @if(!empty($ad['thumbnail_url']) || !empty($ad['ad_link']))
    <div class="card mb-4">
        <div class="card-body d-flex align-items-center gap-4">
            @if(!empty($ad['thumbnail_url']))
                <img src="{{ $ad['thumbnail_url'] }}" width="100" class="img-thumbnail" alt="Ad Thumbnail">
            @endif
            @if(!empty($ad['ad_link']))
                <a href="{{ $ad['ad_link'] }}" target="_blank" class="btn btn-outline-primary btn-sm">🔗 View Ad on Facebook</a>
            @endif
        </div>
    </div>
    @endif

    {{-- Interval-Based Summary --}}
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">🗓 Interval-Based Comparison Summary</h5>
            <p class="mb-1"><strong>{{ ucfirst($comparison) }} Interval Dates:</strong> {{ $ad['comparison_dates'] ?? '-' }}</p>
            <p class="mb-1"><strong>{{ ucfirst($primary) }} Interval Dates:</strong> {{ $ad['primary_dates'] ?? '-' }}</p>
            <p class="text-muted mb-0 small">
                Compares KPI performance from <strong>{{ $comparison }}</strong> to <strong>{{ $primary }}</strong>.
                <span class="text-success">↑ improvement</span>, <span class="text-danger">↓ decline</span>.
            </p>
        </div>
    </div>

    {{-- Direct Day Comparison --}}
    @if(!empty($ad['compare_date_1']) && !empty($ad['compare_date_2']) && !empty($ad['custom_trends']))
    <div class="card border-info mb-4">
        <div class="card-body">
            <h5 class="card-title text-info">📅 Direct Day Comparison</h5>
            <p><strong>Compared Period:</strong> {{ $ad['compare_date_1'] }} <span class="text-muted">(Baseline)</span></p>
            <p><strong>Current Period:</strong> {{ $ad['compare_date_2'] }} <span class="text-muted">(Recent)</span></p>
            <p class="text-muted"><em>Trends show change from Compared Period → Current Period</em></p>

            {{-- Custom Trend Table --}}
            <table class="table table-bordered table-sm mt-3">
                <thead><tr><th>📊 Metric</th><th>Trend</th></tr></thead>
                <tbody>
                    @php
                        function rowClass($trendHtml, $positiveGood = true) {
                            if (str_contains($trendHtml, '↑') && $positiveGood) return 'table-success';
                            if (str_contains($trendHtml, '↓') && !$positiveGood) return 'table-success';
                            if (str_contains($trendHtml, '↑') || str_contains($trendHtml, '↓')) return 'table-danger';
                            return '';
                        }
                        $strip = fn($html) => strip_tags($html ?? '-');
                    @endphp

                    @foreach(['ROAS', 'CPA', 'Spend', 'CTR', 'Impressions', 'Clicks'] as $metric)
                        @php
                            $isPositive = !in_array($metric, ['CPA']);
                            $trend = $ad['custom_trends'][$metric] ?? '-';
                        @endphp
                        <tr class="{{ rowClass($trend, $isPositive) }}">
                            <td>{{ $metric }}</td>
                            <td>{!! $trend !!} {{ str_contains($trend, '↑') ? '📈' : (str_contains($trend, '↓') ? '📉' : '') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- Summary Sentence --}}
            <div class="text-muted small mt-3">
                Over this period, ROAS changed by <strong>{{ $strip($ad['custom_trends']['ROAS']) }}</strong>,
                CPA by <strong>{{ $strip($ad['custom_trends']['CPA']) }}</strong>,
                Spend by <strong>{{ $strip($ad['custom_trends']['Spend']) }}</strong>,
                and CTR by <strong>{{ $strip($ad['custom_trends']['CTR']) }}</strong>.
                Impressions and Clicks moved <strong>{{ $strip($ad['custom_trends']['Impressions']) }}</strong> and
                <strong>{{ $strip($ad['custom_trends']['Clicks']) }}</strong> respectively.
            </div>
        </div>
    </div>
    @endif

    {{-- KPI Summary Table --}}
    <div class="card mb-4">
        <div class="card-header">📈 KPI Trend Table ({{ ucfirst($comparison) }} → {{ ucfirst($primary) }})</div>
        <div class="card-body p-0">
            <table class="table table-bordered table-sm mb-0">
                <thead class="table-light">
                    <tr><th>Metric</th><th>Trend</th></tr>
                </thead>
                <tbody>
                    <tr><td>ROAS</td><td>{!! $ad['roas_trend'] ?? '-' !!}</td></tr>
                    <tr><td>CPA</td><td>{!! $ad['cpa_trend'] ?? '-' !!}</td></tr>
                    <tr><td>Total Spend</td><td>{!! $ad['spend_trend'] ?? '-' !!}</td></tr>
                    <tr><td>CTR</td><td>{!! $ad['ctr_trend'] ?? '-' !!}</td></tr>
                    <tr><td>Impressions</td><td>{!! $ad['impressions_trend'] ?? '-' !!}</td></tr>
                    <tr><td>Clicks</td><td>{!! $ad['clicks_trend'] ?? '-' !!}</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- GPT AI Insight --}}
    @if(!empty($ad['ai_insights']))
    <div class="card border-warning mb-4">
        <div class="card-body">
            <h5 class="card-title text-warning">🧠 GPT-Powered Insights & Recommendations</h5>
            <div class="card-body bg-light text-dark">
                <div class="fs-3 lh-lg" style="white-space: pre-line;">
                    {!! nl2br(e($ad['ai_insights'])) !!}
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Charts --}}
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title mb-3">📊 30-Day Trend Chart</h5>
            <canvas id="trendChart" height="120"></canvas>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">📉 Performance Chart ({{ ucfirst($primary) }})</div>
        <div class="card-body">
            <canvas id="performanceChart"></canvas>
            <small class="text-muted d-block mt-2">ROAS, CPA, and CTR over the selected {{ $primary }} interval.</small>
        </div>
    </div>
</div>

{{-- Chart.js Scripts --}}
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const labels = {!! json_encode($chartLabels) !!};
    const datasets = [
        { label: 'ROAS', data: {!! json_encode($chartRoas) !!}, borderColor: 'green', fill: false, tension: 0.4 },
        { label: 'CPA', data: {!! json_encode($chartCpa) !!}, borderColor: 'red', fill: false, tension: 0.4 },
        { label: 'CTR', data: {!! json_encode($chartCtr) !!}, borderColor: 'blue', fill: false, tension: 0.4 }
    ];

    new Chart(document.getElementById('trendChart').getContext('2d'), {
        type: 'line',
        data: { labels, datasets },
        options: {
            responsive: true,
            plugins: {
                title: { display: true, text: 'Performance Trends (Last 30 Days)' },
                tooltip: { mode: 'index', intersect: false }
            },
            interaction: { mode: 'nearest', axis: 'x', intersect: false },
            scales: { y: { beginAtZero: true } }
        }
    });

    new Chart(document.getElementById('performanceChart').getContext('2d'), {
        type: 'line',
        data: { labels, datasets },
        options: {
            responsive: true,
            plugins: {
                title: { display: true, text: 'Performance Trend Overview' },
                legend: { position: 'bottom' }
            },
            scales: { y: { beginAtZero: true } }
        }
    });
</script>
@endpush
@endsection
