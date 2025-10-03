<?php

use App\Http\Controllers\AdsController;
use App\Http\Controllers\AdvancedConversionController;
use App\Http\Controllers\ConversionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailDraftController;
use App\Http\Controllers\ExcludedIpController;
use App\Http\Controllers\FacebookAdCreativeSuggestionsController;
use App\Http\Controllers\FacebookAdsController;
use App\Http\Controllers\FacebookMetricsController;
use App\Http\Controllers\GmailWebhookController;
use App\Http\Controllers\GoogleSheetController;
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
use App\Http\Controllers\ShopifyWebhookControllerMycolean;
use App\Http\Controllers\KlaviyoKpiController;
use App\Services\GmailShopifyInvoiceService;
use App\Http\Controllers\AovReportController;
use App\Http\Controllers\KpiReportController;

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

Route::get('/facebook-ads', [FacebookAdsController::class, 'index']);
Route::get('/fb-fetch', [FacebookAdsController::class, 'queueCampaigns'])->name('fb.fetch');
Route::get('/facebook/fetch-ui', [FacebookAdsController::class, 'showFetchView'])->name('fb.fetch-ui');
Route::get('/facebook/job-status', [FacebookAdsController::class, 'checkStatus'])->name('fb.status');
Route::get('/facebook/list-files', [FacebookAdsController::class, 'listJsonFiles'])->name('fb.files');
Route::get('/facebook/merge-campaigns', [FacebookAdsController::class, 'mergeAllCampaignFiles'])->name('fb.merge');
Route::get('/facebook/ad-accounts', [FacebookAdsController::class, 'getAdAccounts'])->name('fb.accounts');
Route::get('/facebook-metrics', [FacebookMetricsController::class, 'filter']);
Route::get('/facebook-ads/data', [FacebookMetricsController::class, 'getData'])->name('facebook.ads.data');
Route::get('/facebook-ads', [FacebookMetricsController::class, 'index'])->name('facebook.ads.index');
Route::get('/facebook-ads/orders/{ad_id}', [FacebookMetricsController::class, 'showOrders'])->name('facebook.ad.orders');
Route::get('/facebook/multi-interval', [FacebookMetricsController::class, 'multiIntervalView'])->name('facebook.multi_interval.view');
Route::get('/facebook/multi-interval/data', [FacebookMetricsController::class, 'getMultiIntervalData'])->name('facebook.multi_interval.data');
Route::get('/facebook/trend-metrics', [FacebookMetricsController::class, 'showTrendMetrics'])->name('facebook.trend.metrics');
Route::get('/facebook/ads/{ad_id}/trend-metrics', [FacebookMetricsController::class, 'showAdTrendMetrics'])->name('facebook.ad.trend_metrics');
Route::get('/facebook/ads/{ad_id}/charts', [FacebookMetricsController::class, 'showAdCharts'])->name('facebook.ad.charts');


Route::prefix('analytics')->group(function () {

    Route::get('/creative-suggestions', [FacebookAdCreativeSuggestionsController::class, 'index'])->name('suggestions.index');
    Route::get('/creative-suggestions/data', [FacebookAdCreativeSuggestionsController::class, 'getData'])->name('suggestions.data');
    

    Route::get('/sources',  [AdvancedConversionController::class, 'sourcePerformance'])
        ->name('analytics.sources');

    Route::get('/products', [AdvancedConversionController::class, 'productBySource'])
        ->name('analytics.products');

    Route::get('/lag',      [AdvancedConversionController::class, 'conversionLag'])
        ->name('analytics.lag');

    Route::get('/landing-sites', [ConversionController::class, 'landingSiteConversions'])->name('analytics.landing_sites');

    Route::get('/funnel', [AdvancedConversionController::class, 'funnelFallout'])
     ->name('analytics.funnel');

});


Route::get('/google-sheet/export', [GoogleSheetController::class, 'showForm'])->name('sheet.form');
Route::post('/google-sheet/create', [GoogleSheetController::class, 'createSheet'])->name('sheet.create');
Route::post('/google-sheet/append', [GoogleSheetController::class, 'appendToSheet'])->name('sheet.append');

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

    Route::post('/shopify/webhook/orders/mycolean', [ShopifyWebhookControllerMycolean::class, 'handleOrderWebhook'])
    ->name('shopify.webhook.orders.mycolean');
    
    Route::get('/shopify/register-webhook', function () {
     
    });
Route::middleware(['auth'])->group(function () {
 // routes/web.php


Route::get('/shopify-orders-view', [ShopifyOrderController::class, 'index'])->name('shopify.index');        // Blade page
Route::get('/shopify-orders/data', [ShopifyOrderController::class, 'data'])->name('shopify.data');     // AJAX
Route::get('/shopify-orders/export', [ShopifyOrderController::class, 'exportCsv'])->name('shopify.exportCsv');


    Route::get('/dashboard/sales', [DashboardController::class, 'index']);
    Route::get('/dashboard/filter', [DashboardController::class, 'filter'])->name('dashboard.filter');
    Route::get('/dashboard/profit', [DashboardController::class, 'profit'])->name('dashboard.profit');

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


Route::get('/klaviyo/kpis', [KlaviyoKpiController::class, 'index'])->name('klaviyo.kpis');
Route::get('/klaviyo/options', [KlaviyoKpiController::class, 'options'])->name('klaviyo.options'); // lists campaigns & segments
Route::post('/klaviyo/kpis/campaigns', [KlaviyoKpiController::class, 'campaignKpis'])->name('klaviyo.kpis.campaigns');
Route::post('/klaviyo/kpis/segments', [KlaviyoKpiController::class, 'segmentKpis'])->name('klaviyo.kpis.segments');

});

// AOV Analytics (order_date-filtered, by utm_campaign)
Route::get('/analytics/aov', [AovReportController::class, 'index'])->name('analytics.aov');
Route::get('/analytics/aov/data', [AovReportController::class, 'data'])->name('analytics.aov.data');
Route::get('/analytics/kpi', [KpiReportController::class, 'index'])->name('analytics.kpi');
Route::get('/analytics/kpi/data', [KpiReportController::class, 'data'])->name('analytics.kpi.data');

// Quiz Dashboard Routes
use App\Http\Controllers\QuizDashboardController;

Route::middleware(['auth'])->prefix('quiz')->name('quiz.')->group(function () {
    Route::get('/dashboard', [QuizDashboardController::class, 'index'])->name('dashboard');
    Route::get('/sessions', [QuizDashboardController::class, 'sessions'])->name('sessions');
    Route::get('/session/{sessionUuid}', [QuizDashboardController::class, 'sessionDetail'])->name('session-detail');
    Route::get('/analytics', [QuizDashboardController::class, 'analytics'])->name('analytics');
    Route::get('/ai-analytics', [\App\Http\Controllers\AIAnalyticsController::class, 'index'])->name('ai-analytics');
    Route::get('/export', [QuizDashboardController::class, 'export'])->name('export');
});
