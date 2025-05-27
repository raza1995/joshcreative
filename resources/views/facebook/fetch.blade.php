@extends('layouts.app')

@section('content')
<div class="container mt-5">
    <h2 class="mb-4">Facebook Ads Data Sync</h2>

    <div id="status-message" class="alert alert-info d-none">
        ⏳ Sending Facebook Ads fetch job to queue...
    </div>

    <div id="success-alert" class="alert alert-success d-none">
        🎉 Facebook Ads job dispatched!
        <br>
        <strong>Results will be saved here:</strong>
        <a id="result-link" href="#" target="_blank">📂 Open Folder</a>
    </div>

    <button id="fetch-button" type="button" class="btn btn-primary">
        🚀 Fetch All Facebook Ads Data
    </button>

    <div class="mt-4">
        <strong>Last Job Status:</strong>
        <span id="job-status" class="badge bg-secondary">Checking...</span>
    </div>

    <div class="mt-4">
        <h5>📁 Recent Files</h5>
        <ul id="file-list" class="list-group"></ul>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function updateStatusLabel(status) {
        const el = document.getElementById('job-status');
        el.classList.remove('bg-success', 'bg-warning', 'bg-danger', 'bg-secondary');

        switch (status) {
            case 'completed':
                el.textContent = '✅ Completed';
                el.classList.add('bg-success');
                break;
            case 'queued':
                el.textContent = '🕒 Queued';
                el.classList.add('bg-warning');
                break;
            case 'processing':
                el.textContent = '⚙️ Processing';
                el.classList.add('bg-warning');
                break;
            case 'failed':
                el.textContent = '❌ Failed';
                el.classList.add('bg-danger');
                break;
            default:
                el.textContent = '❓ Unknown';
                el.classList.add('bg-secondary');
        }
    }

    function checkStatus() {
        fetch("{{ route('fb.status') }}")
            .then(res => res.json())
            .then(data => updateStatusLabel(data.status));
    }

    function fetchFiles() {
        fetch("{{ route('fb.files') }}")
            .then(res => res.json())
            .then(files => {
                const ul = document.getElementById('file-list');
                ul.innerHTML = '';
                files.forEach(file => {
                    const li = document.createElement('li');
                    li.classList.add('list-group-item');
                    li.innerHTML = `<a href="${file.url}" target="_blank">${file.name}</a>`;
                    ul.appendChild(li);
                });
            });
    }

    document.getElementById('fetch-button').addEventListener('click', function () {
        document.getElementById('status-message').classList.remove('d-none');

        fetch("{{ route('fb.fetch') }}")
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                document.getElementById('status-message').classList.add('d-none');
                document.getElementById('success-alert').classList.remove('d-none');
                document.getElementById('result-link').setAttribute('href', data.output_url);
                updateStatusLabel('queued');
                setTimeout(fetchFiles, 4000); // wait a few seconds before refreshing list
            })
            .catch(error => {
                document.getElementById('status-message').classList.add('d-none');
                alert('❌ Failed to trigger queue: ' + error.message);
            });
    });

    // Initial load
    checkStatus();
    fetchFiles();
</script>
@endsection
