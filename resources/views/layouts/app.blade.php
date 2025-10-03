<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'JoshCreative') }}</title>

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito" rel="stylesheet">
    <link href="{{asset('assets/css/jquery-ui.min.css')}}" rel="stylesheet">
    <link href="{{asset('assets/plugins/global/plugins.bundle.css')}}" rel="stylesheet">
    <link href="{{asset('assets/css/style.bundle.css')}}" rel="stylesheet">
    <link href="{{asset('assets/css/custom.css')}}" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
    <!-- Quiz Navigation Styles -->
    <style>
        .quiz-nav-active {
            background-color: #e7f3ff !important;
            color: #0066cc !important;
            font-weight: 600;
        }
        .dropdown-item:hover {
            background-color: #f8f9fa;
        }
        .dropdown-item i {
            width: 20px;
            margin-right: 8px;
        }
        
        /* Ensure dropdown menu displays properly */
        .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            z-index: 1000;
            min-width: 200px;
            padding: 0.5rem 0;
            margin: 0;
            font-size: 0.875rem;
            color: #212529;
            text-align: left;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid rgba(0,0,0,.15);
            border-radius: 0.375rem;
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,.175);
        }
        
        .dropdown-menu.show {
            display: block;
            animation: dropdownFadeIn 0.2s ease-in-out;
        }
        
        @keyframes dropdownFadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .dropdown-toggle::after {
            display: inline-block;
            margin-left: 0.255em;
            vertical-align: 0.255em;
            content: "";
            border-top: 0.3em solid;
            border-right: 0.3em solid transparent;
            border-bottom: 0;
            border-left: 0.3em solid transparent;
        }
        
        .nav-item.dropdown {
            position: relative;
        }
        
        /* Global Text Readability Improvements */
        .text-white h1, .text-white h2, .text-white h3, .text-white h4, .text-white h5, .text-white h6 {
            color: white !important;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
            font-weight: 600;
        }
        
        .text-white p, .text-white small, .text-white span {
            color: rgba(255,255,255,0.95) !important;
            text-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }
        
        .text-white i {
            color: rgba(255,255,255,0.9) !important;
            text-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }
        
        /* Card header improvements */
        .card-header.text-white, .card-header.bg-gradient {
            color: white !important;
            display: flex !important;
            align-items: center !important;
            min-height: 50px !important;
            padding: 12px 20px !important;
        }
        
        .card-header.text-white h5, .card-header.bg-gradient h5,
        .card-header.text-white h6, .card-header.bg-gradient h6 {
            color: white !important;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
            font-weight: 600 !important;
            margin: 0 !important;
            display: flex !important;
            align-items: center !important;
            line-height: 1.2 !important;
            flex: 1 !important;
        }
        
        .card-header.text-white i, .card-header.bg-gradient i {
            color: rgba(255,255,255,0.95) !important;
            text-shadow: 0 1px 2px rgba(0,0,0,0.2);
            margin-right: 8px !important;
            flex-shrink: 0 !important;
            font-size: 16px !important;
        }
        
        .card-header.text-white small, .card-header.bg-gradient small {
            display: block !important;
            margin-top: 2px !important;
            opacity: 0.8 !important;
            font-size: 12px !important;
            line-height: 1.1 !important;
        }
        
        /* General card header alignment */
        .card-header {
            display: flex !important;
            align-items: center !important;
            min-height: 50px !important;
            padding: 12px 20px !important;
        }
        
        .card-header h5, .card-header h6 {
            margin: 0 !important;
            display: flex !important;
            align-items: center !important;
            line-height: 1.2 !important;
            flex: 1 !important;
            font-weight: 600 !important;
        }
        
        /* Badge improvements */
        .badge {
            font-weight: 600;
            text-shadow: none;
        }
        
        /* Ensure proper contrast on colored backgrounds */
        .bg-primary, .bg-success, .bg-info, .bg-warning, .bg-danger, .bg-dark {
            color: white !important;
        }
        
        .bg-primary *, .bg-success *, .bg-info *, .bg-warning *, .bg-danger *, .bg-dark * {
            color: white !important;
            text-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }
    </style>
    <!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">

    <script src="{{ asset('js/jquery/jquery.min.js') }}"></script>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">


    @vite('resources/js/app.js')
    <!-- Scripts -->
 
