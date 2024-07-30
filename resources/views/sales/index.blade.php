@extends('layouts.app')

@section('content')


<div class="row">
    <div class="col-12">
        <div class="card">

            <div class="card-body">
                <div class="row">
                    <div class="col-md-12">
                        {{ $dataTable->table() }}                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    @push('scripts')

    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}

    @endpush
@endsection
