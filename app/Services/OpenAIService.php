<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIService
{
    protected $apiKey;
    protected $model;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->model = config('services.openai.model', 'gpt-4');
    }

    public function generateReply($customerQuery, $context = '')
{
    try {
        $prompt = "Keep it short and conversational. Avoid sounding like an email. 
                   Be friendly, helpful, and empathetic without encouraging product returns. 

                   Context: $context
                   Customer Query: \"$customerQuery\"";

        $response = Http::withToken($this->apiKey)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a friendly customer support assistant. 
                                      Respond in a casual and conversational way, like a chat. 
                                      Be helpful and solution-oriented without encouraging returns.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.8,
                'max_tokens' => 180, // Shorter responses to avoid lengthy, email-like replies
            ]);

        if ($response->successful()) {
            return $response->json()['choices'][0]['message']['content'] ?? 'Not sure, but happy to help!';
        } else {
            Log::error('OpenAI API Error: ' . $response->body());
            return 'Hmm, something went wrong. Can you try again?';
        }
    } catch (\Exception $e) {
        Log::error('Exception in OpenAIService: ' . $e->getMessage());
        return 'Oops, I had a little hiccup. Try again?';
    }
}


    public function generateSummary($emailContent)
{
    try {
        $prompt = "Summarize the following customer email, focusing on the main issue or concern in a professional tone without unnecessary details.

        Email Content:
        \"$emailContent\"";

        $response = Http::withToken($this->apiKey)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an expert in summarizing customer service emails concisely.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.5,
                'max_tokens' => 150,
            ]);

        if ($response->successful()) {
            return $response->json()['choices'][0]['message']['content'] ?? 'Summary not available.';
        } else {
            Log::error('OpenAI API Summary Error: ' . $response->body());
            return 'Error generating summary.';
        }
    } catch (\Exception $e) {
        Log::error('Exception in OpenAIService (Summary): ' . $e->getMessage());
        return 'Error communicating with AI for summary.';
    }
}

}
