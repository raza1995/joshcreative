@extends('layouts.app')

@section('content')
<div class="container">
    <form method="GET" class="row mb-4">

        <input type="hidden" name="ad_id" value="{{ $ad_id }}"/>
    
        <div class="col-md-3">
            <label>Date Range:</label>
            <select name="range" class="form-control">
                <option value="">Custom</option>
                <option value="last_7" {{ $range == 'last_7' ? 'selected' : '' }}>Last 7 Days</option>
                <option value="last_30" {{ $range == 'last_30' ? 'selected' : '' }}>Last 30 Days</option>
                <option value="last_90" {{ $range == 'last_90' ? 'selected' : '' }}>Last 90 Days</option>
            </select>
        </div>
    
        <div class="col-md-3">
            <label>Start Date:</label>
            <input type="date" name="start_date" class="form-control" value="{{ $start_date }}">
        </div>
    
        <div class="col-md-3">
            <label>End Date:</label>
            <input type="date" name="end_date" class="form-control" value="{{ $end_date }}">
        </div>
    
        <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">Filter</button>
        </div>
    </form>
</div>


<div class="container">
    <h3 class="mb-4">📊 Ad Performance Charts (Ad ID: {{ $ad_id }})</h3>

    <canvas id="roasChart" height="100"></canvas>
    <canvas id="spendOrdersChart" height="100" class="mt-5"></canvas>
    <canvas id="cpaChart" height="100" class="mt-5"></canvas>
    <canvas id="ctrClicksChart" height="100" class="mt-5"></canvas>
    <canvas id="impressionsChart" height="100" class="mt-5"></canvas>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const stats = @json($stats);

const labels = stats.map(d => d.date);

function lineChart(id, label, data, color = 'rgba(75, 192, 192, 0.8)') {
    new Chart(document.getElementById(id), {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label,
                data: stats.map(d => d[data]),
                backgroundColor: color,
                borderColor: color,
                fill: false,
                tension: 0.4
            }]
        }
    });
}

function barComboChart(id, label1, field1, label2, field2) {
    new Chart(document.getElementById(id), {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label: label1,
                    data: stats.map(d => d[field1]),
                    backgroundColor: 'rgba(54, 162, 235, 0.6)'
                },
                {
                    label: label2,
                    data: stats.map(d => d[field2]),
                    type: 'line',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    fill: false,
                    tension: 0.4
                }
            ]
        }
    });
}

// Render Charts
lineChart('roasChart', 'ROAS Over Time', 'roas');
barComboChart('spendOrdersChart', 'Spend', 'spend', 'Orders', 'orders');
lineChart('cpaChart', 'CPA Over Time', 'cpa', 'rgba(255, 159, 64, 0.8)');
barComboChart('ctrClicksChart', 'CTR', 'ctr', 'Clicks', 'clicks');
lineChart('impressionsChart', 'Impressions', 'impressions', 'rgba(153, 102, 255, 0.8)');
</script>
@endpush
