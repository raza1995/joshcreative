<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\ShopifyOrder;

class OpenAIService
{
    protected $apiKey;
    protected $model;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->model = config('services.openai.model', 'gpt-4');
    }

    // 1️⃣ Fetch Order by Order Number
    private function getOrderByOrderNumber($orderNumber)
    {
        return ShopifyOrder::where('order_number', $orderNumber)->first();
    }

    // 2️⃣ Fetch Orders by Email
    private function getOrdersByEmail($email)
    {
        return ShopifyOrder::where('email_address', $email)->get();
    }

    // 3️⃣ Format Order Details for Readability
    private function formatOrderDetails($orders)
    {
        $formattedDetails = '';

        foreach ($orders as $order) {
            $formattedDetails .= "
📦 **Order Number:** {$order->order_number}
👤 **Customer Name:** {$order->customer_name}
📧 **Email:** {$order->email_address}
🛒 **Product:** {$order->product_name}
🔢 **Items:** {$order->number_of_items}
💰 **Paid Amount:** {$order->paid_amount} (Discount: {$order->discount}, Coupon: {$order->coupon})
🚚 **Tracking Number:** {$order->tracking_number} | [Track Order]({$order->tracking_url})
📅 **Order Date:** {$order->order_date}

-----------------------------\n";
        }

        return $formattedDetails ?: "No orders found.";
    }

    // 4️⃣ Generate AI Reply
    public function generateReply($customerQuery, $context = '')
    {
        try {
            // Detect email or order number
            preg_match('/\d+/', $customerQuery, $orderMatches);
            preg_match('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,4}\b/', $customerQuery, $emailMatches);

            $orderNumber = $orderMatches[0] ?? null;
            $email = $emailMatches[0] ?? null;

            // Fetch order data
            $orders = collect(); // Empty collection as default

            if ($email) {
                $orders = $this->getOrdersByEmail($email);
            } elseif ($orderNumber) {
                $order = $this->getOrderByOrderNumber($orderNumber);
                if ($order) {
                    $orders = collect([$order]);
                }
            }

            // Format orders for AI
            $orderContext = $this->formatOrderDetails($orders);

            // AI Prompt
            $prompt = "You are a friendly, professional customer support assistant. 
                       Respond in a clear, structured, and friendly way without encouraging product returns. 
                       Use bullet points, bold headers, and clean formatting for order details. 

                       ${context}
                       **Order Information:** 
                       ${orderContext}

                       **Customer Query:** \"$customerQuery\"
                       **Your Reply:**";

            $response = Http::withToken($this->apiKey)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $this->model,
                    'n' => 1,
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a professional, friendly customer support assistant. 
                                      Provide structured, easy-to-read responses with clear formatting.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 250,
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
