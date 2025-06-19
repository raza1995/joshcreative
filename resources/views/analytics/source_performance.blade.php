@extends('layouts.app')

@section('content')
<div class="container">
  <h2>Traffic-Source Performance</h2>

  @include('analytics.partials.date_form')

  <div class="mb-3 row">
    <div class="col-md-4">
      <input type="text" id="domainFilter" class="form-control" placeholder="Search Domain">
    </div>
  </div>

  <table class="table table-bordered" id="trafficTable">
    <thead>
      <tr>
        <th>Referring Domain</th>
        <th class="text-end">Product Views</th>
        <th class="text-end">Purchases</th>
        <th class="text-end">CVR (%)</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($stats as $row)
        <tr>
          <td>{{ $row['domain'] }}</td>
          <td class="text-end">{{ $row['visits'] }}</td>
          <td class="text-end">{{ $row['conversions'] }}</td>
          <td class="text-end">{{ $row['conversion_rate'] }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const table = document.getElementById('trafficTable');
  const rows = Array.from(table.querySelectorAll('tbody tr'));
  const domainFilter = document.getElementById('domainFilter');

  function filterRows() {
    const domainVal = domainFilter.value.toLowerCase();

    rows.forEach(row => {
      const domain = row.children[0].innerText.toLowerCase();
      const match = domain.includes(domainVal);
      row.style.display = match ? '' : 'none';
    });
  }

  domainFilter.addEventListener('input', filterRows);

  // Auto-sort by purchases (conversions) descending on load
  rows.sort((a, b) => {
    return parseInt(b.children[2].innerText) - parseInt(a.children[2].innerText);
  }).forEach(row => table.querySelector('tbody').appendChild(row));
});
</script>
@endpush
