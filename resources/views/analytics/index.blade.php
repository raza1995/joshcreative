@extends('layouts.app')

@section('content')
<div class="container">
    <h3>🎯 Creative Suggestions Dashboard (Last 14 Days)</h3>

    @foreach ([
        'Turn into Video',
        'Change the visual delivery',
        'Change the Angle',
        'Change hook variation',
        'Change messaging',
        'Trash'
    ] as $action)
        <div class="card my-4">
            <div class="bg-primary text-white d-flex justify-content-between p-3">
                <strong>{{ $action }}</strong>
            </div>
            <div class="card-body">
                <table class="table table-bordered datatable" data-action-type="{{ $action }}">
                    <thead>
                        <tr>
                            <th>Ad Account Name</th>
                            <th>Ad ID</th>
                            <th>Campaign</th>
                            <th>Ad Name</th>
                            <th>Adset</th>
                            <th>Spend</th>
                            <th>CPA</th>
                            <th>Ad Link</th>
                            <th>Thumbnail</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    @endforeach
</div>
@endsection

@push('scripts')


<script>
$(document).ready(function () {
    $('.datatable').each(function () {
        let table = $(this);
        let actionType = table.data('action-type');

        table.DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ route("suggestions.data") }}',
                data: { action_type: actionType }
            },
            columns: [
                { data: 'ad_account_name' },
                { data: 'ad_id' },
                { data: 'campaign_name' },
                { data: 'ad_name' },
                { data: 'adset_name' },
                { data: 'spend' },
                { data: 'cpa' },
                { data: 'ad_link', orderable: false, searchable: false },
                { data: 'thumbnail_url', orderable: false, searchable: false }
            ]
        });
    });
});
</script>
@endpush
