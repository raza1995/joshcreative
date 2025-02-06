<?php

namespace App\Http\Controllers;

use App\Services\OpenAIService;
use App\Services\SlackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SlackController extends Controller
{
    protected $slackService;
    protected $openAIService;

    public function __construct(SlackService $slackService, OpenAIService $openAIService)
    {
        $this->slackService = $slackService;
        $this->openAIService = $openAIService;
    }

    public function generateAIReply(Request $request)
    {
        $request->validate([
            'customer_query' => 'required|string|max:1000',
        ]);

        $customerQuery = $request->input('customer_query');

        // Optional: Add context like order status or customer info
        $context = "Order Status: Delivered on time. No return policy for opened items.";

        $aiReply = $this->openAIService->generateReply($customerQuery, $context);

        // Send AI-generated reply to Slack
        $this->slackService->sendMessage("🤖 AI Reply:\n$aiReply");

        return response()->json(['reply' => $aiReply]);
    }
    /**
     * Send a test message to Slack
     */
    public function sendTestMessage(Request $request)
    {
        $message = $request->input('message', 'Hello from Mycolean AI Assistant!');
        $this->slackService->sendMessage($message);

        return response()->json(['status' => 'Message sent']);
    }

    public function handleOAuthCallback(Request $request)
    {
        $code = $request->input('code');

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
            return redirect('/')->with('error', 'Slack Authorization Failed!');
        }
    }

    public function handleSlackEvent(Request $request)
    {

        $timestamp = $request->header('X-Slack-Request-Timestamp');
        $signature = $request->header('X-Slack-Signature');
        $body      = $request->getContent();
        $signingSecret = env('SLACK_SIGNING_SECRET'); // store in .env
    
        // Simple check to avoid replay attacks
        if (abs(time() - $timestamp) > 300) {
            return response()->json(['error' => 'Invalid request timestamp'], 400);
        }
    
        // Create the basestring for signing verification
        $basestring = 'v0:' . $timestamp . ':' . $body;
        $computedSignature = 'v0=' . hash_hmac('sha256', $basestring, $signingSecret);
    
        if (!hash_equals($computedSignature, $signature)) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }
        // Parse the incoming Slack payload
        $payload = $request->all();

        // 1) Handle Slack URL Verification Challenge
        //    Slack will send a 'challenge' parameter when you first add a Request URL
        if (isset($payload['type']) && $payload['type'] === 'url_verification') {
            return response($payload['challenge'], 200)
                    ->header('Content-Type', 'text/plain');
        }

        // 2) Optionally, verify Slack signature (security best practice)
        // see Slack docs: https://api.slack.com/authentication/verifying-requests-from-slack
        // We'll skip the details here for brevity (code snippet below).

        // 3) Handle "event_callback" type
        if (isset($payload['type']) && $payload['type'] === 'event_callback') {
            $event = $payload['event'] ?? [];

            // We only care about actual messages (not e.g. channel join events, etc.)
            // Also skip bot messages to avoid infinite loops
            if (
                isset($event['type']) && $event['type'] === 'message' &&
                !isset($event['bot_id']) // ensure it's not from a bot
            ) {
                $channelId = $event['channel'];
                $messageText = $event['text'];

                // (Optional) Add any context you want to pass to your AI
                $context = "Some context that might inform OpenAI's reply...";

                // 4) Generate a reply using your OpenAIService
                $aiReply = $this->openAIService->generateReply($messageText, $context);

                // 5) Respond in Slack by sending a message to the same channel
                $this->slackService->sendMessageToChannel($aiReply, $channelId);
            }
        }

        // Slack expects a 200 OK response even if we do nothing
        return response()->json(['status' => 'ok']);
    }
}
