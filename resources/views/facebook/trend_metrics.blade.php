@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="mb-4">📊 Facebook Ads KPI Trend Comparison</h2>

    {{-- Interval Comparison Form --}}
    <form method="GET" class="row mb-4">
        <div class="col-md-2">
            <label>Primary Interval:</label>
            <select name="primary" class="form-control">
                <option value="daily" {{ $primary == 'daily' ? 'selected' : '' }}>Daily</option>
                <option value="weekly" {{ $primary == 'weekly' ? 'selected' : '' }}>Weekly</option>
                <option value="monthly" {{ $primary == 'monthly' ? 'selected' : '' }}>Monthly</option>
            </select>
        </div>
    
        <div class="col-md-2">
            <label>Comparison Interval:</label>
            <select name="comparison" class="form-control">
                <option value="daily" {{ $comparison == 'daily' ? 'selected' : '' }}>Daily</option>
                <option value="weekly" {{ $comparison == 'weekly' ? 'selected' : '' }}>Weekly</option>
                <option value="monthly" {{ $comparison == 'monthly' ? 'selected' : '' }}>Monthly</option>
            </select>
        </div>
    
        <div class="col-md-2">
            <label>Campaign:</label>
            <select name="campaign" class="form-control">
                <option value="">All Campaigns</option>
                @foreach($campaigns as $item)
                    <option value="{{ $item }}" {{ $campaign == $item ? 'selected' : '' }}>{{ $item }}</option>
                @endforeach
            </select>
        </div>
    
        <div class="col-md-2">
            <label>Ad ID:</label>
            <input type="text" name="ad_id" class="form-control" value="{{ $adId }}">
        </div>
    
        <div class="col-md-2">
            <label>Ad Name:</label>
            <input type="text" name="ad_name" class="form-control" value="{{ $adName }}">
        </div>
    
        <div class="col-md-2">
            <label>Date Range:</label>
            <div class="d-flex">
                <input type="date" name="start_date" class="form-control me-1" value="{{ $startDate }}">
                <input type="date" name="end_date" class="form-control" value="{{ $endDate }}">
            </div>
        </div>
    
        <div class="col-md-12 mt-3">
            <button class="btn btn-primary">Compare</button>
        </div>
    </form>
    

    {{-- Comparison Summary --}}
    <div class="alert alert-info">
        <strong>Currently Comparing:</strong> <span class="text-primary">{{ ucfirst($primary) }}</span> vs <span class="text-danger">{{ ucfirst($comparison) }}</span>
    </div>

    {{-- Ads Loop --}}
    @forelse($ads as $ad)
        <div class="card mb-4 shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="card-title mb-1">{{ $ad['ad_name'] }}</h5>
                        <h6 class="text-muted">{{ $ad['campaign_name'] }}</h6>
                    </div>
                    @if($ad['thumbnail_url'])
                        <img src="{{ $ad['thumbnail_url'] }}" width="60" class="rounded">
                    @endif
                </div>

                <div class="mb-3">
                    <a href="{{ $ad['ad_link'] }}" target="_blank" class="btn btn-outline-primary btn-sm">
                        🔗 View Ad
                    </a>
                </div>

                <table class="table table-sm table-striped table-bordered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>📊 Metric</th>
                            <th>📈 Trend</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>ROAS</td><td>{!! $ad['roas_trend'] !!}</td></tr>
                        <tr><td>CPA</td><td>{!! $ad['cpa_trend'] !!}</td></tr>
                        <tr><td>Spend</td><td>{!! $ad['spend_trend'] !!}</td></tr>
                        <tr><td>CTR</td><td>{!! $ad['ctr_trend'] !!}</td></tr>
                        <tr><td>Impressions</td><td>{!! $ad['impressions_trend'] !!}</td></tr>
                        <tr><td>Clicks</td><td>{!! $ad['clicks_trend'] !!}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="alert alert-warning">No ads found for the selected intervals.</div>
    @endforelse
</div>
@endsection
