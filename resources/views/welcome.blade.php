@extends('layouts.app')

@section('content')
    <body class="antialiased">

        <div id="kt_app_content" class="app-content flex-column-fluid">
            <div id="kt_app_content_container" class="app-container container-xxl">
                <div class="row g-5 g-xl-10 mb-xl-10">
                    <div class="col-md-6 col-lg-6 col-xl-6 col-xxl-3 mb-md-5 mb-xl-10">
                        <h2>chart 1</h2>
                    </div>   
                    <div class="col-md-6 col-lg-6 col-xl-6 col-xxl-3 mb-md-5 mb-xl-10">
                        <h2>chart 2</h2>
                    </div>   
                    <div class="col-lg-12 col-xl-12 col-xxl-6 mb-5 mb-xl-0">
                        <div class="card card-bordered">
                            <div class="card-body">
                                <div id="kt_charts_widget_3_chart" style="height: 350px;"></div>
                            </div>
                        </div>
                    </div>   
                </div>
            </div>
        
        </div>

        @endsection

