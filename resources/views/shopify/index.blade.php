@extends('layouts.app')

@section('content')
<div class="card">
  <div class="card-body">
  <div class="row g-2 mb-3">
      <div class="col-auto">
        <label class="form-label">SKU</label>
        <input id="product" class="form-control" value="WATERMELON WAVE 3">
      </div>
      <div class="col-auto align-self-end">
        <button id="apply" class="btn btn-primary">Apply</button>
      </div>

      <div class="col-auto align-self-end">
    <a id="exportCsv" href="#" class="btn btn-success">Export CSV</a>
  </div>
    </div>
  {!! $dataTable->table(['class' => 'table table-bordered']) !!}
  </div>
</div>
@endsection



@push('scripts')

{{ $dataTable->scripts(attributes: ['type' => 'module']) }}
<script type="module">
  document.getElementById('apply').addEventListener('click', function () {
    // Reload Yajra instance and send the SKU via ajax->data (wired in html())
    $('#shopify-orders-table').DataTable().ajax.reload();
    
  });

  document.getElementById('exportCsv').addEventListener('click', function (e) {
    e.preventDefault();
    const sku = document.getElementById('product')?.value || '';
    window.location.href = `{{ route('shopify.exportCsv') }}?product=${encodeURIComponent(sku)}`;
  });
</script>
@endpush
