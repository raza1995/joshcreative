<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SlackService
{
    protected $botToken;
    protected $channelId;

    public function __construct()
    {
        $this->botToken = env('SLACK_BOT_TOKEN');
        $this->channelId = env('SLACK_CHANNEL_ID');
    }

    /**
     * Send a message to Slack
     */
    public function sendMessage($message)
{
    try {
        $response = Http::withToken($this->botToken)
            ->post('https://slack.com/api/chat.postMessage', [
                'channel' => $this->channelId,
                'text'    => $message,
            ]);

        // Log full response for debugging
        Log::info("Slack API Response:", [
            'status' => $response->status(),
            'msg' => $message,
            'channelId' => $this->channelId,
            'body'   => $response->json()
        ]);

        if ($response->successful() && $response->json()['ok']) {
            Log::info("✅ Message sent to Slack: $message");
            return true;
        }

        Log::error("🚨 Slack API Error: ", $response->json());
        return false;

    } catch (\Exception $e) {
        Log::error("🚨 Exception in SlackService: " . $e->getMessage());
        return false;
    }
}

public function sendMessageToChannel($message, $channelId)
{
    try {
        $response = Http::withToken($this->botToken)
            ->post('https://slack.com/api/chat.postMessage', [
                'channel' => $channelId,
                'text'    => $message,
            ]);

        Log::info("Slack API Response:", [
            'status' => $response->status(),
            'msg' => $message,
            'channelId' => $channelId,
            'body'   => $response->json()
        ]);

        if ($response->successful() && ($response->json()['ok'] ?? false)) {
            Log::info("✅ Message sent to Slack channel $channelId: $message");
            return true;
        }

        Log::error("🚨 Slack API Errorsssssssssssss: ", $response->json());
        return false;

    } catch (\Exception $e) {
        Log::error("🚨 Exception in SlackService: " . $e->getMessage());
        return false;
    }
}
public function sendMessageTo($message)
{
    $defaultChannelId = env('SLACK_CHANNEL_ID');
    return $this->sendMessageToChannel($message, $defaultChannelId);
}

}
