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
        $this->apiKey = env('OPENAI_API_KEY'); // e.g. "sk-..."
        $this->model  = env('OPENAI_MODEL', 'gpt-3.5-turbo');
    }

    /**
     * Generate a reply for a customer service context.
     *
     * @param  array   $conversation   Array of conversation messages,
     *                                 e.g. [
     *                                      ['role' => 'user', 'content' => 'Previous email from customer...'],
     *                                      ['role' => 'assistant', 'content' => 'Your last AI reply...'],
     *                                      ['role' => 'user', 'content' => 'New question from customer...'],
     *                                 ]
     * @param  string  $context        Optional extra context (like policy info).
     *
     * @return string
     */
    public function generateReply($conversation, string $context = '')
    {
        try {
            // Start with a System message that defines the AI's "persona" or instructions
            $messages = [
                [
                    'role' => 'system',
                    'content' => "You are a helpful, empathetic customer service assistant for a company. 
                                  You do not encourage product returns, but remain polite, empathetic, and solution-oriented."
                ],
            ];

            // Optionally add more system-level context if desired
            if (!empty($context)) {
                // You could treat this as a system message or user message, depending on how you want the AI to interpret it
                $messages[] = [
                    'role' => 'system',
                    'content' => "Additional context: $context",
                ];
            }

            // Now add the conversation history to the "messages" array
            // Each item should be: ['role' => 'user'/'assistant'/'system', 'content' => '...']
            foreach ($conversation as $entry) {
                // Make sure role is one of: user / assistant / system
                // Typically you'll only use 'user' and 'assistant' here
                $role = $entry['role'] ?? 'user'; 
                $content = $entry['content'] ?? '';
                $messages[] = [
                    'role' => $role,
                    'content' => $content,
                ];
            }

            // Make the API request to the Chat Completion endpoint
            $response = Http::withToken($this->apiKey)->post('https://api.openai.com/v1/chat/completions', [
                'model'       => $this->model,
                'messages'    => $messages,
                'temperature' => 0.7,
                'max_tokens'  => 250,
            ]);

            if ($response->successful()) {
                // Get the content of the assistant's reply
                return $response->json()['choices'][0]['message']['content'] ?? 'No reply generated.';
            } else {
                Log::error('OpenAI API Error: ' . $response->body());
                return 'Error generating reply.';
            }
        } catch (\Exception $e) {
            Log::error('Exception in OpenAIService: ' . $e->getMessage());
            return 'Error communicating with AI.';
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
