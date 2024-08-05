@extends('layouts.app')

@section('content')


<div class="row ">

        <div class="container">
            <div class="card">
                <div class="card-body">
                  
                        <div class="col-md-12">
                            {!! $dataTable->table(['class' => 'table table-bordered']) !!}
                        </div>
                  
                </div>
            </div>
        </div>
   <!-- Modal for Uploading Sales Data -->
<div class="modal " id="uploadSalesModal" tabindex="-1" role="dialog" aria-labelledby="uploadSalesModalLabel" aria-hidden="false">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadSalesModalLabel">Upload Sales Data</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="{{ route('upload-sales-data') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label for="sales_file">Upload Sales Data</label>
                        <input type="file" class="form-control" name="sales_file" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

</div>
@endsection
    @push('scripts')

    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}

    @endpush

