<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;

class GmailWebhookController extends Controller
{
    public function handleWebhook(Request $request)
{
    Log::info('Gmail Webhook Payload:', $request->all());

    // historyId or other info
    $historyId = $request->input('historyId');

    // Call the command that processes "to:info@mycolean.com"
    \Artisan::call('gmail:process-mycolean');

    return response()->json(['status' => 'ok']);
}
}
