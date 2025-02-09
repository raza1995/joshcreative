@extends('layouts.app')

@section('head')
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
@endsection

@section('content')
<div class="container mt-4">
    <h2>Edit Email Draft</h2>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form action="{{ route('email-draft.update', $draft->id) }}" method="POST">
        @csrf

        <div class="form-group">
            <label for="subject">Subject:</label>
            <input type="text" name="subject" class="form-control" value="{{ old('subject', $draft->subject) }}" required>
        </div>

        <div class="form-group mt-3">
            <label for="body">Email Body:</label>
            <textarea name="body" id="emailBody" class="form-control" rows="10" required>{{ old('body', $draft->body) }}</textarea>
        </div>

        <button type="submit" class="btn btn-primary mt-3">Update Draft</button>
        <a href="{{ route('email-draft.index') }}" class="btn btn-secondary mt-3">Cancel</a>
    </form>
</div>
@endsection

@section('scripts')
<script>
    tinymce.init({
        selector: '#emailBody',
        height: 400,
        menubar: false,
        plugins: [
            'advlist autolink lists link image charmap preview anchor',
            'searchreplace visualblocks code fullscreen',
            'insertdatetime media table code help wordcount'
        ],
        toolbar: 'undo redo | formatselect | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image | preview code',
        content_style: 'body { font-family:Arial,sans-serif; font-size:14px }',
        readonly: false,  // ✅ Ensure it's not read-only
        setup: function (editor) {
            editor.on('init', function () {
                editor.setMode('design');  // ✅ Force editable mode
            });
        }
    });
</script>

@endsection
