@extends('layouts.app')

@section('content')
    <body class="antialiased">
        <div id="kt_app_content" class="app-content flex-column-fluid">
            <div id="kt_app_content_container" class="app-container container-xxl">
                <div class="container">
                    <h1>Revenue Dashboard</h1>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <button class="btn btn-primary" onclick="updateChart('daily')">Daily</button>
                            <button class="btn btn-primary" onclick="updateChart('monthly')">Monthly</button>
                            <button class="btn btn-primary" onclick="updateChart('yearly')">Yearly</button>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <input type="text" id="startDate" class="form-control" placeholder="Start Date">
                        </div>
                        <div class="col-md-3">
                            <input type="text" id="endDate" class="form-control" placeholder="End Date">
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-primary" onclick="filterByDateRange()">Apply Date Range</button>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <h2>Current Month Revenue: $<span id="currentMonthRevenue">{{ number_format($currentMonthRevenue , 2) }}</span></h2>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <h2>Total Revenue: $<span id="totalRevenue">0.00</span></h2>
                        </div>
                    </div>
                    <div id="chart" style="height: 400px;"></div>
                </div>
                <div class="container">
                    <h1 class="mb-4">Top Performing Pages</h1>
                    <div class="card shadow-sm">
                       
                        <div class="card-body" >
                            
                                <canvas style="height: 200px" id="pageChart"></canvas>
                          
                        
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endsection
    @push('scripts')
    <script>
   document.addEventListener('DOMContentLoaded', function () {
    $("#startDate").datepicker({ dateFormat: 'yy-mm-dd' });
    $("#endDate").datepicker({ dateFormat: 'yy-mm-dd' });

    var dailyData = @json($dailyRevenue );
    var monthlyData = @json($monthlyRevenue );
    var yearlyData = @json($yearlyRevenue );
    console.log(yearlyData)
    var options = {
        chart: {
            type: 'line',
            height: '400px'
        },
        series: [{
            name: 'Revenue',
            data: dailyData.map(item => parseFloat(item.total).toFixed(2))
        }],
        xaxis: {
            categories: dailyData.map(item => item.date)
        },
        yaxis: {
            title: {
                text: 'Revenue'
            }
        },
        title: {
            text: 'Daily Revenue',
            align: 'center'
        }
    };
    
    var chart = new ApexCharts(document.querySelector("#chart"), options);
    chart.render();

    function calculateTotalRevenue(data) {
       
        return data.reduce((sum, item) => sum + parseFloat(item.total), 0).toFixed(2);
    }

    function updateTotalRevenue(data) {
        console.log(data);
        var totalRevenue = calculateTotalRevenue(data);
        document.getElementById('totalRevenue').innerText = totalRevenue;
    }

    window.updateChart = function(view) {
        var data = [];
        var title = '';
        if (view === 'daily') {
            data = dailyData;
            title = 'Daily Revenue';
        } else if (view === 'monthly') {
            data = monthlyData;
            title = 'Monthly Revenue';
        } else if (view === 'yearly') {
            data = yearlyData;
            title = 'Yearly Revenue';
        }

        chart.updateOptions({
            series: [{
                name: 'Revenue',
                data: data.map(item => parseFloat(item.total).toFixed(2))
            }],
            xaxis: {
                categories: data.map(item => item.date)
            },
            title: {
                text: title,
                align: 'center'
            }
        });
    
        updateTotalRevenue(data);
    }

    window.filterByDateRange = function() {
        var startDate = $("#startDate").val();
        var endDate = $("#endDate").val();
        var filteredData = dailyData.filter(item => item.date >= startDate && item.date <= endDate);

        chart.updateOptions({
            series: [{
                name: 'Revenue',
                data: filteredData.map(item => parseFloat(item.total).toFixed(2))
            }],
            xaxis: {
                categories: filteredData.map(item => item.date)
            },
            title: {
                text: 'Daily Revenue (Filtered)',
                align: 'center'
            }
        });

        updateTotalRevenue(filteredData);
    }

    // Initialize with daily data
    updateTotalRevenue(dailyData);

    const ctx = document.getElementById('pageChart').getContext('2d');

// Debugging: Log data passed to the chart
console.log('Page Visits:', @json($pageVisits));

const urls = @json($pageVisits->pluck('path'));
const truncatedUrls = urls.map(url => url.length > 400 ? url.substring(0, 400) + '...' : url);

// Debugging: Log processed URLs
console.log('URLs:', urls);
console.log('Truncated URLs:', truncatedUrls);

const data = {
    labels: truncatedUrls,
    datasets: [{
        label: 'Total Visits',
        data: @json($pageVisits->pluck('views')),
        backgroundColor: 'rgba(75, 192, 192, 0.2)',
        borderColor: 'rgba(75, 192, 192, 1)',
        borderWidth: 1,
        yAxisID: 'y-visits',
    }, {
        label: 'Average Stay Duration (minutes)',
        data: @json($pageVisits->pluck('avg_stay_duration')->map(fn($duration) => round($duration, 2))),
        backgroundColor: 'rgba(153, 102, 255, 0.2)',
        borderColor: 'rgba(153, 102, 255, 1)',
        borderWidth: 1,
        yAxisID: 'y-duration',
    }]
};

// Debugging: Log data object
console.log('Chart Data:', data);

const config = {
    type: 'bar',
    data: data,
    options: {
        scales: {
            'y-visits': {
                type: 'linear',
                position: 'left',
            },
            'y-duration': {
                type: 'linear',
                position: 'right',
                grid: {
                    drawOnChartArea: false,
                },
                ticks: {
                    callback: function(value) {
                        const hours = Math.floor(value / 60);
                        const minutes = value % 60;
                        return `${hours}h ${minutes}m`;
                    }
                }
            }
        },
        tooltips: {
            callbacks: {
                title: function(tooltipItems, data) {
                    const index = tooltipItems[0].index;
                    return urls[index];
                },
                label: function(tooltipItem, data) {
                    const datasetLabel = data.datasets[tooltipItem.datasetIndex].label || '';
                    if (datasetLabel === 'Average Stay Duration (minutes)') {
                        const value = tooltipItem.yLabel;
                        const hours = Math.floor(value / 60);
                        const minutes = value % 60;
                        return `${datasetLabel}: ${hours}h ${minutes}m`;
                    }
                    return `${datasetLabel}: ${tooltipItem.yLabel}`;
                }
            }
        }
    }
};

new Chart(ctx, config);
});
    </script>
@endpush
