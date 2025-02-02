<?php

namespace App\Http\Controllers;

use App\Models\ExcludedIp;
use App\Models\Pages;
use App\Models\UserEvent;
use App\Services\ShopifyService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShopifyOrderController extends Controller
{
    public function fetchOrders(Request $request, ShopifyService $shopifyService): JsonResponse
{
    $message = $shopifyService->fetchOrders(
        $request->input('from_date'),
        $request->input('to_date')
    );

    return response()->json(['message' => $message]);
}


}
