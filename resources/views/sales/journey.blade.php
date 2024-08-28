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
        {{-- <div class="col-md-4">
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
        </div> --}}
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
                        {{-- <div class="col-md-3">
                            <label for="startDate">Start Date</label>
                            <input type="date" id="startDate" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label for="endDate">End Date</label>
                            <input type="date" id="endDate" class="form-control">
                        </div> --}}
                    </div>

                    <div id="analyticsChart" width="400" height="200"></div>
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
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    var journeyMap = @json($journeyMap);
    var topLandingPages = @json($topLandingPages);
    var topExitPages = @json($topExitPages);
    var events = @json($events);

    console.log('Journey Map:', journeyMap);
    console.log('Top Landing Pages:', topLandingPages);
    console.log('Top Exit Pages:', topExitPages);
    console.log('Events:', events);

    var pages = Object.keys(journeyMap);
    var visits = pages.map(page => journeyMap[page].visits);

    // Prepare data for transitions
    var transitions = [];
    var transitionData = [];

    pages.forEach(page => {
        Object.keys(journeyMap[page].nextPages).forEach(nextPage => {
            if (page !== nextPage) { // Filter out same URL transitions
                transitions.push(`${page} -> ${nextPage}`);
                transitionData.push(journeyMap[page].nextPages[nextPage]);
            }
        });
    });

    console.log('Transitions:', transitions);
    console.log('Transition Data:', transitionData);

    var chartFilter = document.getElementById('chartFilter');
    var pageFilter = document.getElementById('pageFilter');
    var startDate = document.getElementById('startDate');
    var endDate = document.getElementById('endDate');
    var currentChart;

    const mainmaterialColors = [
        '#E53935', '#AD1457', '#9C27B0', '#673AB7', '#3F51B5',
        '#2196F3', '#03A9F4', '#00BCD4', '#009688', '#4CAF50',
        '#8BC34A', '#CDDC39', '#FFEB3B', '#FFC107', '#FF9800', '#FF5722'
    ];
    const materialColors = [
    '#E57373', // Darker Red
    '#F06292', // Darker Pink
    '#BA68C8', // Darker Purple
    '#9575CD', // Darker Deep Purple
    '#7986CB', // Darker Indigo
    '#64B5F6', // Darker Blue
    '#4FC3F7', // Darker Light Blue
    '#4DD0E1', // Darker Cyan
    '#4DB6AC', // Darker Teal
    '#81C784', // Darker Green
    '#AED581', // Darker Light Green
    '#DCE775', // Darker Lime
    '#FFF176', // Darker Yellow
    '#FFD54F', // Darker Amber
    '#FFB74D', // Darker Orange
    '#FF8A65', // Darker Deep Orange
];
const materialDarkerColorsWithBorders = [
    '#E57373', // Darker Red
    '#F06292', // Darker Pink
    '#BA68C8', // Darker Purple
    '#9575CD', // Darker Deep Purple
    '#7986CB', // Darker Indigo
    '#64B5F6', // Darker Blue
    '#4FC3F7', // Darker Light Blue
    '#4DD0E1', // Darker Cyan
    '#4DB6AC', // Darker Teal
    '#81C784', // Darker Green
    '#AED581', // Darker Light Green
    '#DCE775', // Darker Lime
    '#FFF176', // Darker Yellow
    '#FFD54F', // Darker Amber
    '#FFB74D', // Darker Orange
    '#FF8A65', // Darker Deep Orange
];



    function getColor(index) {
        return materialColors[index % materialColors.length];
    }
    function getColorMain(index) {
        return mainmaterialColors[index % mainmaterialColors.length];
    }
    function createChart(options) {
        if (currentChart) {
            currentChart.destroy();
        }

        currentChart = new ApexCharts(document.querySelector("#analyticsChart"), options);
        currentChart.render();
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
        var filteredData, labels, data, chartOptions;

        console.log('Selected Filter:', selectedFilter);
        console.log('Selected Page:', selectedPage);

        if (selectedFilter === 'visits') {
            filteredData = filterData(pages.map(page => ({
                page: page,
                date: journeyMap[page].date,
                value: journeyMap[page].visits
            })), selectedPage, startDate.value, endDate.value);
            labels = filteredData.map(item => item.page);
            data = filteredData.map(item => item.value);
            console.log(data, 'dsadsa');
         chartOptions = {
    chart: { 
        type: 'bar', 
        height: 350, 
        toolbar: { show: true } 
    },
    series: [{ 
        name: 'Number of Visits', 
        data: data 
    }],
    plotOptions: {
        bar: {

            distributed: true, // Enable distributed colors
            columnWidth: '60%', // Adjust width to create separation
        },
        border: {
                width: 11,
                colors: ['#000000'] // Black border around each bar
            }
    },
    
    colors: labels.map((_, index) => getColor(index)),

    xaxis: {
        categories: labels,
        labels: {
            rotate: -45, // Rotate labels for better readability
            style: {
                fontSize: '12px',
            },
            formatter: function(value) {
                let path = value.replace(/^https?:\/\/[^\/]+/, '');

                // Truncate the remaining part if it's longer than 30 characters
                return path.length > 30 ? path.substring(0, 30) + '...' : path;
            }
        }
    },
    tooltip: {
        y: {
            formatter: function(value, { series, seriesIndex, dataPointIndex, w }) {
                return `Visits: ${value}<br>Page: ${labels[dataPointIndex]}`; // Show number of visits and corresponding data
            }
        },
        style: {
            fontSize: '12px',
        }
    },
    dataLabels: {
        enabled: true, // Enable data labels
        formatter: function(value, { series, seriesIndex, dataPointIndex, w }) {
            return `${value}`; // Show the number of visits on each bar
        },
        style: {
            fontSize: '12px',
            colors: ['#000']
        },
        offsetY: -20, // Position the labels above the bars
    },
    legend: {
        show: false
    },

    
};
        } else if (selectedFilter === 'transitions') {
            filteredData = filterData(transitions.map((transition, index) => ({
                page: transition,
                date: journeyMap[transition.split(' -> ')[0]].date,
                value: transitionData[index]
            })), selectedPage, startDate.value, endDate.value);
            labels = filteredData.map(item => item.page);
            data = filteredData.map(item => item.value);
            chartOptions = {
    chart: { 
        type: 'bar', 
        height: 350, 
        toolbar: { show: true } 
    },
    series: [{ 
        name: 'Number of Visits', 
        data: data 
    }],
    plotOptions: {
        bar: {
            borderRadius: 4,
            distributed: true, // Enable distributed colors
        }
    },
    colors: labels.map((_, index) => getColor(index)),
    xaxis: {
        categories: labels,
        labels: {
            rotate: -45, // Rotate labels for better readability
            style: {
                fontSize: '12px',
            },
            formatter: function(value) {
                let path = value.replace(/^https?:\/\/[^\/]+/, '');

                // Truncate the remaining part if it's longer than 30 characters
                return path.length > 30 ? path.substring(0, 30) + '...' : path;
            }
        }
    },
    tooltip: {
        y: {
            formatter: function(value, { series, seriesIndex, dataPointIndex, w }) {
                return `Visits: ${value}<br>Page: ${labels[dataPointIndex]}`; // Show number of visits and corresponding data
            }
        },
        style: {
            fontSize: '12px',
        }
    },
    dataLabels: {
        enabled: true, // Enable data labels
        formatter: function(value, { series, seriesIndex, dataPointIndex, w }) {
            return `${value}`; // Show the number of visits on each bar
        },
        style: {
            fontSize: '12px',
            colors: ['#000']
        },
        offsetY: -20, // Position the labels above the bars
    },
    legend: {
        show: false
    }
};
        } else if (selectedFilter === 'landing') {
            filteredData = filterData(Object.keys(topLandingPages).map(page => ({
                page: page,
                date: journeyMap[page].date,
                value: topLandingPages[page]
            })), selectedPage, startDate.value, endDate.value);
            labels = filteredData.map(item => item.page);
            data = filteredData.map(item => item.value);
            chartOptions = {
    chart: { 
        type: 'bar', 
        height: 350, 
        toolbar: { show: true } 
    },
    series: [{ 
        name: 'Number of Visits', 
        data: data 
    }],
    plotOptions: {
        bar: {
            borderRadius: 4,
            distributed: true, // Enable distributed colors
        }
    },
    colors: labels.map((_, index) => getColor(index)),
    xaxis: {
        categories: labels,
        labels: {
            rotate: -45, // Rotate labels for better readability
            style: {
                fontSize: '12px',
            },
            formatter: function(value) {
                let path = value.replace(/^https?:\/\/[^\/]+/, '');

                // Truncate the remaining part if it's longer than 30 characters
                return path.length > 30 ? path.substring(0, 30) + '...' : path;
            }
        }
    },
    tooltip: {
        y: {
            formatter: function(value, { series, seriesIndex, dataPointIndex, w }) {
                return `Visits: ${value}<br>Page: ${labels[dataPointIndex]}`; // Show number of visits and corresponding data
            }
        },
        style: {
            fontSize: '12px',
        }
    },
    dataLabels: {
        enabled: true, // Enable data labels
        formatter: function(value, { series, seriesIndex, dataPointIndex, w }) {
            return `${value}`; // Show the number of visits on each bar
        },
        style: {
            fontSize: '12px',
            colors: ['#000']
        },
        offsetY: -20, // Position the labels above the bars
    },
    legend: {
        show: false
    }
};
        } else if (selectedFilter === 'exit') {
            filteredData = filterData(Object.keys(topExitPages).map(page => ({
                page: page,
                date: journeyMap[page].date,
                value: topExitPages[page]
            })), selectedPage, startDate.value, endDate.value);
            labels = filteredData.map(item => item.page);
            data = filteredData.map(item => item.value);
            chartOptions = {
    chart: { 
        type: 'bar', 
        height: 350, 
        toolbar: { show: true } 
    },
    series: [{ 
        name: 'Number of Visits', 
        data: data 
    }],
    plotOptions: {
        bar: {
            borderRadius: 4,
            distributed: true, // Enable distributed colors
        }
    },
    colors: labels.map((_, index) => getColor(index)),
    xaxis: {
        categories: labels,
        labels: {
            rotate: -45, // Rotate labels for better readability
            style: {
                fontSize: '12px',
            },
            formatter: function(value) {
                let path = value.replace(/^https?:\/\/[^\/]+/, '');

                // Truncate the remaining part if it's longer than 30 characters
                return path.length > 30 ? path.substring(0, 30) + '...' : path;
            }
        }
    },
    tooltip: {
        y: {
            formatter: function(value, { series, seriesIndex, dataPointIndex, w }) {
                return `Visits: ${value}<br>Page: ${labels[dataPointIndex]}`; // Show number of visits and corresponding data
            }
        },
        style: {
            fontSize: '12px',
        }
    },
    dataLabels: {
        enabled: true, // Enable data labels
        formatter: function(value, { series, seriesIndex, dataPointIndex, w }) {
            return `${value}`; // Show the number of visits on each bar
        },
        style: {
            fontSize: '12px',
            colors: ['#000']
        },
        offsetY: -20, // Position the labels above the bars
    },
    legend: {
        show: false
    }
};
        }

        console.log('Chart Options:', chartOptions);
        createChart(chartOptions);
    }

    chartFilter.addEventListener('change', updateChart);
    pageFilter.addEventListener('change', updateChart);
    startDate.addEventListener('change', updateChart);
    endDate.addEventListener('change', updateChart);
    updateChart(); // Initial chart

    // User Events Chart
 // Assuming the `events` variable contains the enhanced event data from the backend
 var eventLabels = Object.keys(events);
var eventCounts = Object.values(events);

var eventChartOptions = {
    chart: { 
        type: 'pie', 
        height: 350, 
        toolbar: { show: true } 
    },
    series: eventCounts,
    labels: eventLabels,
    colors: eventLabels.map((_, index) => getColorMain(index)),
    title: { 
        text: 'User Events Distribution (Click Events)', 
        align: 'center' 
    }
};

var eventChart = new ApexCharts(document.querySelector("#eventDataChart"), eventChartOptions);
eventChart.render();

});



</script>
@endsection

