<?php

use App\Http\Controllers\AdsController;
use App\Http\Controllers\EmailDraftController;
use App\Http\Controllers\ExcludedIpController;
use App\Http\Controllers\GmailWebhookController;
use App\Http\Controllers\KlaviyoController;
use App\Http\Controllers\ManualReviewController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\ShopifyOrderController;
use App\Http\Controllers\SlackController;
use App\Services\GmailService;
use App\Services\ShopifyService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShopifyWebhookController;
use App\Services\GmailShopifyInvoiceService;

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
Route::get('/gmail/callback', [GmailService::class, 'handleOAuthCallback']);
Route::get('/gmail/watch', [GmailService::class, 'startGmailWatch']);

Route::post('/gmail/webhook', [GmailWebhookController::class, 'handle']);
Route::get('/slack/oauth/callback', [SlackController::class, 'handleOAuthCallback']);
Route::post('/slack/events', [SlackController::class, 'handleSlackEvent']);


// Reviews
Route::post('/reviews/approve/{draftId}', [ReviewController::class, 'approve']);
Route::post('/reviews/disapprove/{draftId}', [ReviewController::class, 'disapprove']);
Route::get('/reviews', [ReviewController::class, 'index']);

// Manual Reviews
Route::post('/manual-reviews/assign', [ManualReviewController::class, 'assign']);
Route::post('/manual-reviews/resolve/{id}', [ManualReviewController::class, 'resolve']);
Route::get('/manual-reviews', [ManualReviewController::class, 'index']);
// routes/web.php
Route::middleware(['public.urls'])->group(function () {
    Route::get('/slack/oauth/callback', [SlackController::class, 'handleOAuthCallback'])->name('slack.oauth.callback');
    Route::post('/slack/webhook', [SlackController::class, 'handleWebhook']);
    Route::post('/slack/ai-reply', [SlackController::class, 'generateAIReply']);
    
Route::post('/slack/send-test', [SlackController::class, 'sendTestMessage']);



});

Route::get('/test-email-processing', function (GmailShopifyInvoiceService $service) {
    $service->processLabeledEmails();
    return response()->json(['message' => 'Email processing triggered. Check logs.']);
});
Route::post('/shopify/webhook/fulfillment', [ShopifyWebhookController::class, 'handleFulfillmentUpdate'])
    ->name('shopify.webhook.fulfillment');

Route::post('/shopify/webhook/orders', [ShopifyWebhookController::class, 'handleOrderWebhook'])
    ->name('shopify.webhook.orders');

    Route::post('/shopify/webhook/orders/mycolean', [ShopifyWebhookController::class, 'handleOrderWebhook'])
    ->name('shopify.webhook.orders');
    Route::get('/shopify/register-webhook', function () {
     
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
    Route::get('/fetch-shopify-orders', [ShopifyOrderController::class, 'fetchOrders']);


    Route::get('/email-draft', [EmailDraftController::class, 'index'])->name('email-draft.index');
    Route::post('/email-draft/data', [EmailDraftController::class, 'getData'])->name('email-draft.data');
    Route::post('/email-draft/approve/{id}', [EmailDraftController::class, 'approveDraft'])->name('email-draft.approve');
    Route::get('/email-draft/{id}/edit', [EmailDraftController::class, 'edit'])->name('email-draft.edit');
Route::post('/email-draft/{id}/update', [EmailDraftController::class, 'update'])->name('email-draft.update');
Route::post('/email-draft/send-bulk', [EmailDraftController::class, 'sendBulkEmails'])->name('email-draft.send.bulk');
Route::post('/email-draft/send/{id}', [EmailDraftController::class, 'sendEmail'])->name('email-draft.send');
Route::post('/email-draft/bulk-disapprove', [EmailDraftController::class, 'bulkDisapprove'])->name('email-draft.bulk-disapprove');
Route::post('/email-draft/disapprove/{id}', [EmailDraftController::class, 'disapproveDraft'])->name('email-draft.disapprove');
Route::post('/email-draft/bulk-approve', [EmailDraftController::class, 'bulkApprove']);
Route::get('/email-draft/create', [EmailDraftController::class, 'create'])->name('email-draft.create');
Route::post('/email-draft/store', [EmailDraftController::class, 'store'])->name('email-draft.store');
Route::get('/shopify-orders', [EmailDraftController::class, 'getShopifyOrders'])->name('shopify.orders');

});


