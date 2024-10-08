<?php

use App\Http\Controllers\AdsController;
use App\Http\Controllers\ExcludedIpController;
use App\Http\Controllers\KlaviyoController;
use App\Http\Controllers\SalesController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/


Auth::routes();
Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');


Route::get('/', function () {
    return view('home');
});


Route::middleware(['auth'])->group(function () {
    Route::get('sales', [SalesController::class, 'index'])->name('sales');
    Route::post('/upload-sales-data', [SalesController::class, 'uploadSalesData'])->name('upload-sales-data');
    Route::get('/dashboard', [SalesController::class, 'rev'])->name('dashboard');
    Route::get('/journey', [SalesController::class, 'showJourney'])->name('journey');
    Route::resource('excludedips', ExcludedIpController::class);
    Route::get('excludedips/check', [ExcludedIpController::class, 'isExcluded']);
    Route::get('/user-events', [SalesController::class, 'getUserEventsData']);
    Route::get('/sales/journey/{user_id}', [SalesController::class, 'getUserJourney'])->name('sales.journey');
    // Route::get('/klaviyo/profile', [KlaviyoController::class, 'getProfile']);
    // Route::get('/ads/performance/{adAccountId}', [AdsController::class, 'showAdPerformance']);

    Route::get('/test-klaviyo', [KlaviyoController::class, 'getProfile']);

    // Route::get('/user-journeys', [SalesController::class, 'getUserJourneys']);
    Route::get('/journey-map', [SalesController::class, 'getJourneyMap']);
    Route::get('/metrics', [SalesController::class, 'getMetrics']);
    // Route::get('/segments', [SalesController::class, 'getSegments']);

});