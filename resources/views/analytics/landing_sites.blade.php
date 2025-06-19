@extends('layouts.app')

@section('content')
<div class="container">

    <h2 class="mb-4">Referring-Domain Conversion Rates</h2>

    {{-- ───── Date-range picker ───── --}}
    @include('analytics.partials.date_form')

    {{-- ───── Search Filter ───── --}}
    <div class="mb-3 row">
        <div class="col-md-4">
            <input type="text" id="domainSearch" class="form-control" placeholder="Search by domain...">
        </div>
    </div>

    {{-- ───── Results table ───── --}}
    <table class="table table-bordered" id="conversionTable">
        <thead>
            <tr>
                <th>Referring Domain</th>
                <th class="text-end">Product Viewed</th>
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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const table = document.getElementById('conversionTable');
    const rows = Array.from(table.querySelectorAll('tbody tr'));
    const searchInput = document.getElementById('domainSearch');

    // Sort by conversions descending
    rows.sort((a, b) => {
        const aConversions = parseInt(a.children[2]?.innerText || 0);
        const bConversions = parseInt(b.children[2]?.innerText || 0);
        return bConversions - aConversions;
    }).forEach(row => table.querySelector('tbody').appendChild(row));

    // Live search filter
    searchInput.addEventListener('input', function () {
        const keyword = this.value.toLowerCase();
        rows.forEach(row => {
            const domain = row.children[0]?.innerText.toLowerCase();
            const show = domain.includes(keyword);
            row.style.display = show ? '' : 'none';
        });
    });
});
</script>
@endpush
