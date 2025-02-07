<?php
namespace App\Http\Controllers;

use App\Services\GmailService;
use App\Services\OpenAIService;
use App\Services\SlackService;
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

    public function handle(Request $request)
    {
        $historyId = $request->historyId;

        // Fetch new emails since the last history ID
        $emails = app(GmailService::class)->getMessagesFromHistory($historyId);

        foreach ($emails as $email) {
            $headers = $email->getPayload()->getHeaders();
            $from = collect($headers)->firstWhere('name', 'From')->getValue() ?? 'Unknown';

            // Send notification to AI Bot (or Slack)
            app(SlackService::class)->sendMessage("📩 Email from: $from");

            // Optional: Log the notification
            \Log::info("📩 New Email Notification: $from");
        }

        return response()->json(['status' => 'success']);
    }

}
