@extends('layouts.app')

@section('content')


<div class="row">
    <div class="col-12">
        <div class="container">
        <div class="card">

            <div class="card-body">
                <div class="row">
                    <div class="col-md-12">
                        {!! $dataTable->table(['class' => 'table table-bordered table-striped dataTable dtr-inline custom-css-datatable']) !!}
                </div>
            </div>
        </div>
    </div>
    </div>
</div>
@endsection
    @push('scripts')

    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}

    @endpush

