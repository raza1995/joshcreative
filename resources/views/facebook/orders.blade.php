@extends('layouts.app')

@section('content')
<div class="container">
    <h4>Orders for Ad: {{ $ad->ad_name }} ({{ $ad->ad_id }})</h4>
    <hr>

    <table class="table table-bordered" id="orders-table">
        <thead>
            <tr>
                <th>Order #</th>
                <th>Customer</th>
                <th>Email</th>
                <th>Amount Paid</th>
                <th>Discount</th>
                <th>Coupon</th>
                <th>Date</th>
                <th>Tracking #</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($orders as $order)
                <tr>
                    <td>#{{ $order->order_number }}</td>
                    <td>{{ $order->customer_name }}</td>
                    <td>{{ $order->email_address }}</td>
                    <td>${{ number_format($order->paid_amount, 2) }}</td>
                    <td>${{ number_format($order->discount, 2) }}</td>
                    <td>{{ $order->coupon ?? '-' }}</td>
                    <td>{{ \Carbon\Carbon::parse($order->order_date)->format('Y-m-d') }}</td>
                    <td>{{ $order->tracking_number ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="8">No orders found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function () {
        $('#orders-table').DataTable({
            dom: 'Bfrtip',
            buttons: [
                'copyHtml5',
                'excelHtml5',
                'csvHtml5',
                'pdfHtml5',
                'print'
            ],
            order: [[0, 'desc']] // Default sort by Order #
        });
    });
</script>
@endpush
