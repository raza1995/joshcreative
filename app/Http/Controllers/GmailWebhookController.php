<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;

class GmailWebhookController extends Controller
{
    public function handleWebhook(Request $request)
    {
        Log::info('Gmail Webhook Received:', $request->all());

        $historyId = $request->input('historyId');

        if ($historyId) {
            // Process new emails asynchronously
            Artisan::call('gmail:process-emails', ['historyId' => $historyId]);
        }

        return response()->json(['status' => 'success']);
    }
}
