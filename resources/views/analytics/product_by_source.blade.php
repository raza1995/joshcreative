@extends('layouts.app')

@section('content')
<div class="container">
  <h2>Product Performance by Source</h2>

  @include('analytics.partials.date_form')

  <div class="mb-3 row">
    <div class="col-md-4">
      <input type="text" id="sourceFilter" class="form-control" placeholder="Search Source">
    </div>
    <div class="col-md-4">
      <input type="text" id="productFilter" class="form-control" placeholder="Search Product">
    </div>
  </div>

  <table class="table table-bordered" id="productTable">
    <thead>
      <tr>
        <th>Source</th>
        <th>Product</th>
        <th class="text-end">Views</th>
        <th class="text-end">Orders</th>
        <th class="text-end">CVR (%)</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($stats as $row)
        <tr>
          <td>{{ $row['source'] }}</td>
          <td>{{ $row['product'] }}</td>
          <td class="text-end">{{ $row['views'] }}</td>
          <td class="text-end">{{ $row['orders'] }}</td>
          <td class="text-end">{{ $row['cvr'] }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const table = document.getElementById('productTable');
  const rows = Array.from(table.querySelectorAll('tbody tr'));

  const sourceFilter = document.getElementById('sourceFilter');
  const productFilter = document.getElementById('productFilter');

  function filterRows() {
    const sourceVal = sourceFilter.value.toLowerCase();
    const productVal = productFilter.value.toLowerCase();

    rows.forEach(row => {
      const source = row.children[0].innerText.toLowerCase();
      const product = row.children[1].innerText.toLowerCase();

      const match = source.includes(sourceVal) && product.includes(productVal);
      row.style.display = match ? '' : 'none';
    });
  }

  sourceFilter.addEventListener('input', filterRows);
  productFilter.addEventListener('input', filterRows);

  // Optional: Sort by views descending
  rows.sort((a, b) => {
    return parseInt(b.children[2].innerText) - parseInt(a.children[2].innerText);
  }).forEach(row => table.querySelector('tbody').appendChild(row));
});
</script>
@endpush
