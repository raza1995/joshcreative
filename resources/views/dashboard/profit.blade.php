@extends('layouts.app')

@section('content')
<div class="container py-4">
    <h2 class="mb-4">📊 Gross Profit Summary</h2>

    <form method="GET" action="{{ route('dashboard.profit') }}" class="row g-3 mb-4 align-items-end">
    <div class="col-md-3">
        <label for="start_date" class="form-label">Start Date:</label>
        <input type="date" name="start_date" id="start_date"
               value="{{ request('start_date', $startDate) }}" class="form-control">
    </div>

    <div class="col-md-3">
        <label for="end_date" class="form-label">End Date:</label>
        <input type="date" name="end_date" id="end_date"
               value="{{ request('end_date', $endDate) }}" class="form-control">
    </div>

    <div id="ad-spend-wrapper" class="row g-3 mb-4">
    <label class="form-label">Ad Spend Entries:</label>
    
    @php $existingRows = old('ad_spends', []); @endphp
    @foreach ($existingRows as $index => $row)
    <div class="row g-2 align-items-end ad-spend-row">
        <div class="col-md-4">
            <input type="text" name="ad_spends[{{ $index }}][label]" class="form-control" placeholder="Ad Account Name" value="{{ $row['label'] ?? '' }}">
        </div>
        <div class="col-md-3">
            <input type="number" step="0.01" name="ad_spends[{{ $index }}][amount]" class="form-control" placeholder="Amount" value="{{ $row['amount'] ?? '' }}">
        </div>
        <div class="col-md-3">
            <select name="ad_spends[{{ $index }}][company]" class="form-select">
                <option value="Josh" {{ ($row['company'] ?? '') === 'Josh' ? 'selected' : '' }}>Josh</option>
                <option value="Chandler" {{ ($row['company'] ?? '') === 'Chandler' ? 'selected' : '' }}>Chandler</option>
            </select>
        </div>
        <div class="col-md-2">
            <button type="button" class="btn btn-danger remove-row">Remove</button>
        </div>
    </div>
    @endforeach
</div>

<button type="button" class="btn btn-secondary mb-3" id="add-ad-spend">➕ Add Ad Spend</button>


    <div class="col-md-2">
        <button type="submit" class="btn btn-primary w-100">🔄 Calculate</button>
    </div>
</form>


<table class="table table-bordered table-hover">
    <thead class="table-light">
        <tr>
            <th>Marketing Company</th>
            <th>Net Sales ($)</th>
            <th>Ad Spend ($)</th>
            <th>COGS ($)</th>
            <th><strong>Gross Profit ($)</strong></th>
        </tr>
    </thead>
    <tbody>
        @foreach ($summary as $company => $data)
            <tr>
                <td>{{ $company }}</td>
                <td>${{ number_format($data['net_sales'], 2) }}</td>
                <td>${{ number_format($data['ad_spend'], 2) }}</td>
                <td>${{ number_format($data['cogs'], 2) }}</td>
                <td><strong class="text-success">${{ number_format($data['gross_profit'], 2) }}</strong></td>
            </tr>
        @endforeach
    </tbody>
</table>

</div>
<script>
let adIndex = {{ count(old('ad_spends', [])) ?: 0 }};

$('#add-ad-spend').on('click', function () {
    const row = `
    <div class="row g-2 align-items-end ad-spend-row">
        <div class="col-md-4">
            <input type="text" name="ad_spends[${adIndex}][label]" class="form-control" placeholder="Ad Account Name">
        </div>
        <div class="col-md-3">
            <input type="number" step="0.01" name="ad_spends[${adIndex}][amount]" class="form-control" placeholder="Amount">
        </div>
        <div class="col-md-3">
            <select name="ad_spends[${adIndex}][company]" class="form-select">
                <option value="Josh">Josh</option>
                <option value="Chandler">Chandler</option>
            </select>
        </div>
        <div class="col-md-2">
            <button type="button" class="btn btn-danger remove-row">Remove</button>
        </div>
    </div>
    `;
    $('#ad-spend-wrapper').append(row);
    adIndex++;
});

$(document).on('click', '.remove-row', function () {
    $(this).closest('.ad-spend-row').remove();
});
</script>

@endsection
