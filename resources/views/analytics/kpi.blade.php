@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <div class="row mb-3">
    <div class="col">
      <div class="card">
        <div class="card-body">
          <form method="get" action="{{ route('analytics.kpi') }}" class="row g-3 align-items-end">
            <div class="col-md-3">
              <label class="form-label">From (order_date)</label>
              <input type="date" name="from" class="form-control" value="{{ $from }}" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">To (order_date)</label>
              <input type="date" name="to" class="form-control" value="{{ $to }}" required>
            </div>
            <div class="col-md-3">
              <div class="form-check mt-4">
                <input class="form-check-input" type="checkbox" name="ui" id="ui" value="1" {{ $ui ? 'checked' : '' }}>
                <label class="form-check-label" for="ui">Shopify UI Filters</label>
              </div>
            </div>
            <div class="col-md-3">
              <button type="submit" class="btn btn-primary w-100">Apply</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="row mb-3">
    <div class="col-md-3">
      <div class="card text-bg-light"><div class="card-body"><div>Orders</div><div class="display-6">{{ number_format($summary['orders']) }}</div></div></div>
    </div>
    <div class="col-md-3">
      <div class="card text-bg-light"><div class="card-body"><div>Customers</div><div class="display-6">{{ number_format($summary['customers']) }}</div></div></div>
    </div>
    <div class="col-md-3">
      <div class="card text-bg-light"><div class="card-body"><div>Revenue</div><div class="display-6">${{ number_format($summary['revenue'], 2) }}</div></div></div>
    </div>
    <div class="col-md-3">
      <div class="card text-bg-light"><div class="card-body"><div>AOV</div><div class="display-6">${{ number_format($summary['aov'], 2) }}</div></div></div>
    </div>
  </div>

  <div class="row">
    <div class="col">
      <div class="card">
        <div class="card-body table-responsive">
          <h5 class="card-title">Channels</h5>
          <table class="table table-striped table-bordered align-middle">
            <thead>
              <tr>
                <th>Channel</th>
                <th class="text-end">Orders</th>
                <th class="text-end">Customers</th>
                <th class="text-end">Revenue ($)</th>
                <th class="text-end">AOV ($)</th>
              </tr>
            </thead>
            <tbody>
              @foreach($channels as $c)
              <tr>
                <td>{{ $c->channel }}</td>
                <td class="text-end">{{ number_format($c->orders) }}</td>
                <td class="text-end">{{ number_format($c->customers) }}</td>
                <td class="text-end">{{ number_format($c->revenue, 2) }}</td>
                <td class="text-end">{{ number_format($c->aov, 2) }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

