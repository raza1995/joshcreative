<!-- resources/views/excludedips/create.blade.php -->

@extends('layouts.app')

@section('content')
<div class="container">
    <h2>Add New Excluded IP Address</h2>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('excludedips.store') }}" method="POST">
        @csrf
        <div class="form-group">
            <label for="ip_address">IP Address:</label>
            <input type="text" name="ip_address" id="ip_address" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">Add IP</button>
    </form>
</div>
@endsection
