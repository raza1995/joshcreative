<?php

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
Route::post('/webhook/event', [WebhookController::class, 'handle']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
