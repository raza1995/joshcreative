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

        if (!$historyId) {
            Log::error("Webhook received without a historyId.");
            return response()->json(['error' => 'No historyId provided'], 400);
        }

        // Trigger the Laravel command with historyId
        Artisan::call('gmail:process-mycolean', ['historyId' => $historyId]);

        return response()->json(['status' => 'success']);
    }
}
