<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/


Route::any('/webhook', [SalesController::class, 'salesDataWebHook'])->name('webhook');
Route::post('/teachablewebhook', [SalesController::class, 'teachableHandleWebhook'])->name('teachablewebhook');
// Route::post('/webhook/event', [WebhookController::class, 'handle']);
Route::post('/webhook/event', [AnalyticsController::class, 'track']);
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Mycolean Sober Tracker API
use App\Http\Controllers\MycoleanController;

Route::prefix('mycolean')->group(function () {
    Route::get('/month', [MycoleanController::class, 'getMonth']);
    Route::post('/mark-today', [MycoleanController::class, 'markToday']);
    Route::post('/toggle-day', [MycoleanController::class, 'toggleDay']);
    Route::put('/sync-month', [MycoleanController::class, 'syncMonth']);
});

// Mycolean Quiz API
use App\Http\Controllers\QuizController;

Route::prefix('quiz')->group(function () {
    // Session management
    Route::post('/start', [QuizController::class, 'startSession']);
    Route::get('/session/{sessionUuid}', [QuizController::class, 'getSession']);
    
    // Quiz data
    Route::get('/questions', [QuizController::class, 'getQuestions']);
    
    // Response handling
    Route::post('/update-email', [QuizController::class, 'updateEmail']);
    Route::post('/demographics', [QuizController::class, 'saveDemographics']);
    Route::post('/response', [QuizController::class, 'saveResponse']);
    Route::post('/complete', [QuizController::class, 'completeQuiz']);
    
    // Analytics
    Route::post('/track', [QuizController::class, 'trackEvent']);
    Route::get('/stats', [QuizController::class, 'getStats']);
});