</head>
<body>
    <div id="app">
        <nav class="navbar navbar-expand-md navbar-light bg-white shadow-sm">
            <div class="container">
                <a class="navbar-brand" href="{{ url('/') }}">
                    {{ config('app.name', 'JoshCreative') }}
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <!-- Left Side Of Navbar -->
                    <ul class="navbar-nav me-auto">

                    </ul>

                    <!-- Right Side Of Navbar -->
                    <ul class="navbar-nav ms-auto">
                        <!-- Authentication Links -->
                        @guest
                            @if (Route::has('login'))
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('login') }}">{{ __('Login') }}</a>
                                </li>
                            @endif

                            @if (Route::has('register'))
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('register') }}">{{ __('Register') }}</a>
                                </li>
                            @endif
                           
                        @else
                     
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
                        </li>

                        <!-- Quiz Dropdown Menu -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="quizDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-clipboard-list"></i> Quiz System
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="quizDropdown">
                                <li><a class="dropdown-item" href="{{ route('quiz.dashboard') }}">
                                    <i class="fas fa-chart-pie"></i> Quiz Dashboard
                                </a></li>
                                <li><a class="dropdown-item" href="{{ route('quiz.sessions') }}">
                                    <i class="fas fa-list"></i> All Sessions
                                </a></li>
                                <li><a class="dropdown-item" href="{{ route('quiz.analytics') }}">
                                    <i class="fas fa-chart-bar"></i> Detailed Analytics
                                </a></li>
                                <li><a class="dropdown-item" href="{{ route('quiz.ai-analytics') }}">
                                    <i class="fas fa-brain"></i> AI Marketing Analytics
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="{{ route('quiz.sessions') }}?completed=false">
                                    <i class="fas fa-clock text-warning"></i> Incomplete Sessions
                                </a></li>
                                <li><a class="dropdown-item" href="{{ route('quiz.sessions') }}?risk_level=dependence">
                                    <i class="fas fa-exclamation-triangle text-danger"></i> High Risk Results
                                </a></li>
                                <li><a class="dropdown-item" href="{{ route('quiz.sessions') }}?risk_level=higher">
                                    <i class="fas fa-exclamation-circle text-warning"></i> Higher Risk Results
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="{{ route('quiz.export') }}" target="_blank">
                                    <i class="fas fa-download text-success"></i> Export All Data
                                </a></li>
                            </ul>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('sales') }}">{{ __('Sales') }}</a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('journey') }}">{{ __('Analytics') }}</a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('email-draft.index') }}">{{ __('Email Drafts') }}</a>
                        </li>


                        
                        <li class="nav-item">
                            <a class="nav-link" href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                Logout
                            </a>
                        </li> 
                        <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                            @csrf
                        </form>
                        @endguest
                    </ul>
                    @auth
                        <div id="navbarDropdown" class="badge bg-success px-3 mx-4 fs-7" href="#" role="button" >
                            {{ strtoupper(Auth::user()->name ?? '') }}
                        </div>
                    @endauth
                </div>
            </div>
        </nav>

        <main class="py-4">
            @yield('content')
        </main> 
    </div>
    @include('layouts.includes.footer')
    
    <!-- Bootstrap JS for Dropdown Functionality -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Quiz Navigation Enhancement -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Try Bootstrap first, fallback to manual implementation
        try {
            // Ensure Bootstrap dropdowns work
            if (typeof bootstrap !== 'undefined') {
                var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
                var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
                    return new bootstrap.Dropdown(dropdownToggleEl);
                });
            } else {
                // Manual dropdown implementation
                initManualDropdown();
            }
        } catch (error) {
            console.log('Bootstrap not available, using manual dropdown');
            initManualDropdown();
        }
        
        function initManualDropdown() {
            const quizDropdown = document.getElementById('quizDropdown');
            const dropdownMenu = quizDropdown ? quizDropdown.nextElementSibling : null;
            
            console.log('Quiz dropdown element:', quizDropdown);
            console.log('Dropdown menu element:', dropdownMenu);
            
            if (quizDropdown && dropdownMenu) {
                quizDropdown.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Close other dropdowns
                    document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                        if (menu !== dropdownMenu) {
                            menu.classList.remove('show');
                        }
                    });
                    
                    // Toggle current dropdown
                    dropdownMenu.classList.toggle('show');
                });
                
                // Close dropdown when clicking outside
                document.addEventListener('click', function(e) {
                    if (!quizDropdown.contains(e.target) && !dropdownMenu.contains(e.target)) {
                        dropdownMenu.classList.remove('show');
                    }
                });
            }
        }
    });
    </script>
</body>
</html>
