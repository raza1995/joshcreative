@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <div class="row mb-3">
    <div class="col">
      <div class="card">
        <div class="card-body">
          <form method="get" action="{{ route('analytics.aov') }}" class="row g-3 align-items-end">
            <div class="col-md-4">
              <label class="form-label">UTM Medium (Campaign ID)</label>
              <input type="text" name="utm_medium" class="form-control" value="{{ $medium }}" placeholder="e.g. 120227205541980749" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">From (order_date)</label>
              <input type="date" name="from" class="form-control" value="{{ $from }}" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">To (order_date)</label>
              <input type="date" name="to" class="form-control" value="{{ $to }}" required>
            </div>
            <div class="col-md-2">
              <div class="form-check mt-4">
                <input class="form-check-input" type="checkbox" name="ui" id="ui" value="1" {{ $ui ? 'checked' : '' }}>
                <label class="form-check-label" for="ui">Shopify UI Filters</label>
              </div>
            </div>
            <div class="col-md-2">
              <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  @if($summary)
  <div class="row mb-3">
    <div class="col-md-4">
      <div class="card text-bg-success">
        <div class="card-body">
          <h6 class="card-title mb-1">Customers with $70+ AOV</h6>
          <div class="display-6">{{ number_format($summary['customers_70_plus']) }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card text-bg-secondary">
        <div class="card-body">
          <h6 class="card-title mb-1">Customers with $69 or less AOV</h6>
          <div class="display-6">{{ number_format($summary['customers_69_or_less']) }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card text-bg-light">
        <div class="card-body">
          <h6 class="card-title mb-1">Total Customers</h6>
          <div class="display-6">{{ number_format($summary['total_customers']) }}</div>
        </div>
      </div>
    </div>
  </div>

  <div class="row mb-3">
    <div class="col-md-6">
      <div class="card">
        <div class="card-body">
          <h6 class="card-title mb-1">Total Orders (Campaign)</h6>
          <div class="display-6">{{ number_format($summary['campaign_orders']) }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card">
        <div class="card-body">
          <h6 class="card-title mb-1">Total Orders (All)</h6>
          <div class="display-6">{{ number_format($summary['total_orders']) }}</div>
        </div>
      </div>
    </div>
  </div>

  <div class="row mb-3">
    <div class="col-md-6">
      <div class="card text-bg-info">
        <div class="card-body">
          <h6 class="card-title mb-1">New Customers</h6>
          <div class="display-6">{{ number_format($summary['new_customers']) }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card text-bg-dark text-white">
        <div class="card-body">
          <h6 class="card-title mb-1">Returning Customers</h6>
          <div class="display-6">{{ number_format($summary['returning_customers']) }}</div>
        </div>
      </div>
    </div>
  </div>

  <div class="row mb-3">
    <div class="col-md-6">
      <div class="card">
        <div class="card-body">
          <h6 class="card-title mb-1">Discount Orders</h6>
          <div class="display-6">{{ number_format($summary['discount_orders']) }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card">
        <div class="card-body">
          <h6 class="card-title mb-1">Discount Rate</h6>
          <div class="display-6">{{ number_format($summary['discount_rate'], 2) }}%</div>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col">
      <div class="card">
        <div class="card-body table-responsive">
          <table class="table table-striped table-bordered align-middle">
            <thead>
              <tr>
                <th>Email</th>
                <th class="text-end">Orders</th>
                <th class="text-end">Total Spend ($)</th>
                <th class="text-end">AOV ($)</th>
              </tr>
            </thead>
            <tbody>
              @foreach($rows as $r)
              <tr>
                <td>{{ $r->email_address }}</td>
                <td class="text-end">{{ (int) $r->orders_count }}</td>
                <td class="text-end">{{ number_format((float) $r->total_spend, 2) }}</td>
                <td class="text-end">{{ number_format((float) $r->aov, 2) }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="row mt-4">
    <div class="col">
      <div class="card">
        <div class="card-body table-responsive">
          <h5 class="card-title">Top SKUs by Campaign</h5>
          <table class="table table-striped table-bordered align-middle">
            <thead>
              <tr>
                <th>SKU</th>
                <th class="text-end">Orders</th>
                <th class="text-end">Units</th>
                <th class="text-end">Revenue ($)</th>
              </tr>
            </thead>
            <tbody>
              @foreach($topSkus as $s)
              <tr>
                <td>{{ $s['sku'] }}</td>
                <td class="text-end">{{ (int) $s['orders'] }}</td>
                <td class="text-end">{{ (int) $s['units'] }}</td>
                <td class="text-end">{{ number_format((float) $s['revenue'], 2) }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  @endif
</div>
@endsection
