@extends('layouts.app')

@section('content')
    <div class="container">
    <div class="card my-4">
    <div class="card-body">
        <h2 class="card-title">User Journey Details</h2>
        <p class="card-text">
            @if($userJourneys->first()->name ?? '')
                <strong>Name:</strong> {{ $userJourneys->first()->name }}<br>
            @endif
            @if($userJourneys->first()->email ?? '')
                <strong>Email:</strong> {{ $userJourneys->first()->email }}<br>
            @endif
            @if($userJourneys->first()->utm_source ?? '')
                <strong>UTM Source:</strong> {{ $userJourneys->first()->utm_source }}
            @endif
        </p>
    </div>
</div>
<div class="row mb-4 mt-4">
            <div class="col-lg-3 col-md-6">
                <div class="card bg-secondary-dark text-white mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Total Pages Visited</h5>
                        <p class="card-text">{{ $metrics['totalPagesVisited'] }}</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card bg-pink-dark-material text-white mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Total Visits</h5>
                        <p class="card-text">{{ $metrics['totalVisits'] }}</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card bg-success-dark text-white mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Total Focus Time</h5>
                        <p class="card-text">{{ round($metrics['totalFocusTime'] / 60) }} minutes {{ $metrics['totalFocusTime'] % 60 }} seconds</p>
                    </div>
                </div>
            </div>
        </div>
        <h2>Journey Map</h2>
        <div class="timeline-container">
            <ul class="timeline">
                @foreach ($userJourneys as $index => $event)
                    <li>
                        <span class="timeline-date">{{ \Carbon\Carbon::parse($event->created_at)->format('F j, Y, g:i:s A') }}</span>
                        <div class="timeline-content">
                        <div class="timeline-content">
    <!-- Page URL with prominent styling -->
    <h3 style="margin-bottom: 5px;">
        <strong>Page:</strong> 
        <a href="{{ $event->page_url }}" target="_blank">{{ $event->page_url }}</a>
    </h3>

    <!-- Focus Time with icon and highlight for better visibility -->
    <p style="margin-bottom: 5px;" class="mt-5">
        <strong><i class="fas fa-clock"></i> Focus Time:</strong> 
        <span style="color: #f44336; font-weight: bold;">
            {{ $event->focus_time }} seconds 
            ({{ round($event->focus_time / 60, 2) }} minutes)
        </span>
    </p>

</div>

                    
                            @if (!empty($journeyMap[$event->page_url]['click_events']))
                            <p>Click Events:</p>
                            <ul>
                                @foreach ($journeyMap[$event->page_url]['click_events'] as $clickEvent)
                                <li>
                                    <strong>Text:</strong> {{ $clickEvent['text'] }}<br>
                                    <strong>URL:</strong> <a href="{{ $clickEvent['url'] }}">{{ $clickEvent['url'] }}</a>
                                </li>
                            @endforeach
                            </ul>
                        @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>


     
        <!-- Page Visits Chart -->
        <div class="card mb-4">
            <div id="pageVisitsChart" class="mb-4"></div>
        </div>

        <!-- Journey Timeline -->
   
    </div>

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <script>
        var pageVisitsData = {
            series: [{
                name: 'Visits',
                data: [
                    @foreach ($journeyMap as $page => $data)
                        {{ $data['visits'] }},
                    @endforeach
                ]
            }],
            chart: {
                type: 'bar',
                height: 350
            },
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '55%',
                    endingShape: 'rounded'
                },
            },
            dataLabels: {
                enabled: true,
                formatter: function (val) {
                    return val + " visits";
                },
                style: {
                    fontSize: '12px',
                    colors: ['#304758']
                },
                offsetY: -5,
            },
            stroke: {
                show: true,
                width: 2,
                colors: ['transparent']
            },
            xaxis: {
                categories: [
                    @foreach ($journeyMap as $page => $data)
                        "{{ $page }}",
                    @endforeach
                ],
            },
            yaxis: {
                title: {
                    text: 'Visits'
                }
            },
            fill: {
                opacity: 1
            },
            tooltip: {
                y: {
                    formatter: function (val) {
                        return val + " visits";
                    }
                }
            }
        };

        var pageVisitsChart = new ApexCharts(document.querySelector("#pageVisitsChart"), pageVisitsData);
        pageVisitsChart.render();
    </script>
@endpush
@endsection
