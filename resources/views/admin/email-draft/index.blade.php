@extends('layouts.app')

@section('content')
<div class="container">
    <h2>Pending Email Drafts</h2>
    <button id="approve-selected" class="btn btn-success">Approve Selected</button>
    <button id="disapprove-selected" class="btn btn-danger">Disapprove Selected</button>
    <button id="send-selected" class="btn btn-primary">Send Emails</button>

    <table id="emailDraftsTable" class="table table-bordered">
        <thead>
            <tr>
                <th><input type="checkbox" id="select-all"></th>
                <th>Subject</th>
                <th>Body</th>
                <th>Status</th>
                <th>Shopify Order</th>
                <th>Actions</th>
            </tr>
        </thead>
    </table>
</div>
@endsection

@push('scripts')


<script>
    $(document).ready(function() {
        const table = $('#emailDraftsTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ route("email-draft.data") }}',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}'
                }
            },
            columns: [
                { data: 'checkbox', orderable: false, searchable: false },
                { data: 'subject' },
                { data: 'body' },
                { data: 'status' },
                { data: 'shopify_order', defaultContent: '-' },
                { data: 'actions', orderable: false, searchable: false }
            ]
        });

        $('#select-all').on('click', function() {
            $('.select-draft').prop('checked', this.checked);
        });

        function getSelectedDrafts() {
            return $('.select-draft:checked').map(function() {
                return $(this).val();
            }).get();
        }

        $('#approve-selected').click(function() {
            const draftIds = getSelectedDrafts();
            $.post('{{ route("email-draft.approve") }}', { draft_ids: draftIds, _token: '{{ csrf_token() }}' }, function(response) {
                alert(response.message);
                table.ajax.reload();
            });
        });

        $('#disapprove-selected').click(function() {
            const draftIds = getSelectedDrafts();
            const reason = prompt('Please enter a reason for disapproval:');
            if (reason) {
                $.post('{{ route("email-draft.disapprove") }}', {
                    draft_ids: draftIds,
                    reason: reason,
                    _token: '{{ csrf_token() }}'
                }, function(response) {
                    alert(response.message);
                    table.ajax.reload();
                });
            }
        });

        $('#send-selected').click(function() {
            const draftIds = getSelectedDrafts();
            $.post('{{ route("email-draft.send") }}', { draft_ids: draftIds, _token: '{{ csrf_token() }}' }, function(response) {
                alert(response.message);
                table.ajax.reload();
            });
        });
    });


    function approveDraft(id) {
    if (confirm('Are you sure you want to approve this draft?')) {
        $.post('/email-draft/approve', { id: id, _token: '{{ csrf_token() }}' }, function(response) {
            alert(response.message);
            location.reload();
        });
    }
}

function disapproveDraft(id) {
    if (confirm('Are you sure you want to disapprove this draft?')) {
        $.post('/email-draft/disapprove', { id: id, _token: '{{ csrf_token() }}' }, function(response) {
            alert(response.message);
            location.reload();
        });
    }
}

</script>
@endpush
