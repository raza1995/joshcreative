<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\Product;
use App\Models\ProductVariant;

class ShopifyProductSyncService
{
    public function syncAll()
    {
        $baseUrl = "https://" . env('SHOPIFY_STORE_DOMAIN') . "/admin/api/2024-01/products.json";
        $params = ['limit' => 250];
        $nextUrl = $baseUrl;

        do {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => env('SHOPIFY_ACCESS_TOKEN'),
            ])->get($nextUrl, $params);

            if (!$response->successful()) {
                \Log::error('❌ Failed to fetch Shopify products', ['body' => $response->body()]);
                return false;
            }

            $products = $response->json()['products'] ?? [];

         

            foreach ($products as $shopifyProduct) {
                // Create or update Product
                $product = Product::updateOrCreate(
                    ['product_id' => $shopifyProduct['id']],
                    [
                        'title' => $shopifyProduct['title'],
                        'vendor' => $shopifyProduct['vendor'] ?? null,
                    ]
                );

                // Now sync variants
                foreach ($shopifyProduct['variants'] as $variant) {
                    $price = floatval($variant['price']);
                    $cost = null;

                    if (!empty($variant['inventory_item_id'])) {
                        $invResponse = Http::withHeaders([
                            'X-Shopify-Access-Token' => env('SHOPIFY_ACCESS_TOKEN')
                        ])->get("https://" . env('SHOPIFY_STORE_DOMAIN') . "/admin/api/2024-01/inventory_items/{$variant['inventory_item_id']}.json");
                        \Log::info('🔄 Initiating Shopify product sync', [
                            'inv' => $invResponse,
                        
                        ]);
                        if ($invResponse->successful()) {
                            $cost = $invResponse->json()['inventory_item']['cost'] ?? null;
                        } else {
                            \Log::warning("⚠️ Failed to fetch inventory item", ['id' => $variant['inventory_item_id']]);
                        }
                    }

                    $profit = ($cost !== null) ? round($price - $cost, 2) : null;
                    $margin = ($price > 0 && $cost !== null) ? round((($price - $cost) / $price) * 100, 2) : null;

                    ProductVariant::updateOrCreate(
                        ['variant_id' => $variant['id']],
                        [
                            'product_id' => $product->id,
                            'title' => $variant['title'],
                            'sku' => $variant['sku'],
                            'price' => $price,
                            'cost' => $cost,
                            'profit' => $profit,
                            'margin' => $margin,
                        ]
                    );
                }
            }

            // Pagination
            $nextUrl = null;
            $linkHeader = $response->header('Link');
            if ($linkHeader && strpos($linkHeader, 'rel="next"') !== false) {
                preg_match('/<([^>]+)>; rel="next"/', $linkHeader, $matches);
                $nextUrl = $matches[1] ?? null;
            }
        } while ($nextUrl);

        return true;
    }
}
