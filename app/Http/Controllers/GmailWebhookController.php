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
    // public function handleWebhook(Request $request)
    // {
    //     Log::info('Gmail Webhook Received:', $request->all());

    //     $historyId = $request->input('historyId');

    //     if (!$historyId) {
    //         Log::error("Webhook received without a historyId.");
    //         return response()->json(['error' => 'No historyId provided'], 400);
    //     }

    //     // Trigger the Laravel command with historyId
    //     Artisan::call('gmail:process-mycolean', ['historyId' => $historyId]);

    //     return response()->json(['status' => 'success']);
    // }

    public function handle(Request $request)
    {
        \Log::info('Gmail Webhook Request:', $request->all());
        $historyId = $request->historyId;
        \Log::info("Received Gmail webhook with historyId: $historyId");

        // Fetch new emails since the last history ID
        $emails = app(GmailService::class)->getMessagesFromHistory($historyId);
        \Log::info("Fetched " . count($emails) . " new emails from historyId: $historyId");

        foreach ($emails as $email) {
            $headers = $email->getPayload()->getHeaders();
            $from = collect($headers)->firstWhere('name', 'From')->getValue() ?? 'Unknown';

            // Send notification to AI Bot (or Slack)
            app(SlackService::class)->sendMessage("📩 Email from: $from");
            \Log::info("Sent Slack notification for email from: $from");
        }

        \Log::info("Completed processing of emails for historyId: $historyId");
        return response()->json(['status' => 'success']);
    }

}
