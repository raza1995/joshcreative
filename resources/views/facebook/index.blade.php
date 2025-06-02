@extends('layouts.app')

@section('content')
<div class="container">
    <h2>Facebook Ads Insights</h2>

    <div class="mb-3">
        <label for="intervalFilter">Filter by Interval:</label>
        <select id="intervalFilter" class="form-select" style="width: 200px;">
            <option value="daily" selected>Daily</option>
            <option value="weekly">Weekly</option>
            <option value="monthly">Monthly</option>
        </select>
    </div>

    <table class="table table-bordered table-striped" id="facebookAdsTable" style="width: 100%;">
        <thead>
            <tr>
                <th>Ad ID</th>
                <th>Account</th>
                <th>Campaign</th>
                <th>AD Link</th>
                <th>Orders</th>
                <th>Adset</th>
                <th>Ad</th>
                <th>Type</th>
                <th>Spend</th>
                <th>Clicks</th>
                <th>CTR</th>
                <th>CPA</th>
                <th>ROAS</th>
                <th>Status</th>
                <th>Updated</th>
                <th>Interval</th>
                <th>Link</th>
                <th>Thumbnail Url</th>
            </tr>
        </thead>
    </table>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function () {
    function loadTable(interval = 'daily') {
        $('#facebookAdsTable').DataTable({
            processing: true,
            serverSide: true,
            destroy: true,
            ajax: {
                url: '{{ route("facebook.ads.data") }}',
                data: { interval: interval }
            },
            columns: [
                { data: 'ad_id', name: 'ad_id' },
                { data: 'ad_account_name', name: 'ad_account_name' },
                { data: 'campaign_name', name: 'campaign_name' },
                { data: 'ad_link', name: 'ad_link' },
                { data: 'order_count', name: 'order_count' },
                { data: 'adset_name', name: 'adset_name' },
                { data: 'ad_name', name: 'ad_name' },
                { data: 'ad_type', name: 'ad_type' },
                { data: 'spend', name: 'spend',  },
                { data: 'clicks', name: 'clicks' },
                { data: 'ctr', name: 'ctr' },
                { data: 'cpa', name: 'cpa' },
                { data: 'roas', name: 'roas' },
                { data: 'status', name: 'status' },
                { data: 'updated_time', name: 'updated_time' },
                { data: 'interval', name: 'interval' },
                { data: 'link_url', name: 'link_url',},
                { data: 'thumbnail_url', name: 'thumbnail_url', render: function(data, type, row) {
                    return `<img src="${data}" alt="Thumbnail" style="width: 50px; height: auto;">`;
                }},

            ],
            order: [[6, 'desc']],
        });
    }

    $('#intervalFilter').on('change', function () {
        let interval = $(this).val();
        loadTable(interval);
    });

    loadTable();
});
</script>
@endpush
