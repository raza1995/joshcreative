@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <h2>Create New Email</h2>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <form action="{{ route('email-draft.store') }}" method="POST">
    @csrf


    <div class="mb-3">
        <label for="email" class="form-label">Recipient Email</label>
        <input type="email" name="email" class="form-control" required>
    </div>

    <div class="mb-3">
        <label for="subject" class="form-label">Subject</label>
        <input type="text" name="subject" class="form-control" required>
    </div>

    <div class="mb-3">
        <label for="body" class="form-label">Email Body</label>
        <textarea name="body" class="form-control" rows="8" required></textarea>
    </div>

    <div class="mb-3">
    <label for="shopify_order_id" class="form-label">Shopify Order (Optional)</label>
    <select name="shopify_order_id" id="shopify_order_id" 
        class="form-control js-example-basic-single" 
        data-url="{{ route('shopify.orders') }}" 
        style="width: 100%;">
    <option value="">Select Shopify Order</option>
</select>

</div>


    <button type="submit" name="action" value="send" class="btn btn-success">📤 Send Email</button>
    <button type="submit" name="action" value="draft" class="btn btn-secondary">💾 Save as Draft</button>
</form>

</div>
@endsection
@push('scripts')
<script>
       
    tinymce.init({
        selector: '#body',
        height: 300,
        menubar: false,
        plugins: 'link lists',
        toolbar: 'undo redo | bold italic underline | alignleft aligncenter alignright | bullist numlist outdent indent | link'
    });

    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('emailForm');

        form.addEventListener('submit', (e) => {
            const action = e.submitter.value;
            const content = tinymce.get('body').getContent({ format: 'text' }).trim();

            if (!content) {
                e.preventDefault();
                alert('🚨 Email Body cannot be empty.');
                return;
            }

            if (action === 'send' && !confirm('Are you sure you want to send this email now?')) {
                e.preventDefault();
            }
        });
    });
   
</script>
@endpush
