@extends('layouts.app')

@section('content')
<div class="container">

    <h2 class="mb-4">Referring-Domain Conversion Rates</h2>

    {{-- ───── Date-range picker ───── --}}
    <form class="row g-3 mb-4" method="GET" action="{{ route('conversions.landing_sites') }}">
        <div class="col-auto">
            <label for="start_date" class="col-form-label">Start date:</label>
        </div>
        <div class="col-auto">
            <input type="date"
                   id="start_date"
                   name="start_date"
                   value="{{ request('start_date', now()->subDays(7)->toDateString()) }}"
                   class="form-control">
        </div>

        <div class="col-auto">
            <label for="end_date" class="col-form-label">End date:</label>
        </div>
        <div class="col-auto">
            <input type="date"
                   id="end_date"
                   name="end_date"
                   value="{{ request('end_date', now()->toDateString()) }}"
                   class="form-control">
        </div>

        <div class="col-auto">
            <button type="submit" class="btn btn-primary">Update</button>
        </div>
    </form>

    {{-- ───── Results table ───── --}}
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Referring Domain</th>
                <th class="text-end">Visits</th>
                <th class="text-end">Conversions</th>
                <th class="text-end">Conversion Rate (%)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($conversions as $row)
                <tr>
                    <td>{{ $row['domain'] }}</td>
                    <td class="text-end">{{ $row['visits'] }}</td>
                    <td class="text-end">{{ $row['conversions'] }}</td>
                    <td class="text-end">{{ $row['conversion_rate'] }}%</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center">No data for this range</td>
                </tr>
            @endforelse
        </tbody>
    </table>

</div>
@endsection
