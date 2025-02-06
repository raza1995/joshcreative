<?php

namespace App\Http\Controllers;

use App\Services\SlackService;
use Illuminate\Http\Request;

class SlackController extends Controller
{
    protected $slackService;

    public function __construct(SlackService $slackService)
    {
        $this->slackService = $slackService;
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
