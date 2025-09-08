@extends('layouts.app')

@section('content')
<div class="container-fluid">
  <div class="row mb-3">
    <div class="col">
      <div class="card">
        <div class="card-body">
          <form method="get" action="{{ route('analytics.kpi') }}" class="row g-3 align-items-end">
            <div class="col-md-2">
              <label class="form-label">From (order_date)</label>
              <input type="date" name="from" class="form-control" value="{{ $from }}" required>
            </div>
            <div class="col-md-2">
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
              <label class="form-label">Channel</label>
              <input type="text" name="channel" class="form-control" value="{{ $channel }}" placeholder="e.g. Google Ads">
            </div>
            <div class="col-md-2">
              <label class="form-label">UTM Source</label>
              <input type="text" name="utm_source" class="form-control" value="{{ $source }}" placeholder="e.g. google">
            </div>
            <div class="col-md-2">
              <label class="form-label">UTM Medium</label>
              <input type="text" name="utm_medium" class="form-control" value="{{ $medium }}" placeholder="e.g. cpc">
            </div>
            <div class="col-md-2">
              <label class="form-label">UTM Campaign</label>
              <input type="text" name="utm_campaign" class="form-control" value="{{ $campaign }}" placeholder="campaign name/id">
            </div>
            <div class="col-md-2">
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

  <div class="row mb-3">
    <div class="col-md-6">
      <div class="card text-bg-info"><div class="card-body"><div>New Customers</div><div class="display-6">{{ number_format($summary['new_customers']) }}</div></div></div>
    </div>
    <div class="col-md-6">
      <div class="card text-bg-dark text-white"><div class="card-body"><div>Returning Customers</div><div class="display-6">{{ number_format($summary['returning_customers']) }}</div></div></div>
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

  <div class="row mt-4">
    <div class="col">
      <div class="card">
        <div class="card-body table-responsive">
          <h5 class="card-title">UTM Sources</h5>
          <table class="table table-striped table-bordered align-middle">
            <thead>
              <tr>
                <th>Source</th>
                <th class="text-end">Orders</th>
                <th class="text-end">Customers</th>
                <th class="text-end">Revenue ($)</th>
                <th class="text-end">AOV ($)</th>
              </tr>
            </thead>
            <tbody>
              @foreach($sources as $s)
              <tr>
                <td>{{ $s->utm_source }}</td>
                <td class="text-end">{{ number_format($s->orders) }}</td>
                <td class="text-end">{{ number_format($s->customers) }}</td>
                <td class="text-end">{{ number_format($s->revenue, 2) }}</td>
                <td class="text-end">{{ number_format($s->aov, 2) }}</td>
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
          <h5 class="card-title">UTM Mediums</h5>
          <table class="table table-striped table-bordered align-middle">
            <thead>
              <tr>
                <th>Medium</th>
                <th class="text-end">Orders</th>
                <th class="text-end">Customers</th>
                <th class="text-end">Revenue ($)</th>
                <th class="text-end">AOV ($)</th>
              </tr>
            </thead>
            <tbody>
              @foreach($mediums as $m)
              <tr>
                <td>{{ $m->utm_medium }}</td>
                <td class="text-end">{{ number_format($m->orders) }}</td>
                <td class="text-end">{{ number_format($m->customers) }}</td>
                <td class="text-end">{{ number_format($m->revenue, 2) }}</td>
                <td class="text-end">{{ number_format($m->aov, 2) }}</td>
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
          <h5 class="card-title">UTM Campaigns</h5>
          <table class="table table-striped table-bordered align-middle">
            <thead>
              <tr>
                <th>Campaign</th>
                <th class="text-end">Orders</th>
                <th class="text-end">Customers</th>
                <th class="text-end">Revenue ($)</th>
                <th class="text-end">AOV ($)</th>
              </tr>
            </thead>
            <tbody>
              @foreach($campaigns as $g)
              <tr>
                <td>{{ $g->utm_campaign }}</td>
                <td class="text-end">{{ number_format($g->orders) }}</td>
                <td class="text-end">{{ number_format($g->customers) }}</td>
                <td class="text-end">{{ number_format($g->revenue, 2) }}</td>
                <td class="text-end">{{ number_format($g->aov, 2) }}</td>
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
