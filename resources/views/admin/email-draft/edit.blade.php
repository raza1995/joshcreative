@extends('layouts.app')


@section('content')
<div class="container mt-4">
    <h2>Edit Email Draft</h2>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    <div class="card mb-4">
       
        <div class="card-body" style="background-color: #f9f9f9; max-height: 300px; overflow-y: auto;">
        <label for="body">📩 Original Email  Body:</label>
            {!! nl2br(e($draft->original_email)) ?? 'No original email found.' !!}
        </div>
    </div>
    <form id="emailDraftForm" action="{{ route('email-draft.update', $draft->id) }}" method="POST">
        @csrf

        <div class="form-group">
            <label for="subject">Subject:</label>
            <input type="text" name="subject" class="form-control" value="{{ old('subject', $draft->subject) }}" required>
        </div>

        <div class="form-group mt-3">
            <label for="body">Email Body:</label>
            <textarea name="body" id="emailBody" class="form-control" rows="10" required>{{ old('body', $draft->body) }}</textarea>
        </div>

        <div class="mt-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Update Draft</button>
            <button type="button" class="btn btn-success" id="approveDraft">✅ Approve</button>
            <button type="button" class="btn btn-info text-white" id="sendDraft">📤 Send</button>
            <a href="{{ route('email-draft.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')

<script>
    tinymce.init({
        selector: '#emailBody',
        height: 300,
        menubar: false,
        plugins: 'link lists',
        toolbar: 'undo redo | bold italic underline | alignleft aligncenter alignright | bullist numlist outdent indent | link'
    });

    const draftId = "{{ $draft->id }}";

    // ✅ Approve Button
    document.getElementById('approveDraft').addEventListener('click', function () {
        Swal.fire({
            title: '✅ Approve Draft?',
            text: 'Are you sure you want to approve this draft?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Approve!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(`/email-draft/approve/${draftId}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    Swal.fire('✅ Approved!', data.message, 'success');
                    setTimeout(() => window.location.href = "{{ route('email-draft.index') }}", 1500);
                })
                .catch(error => Swal.fire('🚨 Error', 'An error occurred.', 'error'));
            }
        });
    });

    // ✅ Send Button
    document.getElementById('sendDraft').addEventListener('click', function () {
        Swal.fire({
            title: '📤 Send Email?',
            text: 'Are you sure you want to send this email?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Send!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(`/email-draft/send/${draftId}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    Swal.fire('📤 Sent!', data.message, 'success');
                    setTimeout(() => window.location.href = "{{ route('email-draft.index') }}", 1500);
                })
                .catch(error => Swal.fire('🚨 Error', 'An error occurred.', 'error'));
            }
        });
    });
</script>
@endpush
