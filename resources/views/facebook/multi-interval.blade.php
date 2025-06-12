@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="mb-4">Multi-Interval Facebook Ads Comparison</h2>

    <div class="row mb-3">
        <div class="mb-2">
            <div class="col-md-3">
                <label>Performance Flag:</label>
                <select id="performance-flag" class="form-control">
                    <option value="">All</option>
                    <option value="excellent">Excellent (ROAS ≥ 3)</option>
                    <option value="moderate">Moderate (ROAS 1.5–2.99)</option>
                    <option value="risky">Risky (Spend ≥ $1000 & ROAS < 2)</option>
                    <option value="poor">Poor (ROAS < 1.5 & Spend < $1000)</option>
                </select>
            </div>
            
            <strong>Legend:</strong>
            <span class="badge bg-success">ROAS ≥ 3 (Excellent)</span>
            <span class="badge bg-warning text-dark">1.5 ≤ ROAS &lt; 3 (Moderate)</span>
            <span class="badge bg-danger">ROAS &lt; 1.5 (Low ROAS, Low Spend)</span>
            <span class="badge bg-dark text-light">ROAS &lt; 2.0 & Spend ≥ $1,000 (High Risk 🔥)</span>
        </div>
        
        <div class="col-md-3">
            
            <label>Campaign:</label>
            <select id="campaign-filter" class="form-control">
                <option value="">All</option>
                @foreach($campaigns as $campaign)
                    <option value="{{ $campaign }}">{{ $campaign }}</option>
                @endforeach
            </select>
        </div>
 
        <div class="col-md-3">
            <label>Intervals:</label>
            <select id="intervals" class="form-control form-select" >
                <option value="daily">Daily</option>
                <option value="weekly" selected>Weekly</option>
                <option value="monthly" selected>Monthly</option>

                
                <option value="custom_60">Last 60 Days</option>
                <option value="custom_90">Last 90 Days</option>
                <option value="custom_120">Last 120 Days</option>
                <option value="custom_365">Last 12 Months</option>
                <option disabled>──────────</option>
                <option value="month_01">January</option>
                <option value="month_02">February</option>
                <option value="month_03">March</option>
                <option value="month_04">April</option>
                <option value="month_05">May</option>
                <option value="month_06">June</option>
                <option value="month_07">July</option>
                <option value="month_08">August</option>
                <option value="month_09">September</option>
                <option value="month_10">October</option>
                <option value="month_11">November</option>
                <option value="month_12">December</option>
            </select>
        </div>
        <div class="col-md-3">
            <label>Start Date:</label>
            <input type="date" id="start-date" class="form-control" />
        </div>
        <div class="col-md-3">
            <label>End Date:</label>
            <input type="date" id="end-date" class="form-control" />
        </div>
    </div>
    <div class="alert alert-info" id="filter-note" style="display:none;">
        Showing results for <strong id="filter-description"></strong>
    </div>
    <table id="ads-table" class="table table-bordered">
        <thead>
            <tr>
                <th>Ad ID</th>
                <th>Campaign</th>
                <th>Ad Name</th>
                <th>Ad Set Name</th>
                <th>Interval</th>
                <th>Spend</th>
                <th>ROAS</th>
                <th>Orders</th>
                <th>CPA</th>
                <th>ROAS Trend</th>
                <th>Performance Flag</th>
                <th>Trend</th>
                <th>Chart</th>
                <th>Ad Link</th>
                <th>Thumbnail</th>
                <th>Updated</th>
            </tr>
        </thead>
        <tfoot>
            <tr>
                <th colspan="4">Total</th>
                <th id="total-spend"></th>
                <th id="avg-roas"></th>
                <th id="total-orders"></th>
                <th colspan="3"></th>
            </tr>
            </tfoot>
    </table>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    const table = $('#ads-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("facebook.multi_interval.data") }}',
            data: function (d) {
                d.campaign = $('#campaign-filter').val();
                d.intervals = $('#intervals').val();
                d.start_date = $('#start-date').val();
                d.end_date = $('#end-date').val();
                d.performance_flag = $('#performance-flag').val(); 

            }
        },
        columns: [
    { data: 'ad_id', name: 'ad_id' },
    { data: 'campaign_name', name: 'campaign_name' },
    { data: 'ad_name', name: 'ad_name' },
    { data: 'adset_name', name: 'adset_name' },
    { data: 'interval', name: 'interval' },
    { data: 'spend', name: 'spend' },
    { data: 'roas', name: 'roas' },
    { data: 'order_count', name: 'order_count' },
    { data: 'cpa', name: 'cpa' },
    { data: 'roas_trend', name: 'roas_trend' },
    { data: 'performance_flag'  }, 
    { data: 'trend_metrics', orderable: false, searchable: false },
    { data: 'charts', orderable: false, searchable: false },
    { data: 'ad_link', orderable: false, searchable: false },
    { data: 'thumbnail_url', orderable: false, searchable: false },
    { data: 'updated_time', name: 'updated_time' }
],
rowCallback: function (row, data) {
    const roas = parseFloat(data.roas);
    const spend = parseFloat(data.spend);

    // Clear any previous styling
    $(row).removeClass('table-success table-warning table-danger table-dark');

    if (!isNaN(roas) && !isNaN(spend)) {
        if (roas >= 3) {
            $(row).addClass('table-success'); // Excellent
        } else if (roas >= 1.5) {
            $(row).addClass('table-warning'); // Moderate
        } else if (spend >= 1000 && roas < 2) {
            $(row).addClass('table-dark'); // High spend, low return
        } else {
            $(row).addClass('table-danger'); // Poor performance
        }
    }
}


    });


    $('#intervals, #campaign-filter, #start-date, #end-date, #performance-flag').on('change', function () {
    table.draw();
});

    table.on('xhr', function () {
    const data = table.ajax.json();
    $('#total-spend').text(data.total_spend);
    $('#avg-roas').text(data.avg_roas);
    $('#total-orders').text(data.total_orders);
});

function updateFilterNote() {
    const start = $('#start-date').val();
    const end = $('#end-date').val();
    const interval = $('#intervals').val();

    if (start && end) {
        $('#filter-note').show();
        $('#filter-description').text(`Custom Date Range: ${start} → ${end}`);
    } else if (interval) {
        $('#filter-note').show();
        $('#filter-description').text(`Interval: ${interval}`);
    } else {
        $('#filter-note').hide();
    }
}

$('#intervals, #campaign-filter, #start-date, #end-date, #performance-flag').on('change', function () {
    updateFilterNote();
    table.draw();
});

updateFilterNote(); // Initial call

});
</script>
@endpush
