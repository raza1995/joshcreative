@extends('layouts.app')

@section('content')
<div class="container mt-5">
    <h2 class="mb-4">📊 Export Facebook Ads to Google Sheets</h2>

    <div id="response-msg" class="alert d-none"></div>

    {{-- Create Google Sheet --}}
    <form id="create-sheet-form" class="mb-4">
        @csrf
        <div class="mb-3">
            <label for="sheet_name">Sheet Title</label>
            <input type="text" name="sheet_name" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="json_file">Select JSON File</label>
            <select name="json_file" class="form-control" required>
                @foreach($jsonFiles as $file)
                    <option value="{{ $file }}">{{ $file }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-success">📁 Create Google Sheet</button>
    </form>

    <hr>

    {{-- Append Data to Sheet --}}
    <form id="append-sheet-form">
        @csrf
        <div class="mb-3">
            <label for="spreadsheet_id">Existing Spreadsheet ID</label>
            <input type="text" name="spreadsheet_id" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="json_file">Select JSON File to Append</label>
            <select name="json_file" class="form-control" required>
                @foreach($jsonFiles as $file)
                    <option value="{{ $file }}">{{ $file }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-warning">📌 Append Data</button>
    </form>
</div>
@endsection

@push('scripts')
<script>
    function handleResponse(status, message, isLink = false) {
        const alertBox = document.getElementById('response-msg');
        alertBox.classList.remove('d-none', 'alert-success', 'alert-danger');
        alertBox.classList.add(status === 'success' ? 'alert-success' : 'alert-danger');
        alertBox.innerHTML = isLink ? `<a href="${message}" target="_blank">${message}</a>` : message;
    }

    document.getElementById('create-sheet-form').addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch("{{ route('sheet.create') }}", {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('input[name=_token]').value
            },
            body: formData
        })
        .then(res => res.json())
        .then(data => handleResponse('success', data.sheet_url, true))
        .catch(err => handleResponse('error', '❌ Failed to create sheet.'));
    });

    document.getElementById('append-sheet-form').addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch("{{ route('sheet.append') }}", {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('input[name=_token]').value
            },
            body: formData
        })
        .then(res => res.json())
        .then(data => handleResponse('success', data.message))
        .catch(err => handleResponse('error', '❌ Failed to append data.'));
    });
</script>
@endpush
