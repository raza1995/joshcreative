<!-- resources/views/excludedips/edit.blade.php -->

@extends('layouts.app')

@section('content')
<div class="container">
    <h2>Edit Excluded IP Address</h2>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('excludedips.update', $ip->id) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="form-group">
            <label for="ip_address">IP Address:</label>
            <input type="text" name="ip_address" id="ip_address" class="form-control" value="{{ $ip->ip_address }}" >
        </div>
        <div class="form-group">
            <label for="user_id">User ID:</label>
            <input type="text" name="user_id" id="user_id" class="form-control" value="{{ $ip->user_id }}">
        </div>
        <button type="submit" class="btn btn-primary">Update Data</button>
    </form>
</div>
@endsection
