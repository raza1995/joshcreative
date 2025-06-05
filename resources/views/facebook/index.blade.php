@extends('layouts.app')

@section('content')
<div class="container">
    <h2>Facebook Ads Insights</h2>

    <div class="mb-4 d-flex align-items-end gap-3 flex-wrap">
        <div>
            <label for="intervalFilter">Filter by Interval:</label>
            <select id="intervalFilter" class="form-select" style="width: 200px;">
                <option value="daily" selected>Daily</option>
                <option value="weekly">Weekly</option>
                <option value="monthly">Monthly</option>
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

        <button class="btn btn-primary" id="applyFilter">Apply Filter</button>
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
                <th>Impressions</th>
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
    let dataTable;

    function loadTable() {
        const interval = $('#intervalFilter').val();

        if (dataTable) {
            dataTable.destroy();
        }

        dataTable = $('#facebookAdsTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ route("facebook.ads.data") }}',
                data: function (d) {
                    d.interval = interval;
                    d.start_date = '';
                    d.end_date = '';
                }
            },
            columns: [
    { data: 'ad_id', name: 'facebook_ads.ad_id' },
    { data: 'ad_account_name', name: 'facebook_ads.ad_account_name' },
    { data: 'campaign_name', name: 'facebook_ads.campaign_name' },
    { data: 'ad_link', name: 'facebook_ads.ad_link', orderable: true, searchable: true },
    { data: 'order_count', orderable: true }, // from `shopify_orders_count`
    { data: 'adset_name', name: 'facebook_ads.adset_name' },
    { data: 'ad_name', name: 'facebook_ads.ad_name' },
    { data: 'ad_type', name: 'facebook_ads.ad_type' },
    { data: 'impressions', orderable: true, searchable: false },
    { data: 'spend', orderable: true, searchable: false },
    { data: 'clicks', orderable: true, searchable: false },
    { data: 'ctr', orderable: true, searchable: false },
    { data: 'cpa', orderable: true, searchable: false },
    { data: 'roas', orderable: true, searchable: false },
    { data: 'status', name: 'facebook_ads.status' },
    { data: 'updated_time', name: 'facebook_ads.updated_time' },
    { data: 'interval', orderable: true, searchable:    false },
    { data: 'link_url', orderable: true, searchable: false },
    {
        data: 'thumbnail_url',
        orderable: false,
        searchable: false,
        render: function (data) {
            return `<img src="${data}" style="width: 50px;">`;
        }
    },
],
    order: [[9, 'desc']], // shopify_orders_count

        });
    }

    function applyFilter() {
        const interval = $('#intervalFilter').val();
        localStorage.setItem('fb_ads_filter', interval);
        loadTable();
    }

    $('#intervalFilter').on('change', function () {
        applyFilter();
    });

    $('#applyFilter').on('click', function () {
        applyFilter();
    });

    // Load saved filter from localStorage
    const savedInterval = localStorage.getItem('fb_ads_filter');
    if (savedInterval) {
        $('#intervalFilter').val(savedInterval);
    }

    loadTable();
});
</script>
@endpush
