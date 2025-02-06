<?php
namespace App\Services;

use App\Models\ShopifyOrder;
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
    private function getOrderDetails($orderNumber)
    {
        return ShopifyOrder::where('order_number', $orderNumber)->first();
    }
    public function generateReply($customerQuery, $context = '')
{
    try {
        // Extract potential order number (assuming it's numeric)
        preg_match('/\d+/', $customerQuery, $matches);
        $orderNumber = $matches[0] ?? null;

        // Fetch order details if an order number is found
        $orderDetails = $orderNumber ? $this->getOrderDetails($orderNumber) : null;

        // Build order context if available
        $orderContext = '';
        if ($orderDetails) {
            $orderContext = "Here are the order details:
            - 📦 **Order Number:** {$orderDetails->order_number}
            - 🙋‍♂️ **Customer Name:** {$orderDetails->customer_name}
            - 🛒 **Product Name:** {$orderDetails->product_name}
            - 🔢 **Number of Items:** {$orderDetails->number_of_items}
            - 💰 **Paid Amount:** {$orderDetails->paid_amount}
            - 🎁 **Discount:** {$orderDetails->discount}
            - 🎟️ **Coupon Used:** {$orderDetails->coupon}
            - 🚚 **Tracking Number:** {$orderDetails->tracking_number}
            - 🔗 **Tracking URL:** {$orderDetails->tracking_url}
            - 📅 **Order Date:** {$orderDetails->order_date}";
        }

        // AI Prompt
        $prompt = "You are a friendly, knowledgeable customer support assistant. 
                   Respond in a warm, conversational way, like you're chatting with a friend. 
                   Be empathetic, clear, and solution-oriented, without encouraging product returns.

                   When asked about an order, provide updates clearly. Use friendly language, emojis when appropriate, and avoid sounding too formal.

                   ${context}
                   ${orderContext}

                   **Customer's Question:** \"$customerQuery\"
                   **Your Friendly Reply:**";

        $response = Http::withToken($this->apiKey)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'n' => 1,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a friendly customer support assistant. 
                                  Respond in a casual, helpful, and engaging way. 
                                  Be empathetic and solution-oriented, without encouraging returns.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.85, // Slightly more natural, human-like responses
                'max_tokens' => 200,
            ]);

        if ($response->successful()) {
            return $response->json()['choices'][0]['message']['content'] ?? 'Hmm, I’m not sure, but I’m here to help!';
        } else {
            Log::error('OpenAI API Error: ' . $response->body());
            return 'Oops, something went wrong. Could you try again?';
        }
    } catch (\Exception $e) {
        Log::error('Exception in OpenAIService: ' . $e->getMessage());
        return 'Oh no! I hit a snag. Mind trying again?';
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
