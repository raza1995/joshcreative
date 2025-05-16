@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="row mb-4">
        <div class="col">
            <h2 class="fw-semibold">📊 Shopify Monthly Sales Report</h2>
            <p class="text-muted">Use the date range to filter product sales, gross revenue, and net revenue.</p>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form id="filter-form" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="start_date" class="form-label">Start Date:</label>
                    <input type="date" name="start_date" id="start_date" class="form-control">
                </div>
                <div class="col-md-4">
                    <label for="end_date" class="form-label">End Date:</label>
                    <input type="date" name="end_date" id="end_date" class="form-control">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100">🔍 Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle" id="sales-table">
                    <thead class="table-light">
                        <tr>
                            <th>Product Title</th>
                            <th>Total Items Sold</th>
                            <th>Gross Sales ($)</th>
                            <th>Net Sales ($)</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(function () {
    const table = $('#sales-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("dashboard.filter") }}',
            data: function (d) {
                d.start_date = $('#start_date').val();
                d.end_date = $('#end_date').val();
            }
        },
        columns: [
    { data: 'product_title', name: 'product_title' },
    { data: 'total_items', name: 'total_items' },
    {
        data: 'gross_sales',
        name: 'gross_sales',
        render: function (data) {
            return '$' + parseFloat(data).toFixed(2);
        }
    },
    {
        data: 'net_sales',
        name: 'net_sales',
        render: function (data) {
            return '$' + parseFloat(data).toFixed(2);
        }
    }
],

        order: [[1, 'desc']],
        responsive: true,
        pageLength: 10,
        language: {
            processing: "Loading data...",
            emptyTable: "No data available for this range.",
        }
    });

    $('#filter-form').on('submit', function (e) {
        e.preventDefault();
        table.ajax.reload();
    });
});
</script>
@endpush

@endsection
