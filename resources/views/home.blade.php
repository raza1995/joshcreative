@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card card-flush h-xl-100">
                <div class="card-header pt-5">{{ __('Dashboard') }}</div>

                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if(auth()->check())
                        {{ __('You are logged in!') }}
                    @else
                        {{ __('You are not logged in.') }}
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
