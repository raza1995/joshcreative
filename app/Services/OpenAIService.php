<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\ShopifyOrder;

class OpenAIService
{
    protected $apiKey;
    protected $model;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->model = 'gpt-3.5-turbo'; // Using GPT-3.5 Turbo for faster, cost-effective responses
    }

    // Fetch order by number
    private function getOrderByOrderNumber($orderNumber)
    {
        return ShopifyOrder::where('order_number', $orderNumber)->first();
    }

    // Fetch orders by email
    private function getOrdersByEmail($email)
    {
        return ShopifyOrder::where('email_address', $email)->get();
    }

    // Format order details compactly
    private function formatOrderDetails($orders)
    {
        $formattedDetails = '';

        foreach ($orders as $order) {
            $formattedDetails .= "
Order #{$order->order_number} | {$order->product_name} ({$order->number_of_items} items)
- Name: {$order->customer_name}
- Paid: {$order->paid_amount} (Disc: {$order->discount}, Coupon: {$order->coupon})
- Tracking: {$order->tracking_number} | [Track]({$order->tracking_url})
- Date: {$order->order_date}\n\n";
        }

        return $formattedDetails ?: "No orders found.";
    }

    // Simulate learning by caching context
    private function getCachedContext($key)
    {
        return Cache::get($key, '');
    }

    private function setCachedContext($key, $value)
    {
        Cache::put($key, $value, now()->addHours(6)); // Cache for 6 hours
    }

    public function generateReply($customerQuery, $context = '')
    {
        try {
            preg_match('/\d+/', $customerQuery, $orderMatches);
            preg_match('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,4}\b/', $customerQuery, $emailMatches);

            $orderNumber = $orderMatches[0] ?? null;
            $email = $emailMatches[0] ?? null;

            // Fetch order data
            $orders = collect();
            if ($email) {
                $orders = $this->getOrdersByEmail($email);
            } elseif ($orderNumber) {
                $order = $this->getOrderByOrderNumber($orderNumber);
                if ($order) {
                    $orders = collect([$order]);
                }
            }

            // Retrieve cached context
            $cacheKey = $email ?: ($orderNumber ? "order_{$orderNumber}" : 'general');
            $previousContext = $this->getCachedContext($cacheKey);

            // Format orders concisely
            $orderContext = $this->formatOrderDetails($orders);

            // AI Prompt
            $prompt = "You are a professional customer support assistant. 
                       Respond concisely, using bullet points for clarity. 
                       Be empathetic and helpful, without suggesting returns.

                       Previous Info: {$previousContext}
                       Order Info: {$orderContext}

                       Customer Query: \"$customerQuery\"";

            $response = Http::withToken($this->apiKey)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $this->model,
                    'n' => 1,
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a helpful customer support assistant providing concise, clear responses.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.6, // Slightly reduced for consistency
                    'max_tokens' => 150,   // Limit tokens to reduce costs
                ]);

            if ($response->successful()) {
                $reply = $response->json()['choices'][0]['message']['content'] ?? 'Hmm, I’m not sure, but I’m here to help!';

                // Update cached context for "learning"
                $this->setCachedContext($cacheKey, "{$previousContext}\n{$customerQuery}: {$reply}");

                return $reply;
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
