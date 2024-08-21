@extends('layouts.app')

@section('content')
    <div class="container">
        <h1 class="my-4">
            User Journey 
            @if ($userJourneys->first()->name ?? false)
                for User Name: {{ $userJourneys->first()->name }}
            @endif
            
            @if ($userJourneys->first()->email ?? false)
                - Email: {{ $userJourneys->first()->email }}
            @endif
        </h1>

        <!-- Summary Cards -->
        <div class="row mb-4">
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
                        <p class="card-text">{{ $metrics['totalFocusTime'] }} seconds</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Page Visits Chart -->
        <div class="card mb-4">
            <div id="pageVisitsChart" class="mb-4"></div>
        </div>

        <!-- Journey Timeline -->
        <h2>Journey Map</h2>
        <div class="timeline-container">
            <ul class="timeline">
                @foreach ($userJourneys as $index => $event)
                    <li>
                        <span class="timeline-date">{{ \Carbon\Carbon::parse($event->start_time)->format('Y-m-d h:i:s A') }}</span>
                        <div class="timeline-content">
                            <h3>{{ $event->page_url }}</h3>
                            <p>Focus Time: 
                                {{ $event->focus_time }} seconds 
                                ({{ round($event->focus_time / 60, 2) }} minutes)
                            </p>
                            
                    
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
