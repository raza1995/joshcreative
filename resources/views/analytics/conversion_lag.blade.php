@extends('layouts.app')

@section('content')
<div class="container">
  <h2>Conversion-Lag Buckets</h2>

  @include('analytics.partials.date_form')

  <table class="table table-bordered">
    <thead>
      <tr>
        <th>Referring Domain</th>
        <th class="text-end">0-5 min</th>
        <th class="text-end">5-30 min</th>
        <th class="text-end">30-120 min</th>
        <th class="text-end">&gt; 2 h</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($stats as $row)
        <tr>
          <td>{{ $row['domain'] }}</td>
          <td class="text-end">{{ $row['bucket_5']   }}</td>
          <td class="text-end">{{ $row['bucket_30']  }}</td>
          <td class="text-end">{{ $row['bucket_120'] }}</td>
          <td class="text-end">{{ $row['bucket_big'] }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endsection
