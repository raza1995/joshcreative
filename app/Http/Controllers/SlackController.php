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
}
