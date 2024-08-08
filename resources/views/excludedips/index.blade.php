<!-- resources/views/excludedips/index.blade.php -->

@extends('layouts.app')

@section('content')
<div class="container">
    <h2>Excluded User ID</h2>
    
    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <a href="{{ route('excludedips.create') }}" class="btn btn-primary mb-3">Add New IP</a>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>IP Address</th>
                <th>User ID</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($ips as $ip)
            <tr>
                <td>{{ $ip->id }}</td>
                <td>{{ $ip->ip_address }}</td>
                <td>{{ $ip->user_id }}</td>
                <td>
                    <a href="{{ route('excludedips.edit', $ip->id) }}" class="btn btn-warning">Edit</a>
                    <form action="{{ route('excludedips.destroy', $ip->id) }}" method="POST" style="display:inline;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">Remove</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
