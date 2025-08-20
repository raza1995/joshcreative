@extends('layouts.app')

@section('content')
<div class="card">
  <div class="card-body">
    <div class="row g-2 mb-3">
      <div class="col-auto">
        <label class="form-label">Product</label>
        <input id="product" class="form-control" value="WATERMELON WAVE">
      </div>
      <div class="col-auto align-self-end">
        <button id="apply" class="btn btn-primary">Apply</button>
      </div>
      <div class="col-auto align-self-end">
        <a id="exportCsv" class="btn btn-outline-secondary" href="#">Export CSV</a>
      </div>
    </div>

    <table id="orders" class="table table-striped w-100">
      <thead>
        <tr>
          <th>Order #</th>
          <th>Full Name</th>
          <th>Email</th>
          <th>Product</th>
          <th>Qty</th>
          <th>Paid</th>
          <th>Shipping</th>
          <th>Ship Cost</th>
          <th>Discount Codes</th>    
          <th>Total Discount</th>    
          <th>Tracking</th>
          <th>Order Date</th>
        </tr>
      </thead>
    </table>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const dataUrl  = '{{ route('shopify.data') }}';
  const exportUrlBase = '{{ route('shopify.exportCsv') }}';

  const table = $('#orders').DataTable({
    processing: true,
    serverSide: true,
    ajax: {
      url: dataUrl,
      data: function (d) {
        d.product = document.querySelector('#product').value || 'WATERMELON WAVE';
      },
      // yajra returns { data: [...] } by default
      dataSrc: 'data'
    },
    columns: [
    { data: 'order_number', name: 'order_number' },
    { data: 'full_name', name: 'full_name', orderable: false, searchable: false },
    { data: 'email', name: 'email' },
    { data: 'product_name', name: 'product_name' },
    { data: 'number_of_items', name: 'number_of_items' },
    { data: 'order_total', name: 'order_total', orderable: false, searchable: false },
    { data: 'shipping_method', name: 'shipping_method' },
    { data: 'shipping_cost', name: 'shipping_cost', orderable: false, searchable: false },
    { data: 'discount_codes', name: 'discount_codes', orderable: false, searchable: true }, // NEW
    { data: 'total_discount', name: 'total_discount', orderable: false, searchable: false }, // NEW
    { data: 'tracking', name: 'tracking', orderable: false, searchable: false },
    { data: 'order_date_formatted', name: 'order_date' }
  ],
  order: [[11, 'desc']],
  dom: 'Bfrtip',
  buttons: [
    {
      extend: 'csvHtml5',
      text: 'Export CSV',
      title: 'Orders_' + new Date().toISOString().slice(0, 10),
      exportOptions: {
        columns: ':visible'
      }
    },
    {
      extend: 'print',
      text: 'Print'
    }
    // You can also add 'excelHtml5', 'pdfHtml5' if needed
  ]
  });

  document.querySelector('#apply').addEventListener('click', () => table.ajax.reload());

  function buildExportUrl() {
    const product = encodeURIComponent(document.querySelector('#product').value || 'WATERMELON WAVE');
    return `${exportUrlBase}?product=${product}`;
  }
  const exportBtn = document.querySelector('#exportCsv');
  exportBtn.href = buildExportUrl();
  document.querySelector('#product').addEventListener('input', () => exportBtn.href = buildExportUrl());
});
</script>
@endpush
