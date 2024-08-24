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

                        <!-- Click Events -->
                        @if (!empty($journeyMap[$event->page_url]['click_events']))
                            <p><strong>Click Events:</strong></p>
                            <ul>
                                @foreach ($journeyMap[$event->page_url]['click_events'] as $clickEvent)
                                    <li>
                                        <strong>Text:</strong> {{ $clickEvent['text'] }}<br>
                                        <strong>URL:</strong> <a href="{{ $clickEvent['url'] }}">{{ $clickEvent['url'] }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif


                      <!-- Focus Events -->
@if (!empty($journeyMap[$event->page_url]['focus_events']))
    <p><strong>Focus Events:</strong></p>
    <ul>
        @foreach ($journeyMap[$event->page_url]['focus_events'] as $focusEvent)
            @php
                // Load the HTML element string into DOMDocument to parse
                $dom = new DOMDocument();
                @$dom->loadHTML($focusEvent['element']);
                
                // Extract the input element
                $input = $dom->getElementsByTagName('input')->item(0);
                $textarea = $dom->getElementsByTagName('textarea')->item(0);
                $select = $dom->getElementsByTagName('select')->item(0);
                
                // Initialize variables
                $elementName = 'Unknown';
                $elementType = '';
                $elementId = '';
                $elementPlaceholder = '';
                
                if ($input) {
                    $elementName = $input->getAttribute('name') ?: 'Unnamed input';
                    $elementType = $input->getAttribute('type') ?: 'text';
                    $elementId = $input->getAttribute('id') ?: 'No ID';
                    $elementPlaceholder = $input->getAttribute('placeholder') ?: '';
                } elseif ($textarea) {
                    $elementName = $textarea->getAttribute('name') ?: 'Unnamed textarea';
                    $elementType = 'textarea';
                    $elementId = $textarea->getAttribute('id') ?: 'No ID';
                    $elementPlaceholder = $textarea->getAttribute('placeholder') ?: '';
                } elseif ($select) {
                    $elementName = $select->getAttribute('name') ?: 'Unnamed select';
                    $elementType = 'select';
                    $elementId = $select->getAttribute('id') ?: 'No ID';
                }
            @endphp

            <li>
                <strong>Field Name:</strong> {{ $elementName }}<br>
                @if($elementType) <strong>Field Type:</strong> {{ $elementType }}<br> @endif
                @if($elementId) <strong>ID:</strong> {{ $elementId }}<br> @endif
                @if($elementPlaceholder) <strong>Placeholder:</strong> {{ $elementPlaceholder }}<br> @endif
            </li>
        @endforeach
    </ul>
@endif


                        <!-- Change Events -->
                        @if (!empty($journeyMap[$event->page_url]['change_events']))
    <p><strong>Change Events:</strong></p>
    <ul>
        @foreach ($journeyMap[$event->page_url]['change_events'] as $changeEvent)
            @php
                // Load the HTML element string into DOMDocument to parse
                $dom = new DOMDocument();
                @$dom->loadHTML($changeEvent['element']);
                
                // Extract the input element
                $input = $dom->getElementsByTagName('input')->item(0);
                $textarea = $dom->getElementsByTagName('textarea')->item(0);
                $select = $dom->getElementsByTagName('select')->item(0);
                
                // Initialize variables
                $elementName = 'Unknown';
                $elementType = '';
                $elementPlaceholder = '';
                
                if ($input) {
                    $elementName = $input->getAttribute('name') ?: 'Unnamed input';
                    $elementType = $input->getAttribute('type') ?: 'text';
                    $elementPlaceholder = $input->getAttribute('placeholder') ?: '';
                } elseif ($textarea) {
                    $elementName = $textarea->getAttribute('name') ?: 'Unnamed textarea';
                    $elementType = 'textarea';
                    $elementPlaceholder = $textarea->getAttribute('placeholder') ?: '';
                } elseif ($select) {
                    $elementName = $select->getAttribute('name') ?: 'Unnamed select';
                    $elementType = 'select';
                }

                $elementValue = $changeEvent['value'] ?? 'No Value';
            @endphp

            <li>
                <strong>Field Name:</strong> {{ $elementName }}<br>
                @if($elementType) <strong>Field Type:</strong> {{ $elementType }}<br> @endif
                @if($elementPlaceholder) <strong>Placeholder:</strong> {{ $elementPlaceholder }}<br> @endif
                <strong>Value:</strong> {{ $elementValue }}
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
