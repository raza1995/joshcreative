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
                            <h2>Current Month Revenue: $<span id="currentMonthRevenue">{{ number_format($currentMonthRevenue ?? '', 2) }}</span></h2>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <h2>Total Revenue: $<span id="totalRevenue">0.00</span></h2>
                        </div>
                    </div>
                    <div id="chart" style="height: 400px;"></div>
                </div>
            </div>
        </div>
        @endsection
    @push('scripts')
    <script>
   document.addEventListener('DOMContentLoaded', function () {
    $("#startDate").datepicker({ dateFormat: 'yy-mm-dd' });
    $("#endDate").datepicker({ dateFormat: 'yy-mm-dd' });

    var dailyData = @json($dailyRevenue);
    var monthlyData = @json($monthlyRevenue);
    var yearlyData = @json($yearlyRevenue);

    var options = {
        chart: {
            type: 'line',
            height: '400px'
        },
        series: [{
            name: 'Revenue',
            data: dailyData.map(item => item.total)
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
                data: data.map(item => item.total)
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
                data: filteredData.map(item => item.total)
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



});
    </script>
@endpush
