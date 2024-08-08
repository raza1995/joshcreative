<!-- resources/views/excludedips/show.blade.php -->

@extends('layouts.app')

@section('content')
<div class="container">
    <h2>Excluded IP Address Details</h2>
    
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">IP Address: {{ $ip->ip_address }}</h5>
            <a href="{{ route('excludedips.index') }}" class="btn btn-primary">Back to List</a>
        </div>
    </div>
</div>
@endsection
