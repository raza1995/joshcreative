@extends('layouts.app')

@section('content')
<div class="container">
    <h1 class="my-4">Data Analytics</h1>

    <div class="row">
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Bounce Rate</h5>
                    <p class="card-text">{{ round($bounceRate, 2) }}%</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Average Session Duration</h5>
                    <p class="card-text">{{ round($averagePageViewsPerSession / 60, 2) }} minutes</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Total Page Views</h5>
                    <p class="card-text">{{ $totalPageViews }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Unique Pages Visited</h5>
                    <p class="card-text">{{ $uniquePagesVisited }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Average Page Views Per Session</h5>
                    <p class="card-text">{{ round($averagePageViewsPerSession, 2) }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Total Unique Visitors</h5>
                    <p class="card-text">{{ $totalUniqueVisitors }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- User Segmentation Section -->
    <div class="row">
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">New Users</h5>
                    <p class="card-text">{{ $segments['newUsers'] }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Returning Users</h5>
                    <p class="card-text">{{ $segments['returningUsers'] }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Engaged Users</h5>
                    <p class="card-text">{{ $segments['engagedUsers'] }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Bounced Users</h5>
                    <p class="card-text">{{ $segments['bouncedUsers'] }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Converted Users</h5>
                    <p class="card-text">{{ $segments['convertedUsers'] }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">User Journey Analytics</h5>
                    
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label for="chartFilter">Select Metric</label>
                            <select id="chartFilter" class="form-control">
                                <option value="visits">Page Visits</option>
                                <option value="transitions">Page Transitions</option>
                                <option value="landing">Top Landing Pages</option>
                                <option value="exit">Top Exit Pages</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="pageFilter">Filter by Page</label>
                            <select id="pageFilter" class="form-control">
                                <option value="all">All Pages</option>
                                @foreach($landingPages as $page)
                                    <option value="{{ $page }}">{{ $page }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="startDate">Start Date</label>
                            <input type="date" id="startDate" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label for="endDate">End Date</label>
                            <input type="date" id="endDate" class="form-control">
                        </div>
                    </div>

                    <canvas id="analyticsChart" width="400" height="200"></canvas>
                </div>
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">User Events Analytics</h5>
                                <div id="eventDataChart"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var journeyMap = @json($journeyMap);
    var topLandingPages = @json($topLandingPages);
    var topExitPages = @json($topExitPages);
    var pages = Object.keys(journeyMap);
    var visits = pages.map(page => journeyMap[page].visits);

    // Prepare data for transitions
    var transitions = [];
    var transitionData = [];

    pages.forEach(page => {
        Object.keys(journeyMap[page].nextPages).forEach(nextPage => {
            transitions.push(`${page} -> ${nextPage}`);
            transitionData.push(journeyMap[page].nextPages[nextPage]);
        });
    });

    var chartFilter = document.getElementById('chartFilter');
    var pageFilter = document.getElementById('pageFilter');
    var startDate = document.getElementById('startDate');
    var endDate = document.getElementById('endDate');
    var ctx = document.getElementById('analyticsChart').getContext('2d');
    var currentChart;

    function createChart(type, labels, data, label) {
        if (currentChart) {
            currentChart.destroy();
        }

        currentChart = new Chart(ctx, {
            type: type,
            data: {
                labels: labels,
                datasets: [{
                    label: label,
                    data: data,
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    x: {
                        ticks: {
                            autoSkip: false,
                            maxRotation: 90,
                            minRotation: 45
                        }
                    },
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    function filterData(data, page, startDate, endDate) {
        return data.filter(function(item) {
            var itemDate = new Date(item.date);
            var startDateValid = startDate ? new Date(startDate) <= itemDate : true;
            var endDateValid = endDate ? new Date(endDate) >= itemDate : true;
            var pageValid = page === 'all' || item.page === page;

            return startDateValid && endDateValid && pageValid;
        });
    }

    function updateChart() {
        var selectedFilter = chartFilter.value;
        var selectedPage = pageFilter.value;
        var filteredData, labels, data;

        if (selectedFilter === 'visits') {
            filteredData = filterData(pages.map(page => ({
                page: page,
                date: journeyMap[page].date,
                value: journeyMap[page].visits
            })), selectedPage, startDate.value, endDate.value);
            labels = filteredData.map(item => item.page);
            data = filteredData.map(item => item.value);
            createChart('bar', labels, data, 'Number of Visits');
        } else if (selectedFilter === 'transitions') {
            filteredData = filterData(transitions.map((transition, index) => ({
                page: transition,
                date: journeyMap[transition.split(' -> ')[0]].date,
                value: transitionData[index]
            })), selectedPage, startDate.value, endDate.value);
            labels = filteredData.map(item => item.page);
            data = filteredData.map(item => item.value);
            createChart('bar', labels, data, 'Number of Transitions');
        } else if (selectedFilter === 'landing') {
            filteredData = filterData(Object.keys(topLandingPages).map(page => ({
                page: page,
                date: journeyMap[page].date,
                value: topLandingPages[page]
            })), selectedPage, startDate.value, endDate.value);
            labels = filteredData.map(item => item.page);
            data = filteredData.map(item => item.value);
            createChart('bar', labels, data, 'Top Landing Pages');
        } else if (selectedFilter === 'exit') {
            filteredData = filterData(Object.keys(topExitPages).map(page => ({
                page: page,
                date: journeyMap[page].date,
                value: topExitPages[page]
            })), selectedPage, startDate.value, endDate.value);
            labels = filteredData.map(item => item.page);
            data = filteredData.map(item => item.value);
            createChart('bar', labels, data, 'Top Exit Pages');
        }
    }

    chartFilter.addEventListener('change', updateChart);
    pageFilter.addEventListener('change', updateChart);
    startDate.addEventListener('change', updateChart);
    endDate.addEventListener('change', updateChart);
    updateChart(); // Initial chart

    var events = @json($events);
    var eventTypes = events.map(event => event.event_type);
    var eventCounts = events.map(event => event.count);

    var eventChartOptions = {
        chart: { type: 'pie', height: 350 },
        series: eventCounts,
        labels: eventTypes,
        title: { text: 'User Events Distribution', align: 'center' }
    };

    var eventChart = new ApexCharts(document.querySelector("#eventDataChart"), eventChartOptions);
    eventChart.render();
});
</script>
@endsection

