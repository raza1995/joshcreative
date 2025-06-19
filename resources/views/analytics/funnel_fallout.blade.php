@extends('layouts.app')

@section('content')
<div class="container">
  <h2 class="mb-4">Funnel Fallout: Product → Purchase</h2>

  {{-- Date Filter --}}
  @include('analytics.partials.date_form')

  {{-- Funnel Chart --}}
  <canvas id="funnelBar" height="140"></canvas>

  {{-- Funnel Table --}}
  <table class="table table-bordered w-auto mt-4">
    <thead>
      <tr>
        <th>Step</th>
        <th class="text-end">Users</th>
        <th class="text-end">Drop-off %</th>
        <th class="text-end">Pass-through %</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>Product viewed</td>
        <td class="text-end">{{ number_format($funnel['views']) }}</td>
        <td class="text-end">—</td>
        <td class="text-end">100%</td>
      </tr>
      <tr>
        <td>Add To Cart</td>
        <td class="text-end">{{ number_format($funnel['add_to_cart']) }}</td>
        <td class="text-end">{{ number_format(100 - $funnel['pct_atc'], 2) }}%</td>
        <td class="text-end">{{ number_format($funnel['pct_atc'], 2) }}%</td>
      </tr>
      <tr>
        <td>Checkout Started</td>
        <td class="text-end">{{ number_format($funnel['checkout_started']) }}</td>
        <td class="text-end">{{ number_format(100 - $funnel['pct_cko'], 2) }}%</td>
        <td class="text-end">{{ number_format($funnel['pct_cko'], 2) }}%</td>
      </tr>
      <tr>
        <td>Purchase</td>
        <td class="text-end">{{ number_format($funnel['purchases']) }}</td>
        <td class="text-end">{{ number_format(100 - $funnel['pct_buy'], 2) }}%</td>
        <td class="text-end">{{ number_format($funnel['pct_buy'], 2) }}%</td>
      </tr>
    </tbody>
  </table>

  {{-- Funnel Notes --}}
  <p class="mt-3 small text-muted">
    <strong>How to read:</strong> “Pass-through %” is calculated as current-step users ÷ previous-step users.
    “Drop-off %” is the remainder. Events are deduplicated per device per step.
  </p>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
(() => {
    const ctx  = document.getElementById('funnelBar').getContext('2d');
    const data = {
        labels: @json($chart['labels']),
        datasets: [{
            label: 'Unique Users',
            data: @json($chart['values']),
            borderWidth: 1,
            backgroundColor: '#4e73df'
        }]
    };
    new Chart(ctx, {
        type: 'bar',
        data: data,
        options: {
            indexAxis: 'y',
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });
})();
</script>
@endpush
