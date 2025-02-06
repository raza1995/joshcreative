<?php

namespace App\Http\Controllers;

use App\Services\OpenAIService;
use App\Services\SlackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SlackController extends Controller
{
    protected $slackService;
    protected $openAIService;

    public function __construct(SlackService $slackService, OpenAIService $openAIService)
    {
        $this->slackService = $slackService;
        $this->openAIService = $openAIService;
    }

    /**
     * Generate AI Reply based on Customer Query
     */
    public function generateAIReply(Request $request)
    {
        $request->validate([
            'customer_query' => 'required|string|max:1000',
        ]);

        $customerQuery = $request->input('customer_query');
        $context = "Order Status: Delivered on time. No return policy for opened items.";

        try {
            $aiReply = $this->openAIService->generateReply($customerQuery, $context);
            $this->slackService->sendMessageTo("🤖 AI Reply:\n$aiReply");

            return response()->json(['reply' => $aiReply], 200);
        } catch (\Exception $e) {
            Log::error("AI Reply Generation Failed: " . $e->getMessage());
            return response()->json(['error' => 'Failed to generate AI reply'], 500);
        }
    }

    /**
     * Send a Test Message to Slack
     */
    public function sendTestMessage(Request $request)
    {
        $message = $request->input('message', 'Hello from Mycolean AI Assistant!');
        
        try {
            $this->slackService->sendMessage($message);
            return response()->json(['status' => 'Message sent'], 200);
        } catch (\Exception $e) {
            Log::error("Slack Message Failed: " . $e->getMessage());
            return response()->json(['error' => 'Failed to send message'], 500);
        }
    }

    /**
     * Handle Slack OAuth Callback
     */
    public function handleOAuthCallback(Request $request)
    {
        $code = $request->input('code');

        try {
            $response = Http::asForm()->post('https://slack.com/api/oauth.v2.access', [
                'client_id'     => env('SLACK_CLIENT_ID'),
                'client_secret' => env('SLACK_CLIENT_SECRET'),
                'code'          => $code,
                'redirect_uri'  => route('slack.oauth.callback'),
            ]);

            $data = $response->json();

            if (isset($data['access_token'])) {
                return redirect('/dashboard')->with('success', 'Slack Connected Successfully!');
            } else {
                Log::error("Slack OAuth Failed: " . json_encode($data));
                return redirect('/')->with('error', 'Slack Authorization Failed!');
            }
        } catch (\Exception $e) {
            Log::error("Slack OAuth Exception: " . $e->getMessage());
            return redirect('/')->with('error', 'An error occurred during Slack authorization.');
        }
    }

    /**
     * Handle Incoming Slack Events
     */
    public function handleSlackEvent(Request $request)
{
    $payload = $request->all();

    // ✅ 1) Handle Slack URL Verification Challenge
    if (isset($payload['type']) && $payload['type'] === 'url_verification') {
        return response($payload['challenge'], 200)
                ->header('Content-Type', 'text/plain');
    }

    // ✅ 2) Send Immediate Acknowledgment to Slack
    response()->json(['status' => 'ok'])->send();  // Send 200 OK immediately

    // Continue processing the event in the background
    if (isset($payload['type']) && $payload['type'] === 'event_callback') {
        $event = $payload['event'] ?? [];

        if (
            isset($event['type']) &&
            $event['type'] === 'message' &&
            !isset($event['bot_id']) // ✅ Prevent bot reply loops
        ) {
            $channelId = $event['channel'];
            $messageText = $event['text'];

            // Process asynchronously
            dispatch(function () use ($messageText, $channelId) {
                $aiReply = app(OpenAIService::class)->generateReply($messageText, "Context here...");
                app(SlackService::class)->sendMessageToChannel($aiReply, $channelId);
            });
        }
    }

    // Slack already received a response, no need to return again
    exit;
}

    
}
