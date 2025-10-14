<?php

namespace App\Services;

use App\Models\ShopifyProduct;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ShopifyProductSyncService
{
    private string $shopifyUrl;
    private string $accessToken;
    private int $perPage = 250; // Shopify's max per page

    public function __construct()
    {
        $this->shopifyUrl = "https://" . env('SHOPIFY_STORE_DOMAIN');
        $this->accessToken = env('SHOPIFY_ACCESS_TOKEN');
    }

    /**
     * Sync all products from Shopify
     */
    public function syncAll(): bool
    {
        try {
            Log::info('Starting Shopify product sync', [
                'timestamp' => now()->toDateTimeString(),
                'shopify_url' => $this->shopifyUrl,
            ]);

            $totalSynced = 0;
            $sinceId = null;
            $hasMorePages = true;

            while ($hasMorePages) {
                $products = $this->fetchProductsPage($sinceId);
                
                if (empty($products)) {
                    $hasMorePages = false;
                    continue;
                }

                foreach ($products as $product) {
                    $synced = $this->syncProduct($product);
                    if ($synced) {
                        $totalSynced++;
                    }
                }

                // If we got less than perPage items, we've reached the end
                if (count($products) < $this->perPage) {
                    $hasMorePages = false;
                } else {
                    // Use the last product's ID as the since_id for next page
                    $sinceId = end($products)['id'];
                }

                // Add a small delay to avoid rate limiting
                usleep(100000); // 0.1 second
            }

            Log::info('Shopify product sync completed', [
                'total_synced' => $totalSynced,
                'timestamp' => now()->toDateTimeString(),
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Shopify product sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'timestamp' => now()->toDateTimeString(),
            ]);

            return false;
        }
    }

    /**
     * Fetch a page of products from Shopify using since_id pagination
     */
    private function fetchProductsPage(?int $sinceId = null): array
    {
        try {
            $params = [
                'limit' => $this->perPage,
                'fields' => 'id,title,handle,product_type,vendor,tags,status,created_at,updated_at,images,variants',
            ];
            
            if ($sinceId) {
                $params['since_id'] = $sinceId;
            }

            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->accessToken,
                'Content-Type' => 'application/json',
            ])
            ->timeout(30)
            ->get("{$this->shopifyUrl}/admin/api/2025-10/products.json", $params);

            if (!$response->successful()) {
                Log::error('Failed to fetch Shopify products', [
                    'since_id' => $sinceId,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
                return [];
            }

            $data = $response->json();
            return $data['products'] ?? [];

        } catch (\Exception $e) {
            Log::error('Exception while fetching Shopify products', [
                'since_id' => $sinceId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Sync a single product and its variants
     */
    private function syncProduct(array $product): bool
    {
        try {
            $variants = $product['variants'] ?? [];
            
            foreach ($variants as $variant) {
                $this->syncProductVariant($product, $variant);
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to sync Shopify product', [
                'product_id' => $product['id'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Sync a product variant
     */
    private function syncProductVariant(array $product, array $variant): void
    {
        $data = [
            'shopify_product_id' => $product['id'],
            'shopify_variant_id' => $variant['id'],
            'title' => $variant['title'] ?: $product['title'],
            'handle' => $product['handle'],
            'sku' => $variant['sku'] ?? null,
            'barcode' => $variant['barcode'] ?? null,
            'price' => $variant['price'] ?? null,
            'compare_at_price' => $variant['compare_at_price'] ?? null,
            'cost_price' => $variant['cost_price'] ?? null,
            'weight' => $variant['weight'] ?? null,
            'weight_unit' => $variant['weight_unit'] ?? null,
            'inventory_quantity' => $variant['inventory_quantity'] ?? null,
            'inventory_policy' => $variant['inventory_policy'] ?? null,
            'inventory_management' => $variant['inventory_management'] ?? null,
            'fulfillment_service' => $variant['fulfillment_service'] ?? null,
            'product_type' => $product['product_type'] ?? null,
            'vendor' => $product['vendor'] ?? null,
            'tags' => $product['tags'] ? explode(',', $product['tags']) : null,
            'options' => $variant['option1'] ? [
                'option1' => $variant['option1'],
                'option2' => $variant['option2'] ?? null,
                'option3' => $variant['option3'] ?? null,
            ] : null,
            'status' => ($variant['inventory_management'] ?? '') === 'shopify' ? 'active' : 'draft',
            'requires_shipping' => $variant['requires_shipping'] ?? true,
            'taxable' => $variant['taxable'] ?? true,
            'tax_code' => $variant['tax_code'] ?? null,
            'images' => $product['images'] ?? null,
            'body_html' => $product['body_html'] ?? null,
            'seo_title' => $product['seo_title'] ?? null,
            'seo_description' => $product['seo_description'] ?? null,
            'raw_data' => [
                'product' => $product,
                'variant' => $variant,
            ],
            'last_synced_at' => now(),
        ];

        // Update or create the product
        ShopifyProduct::updateOrCreate(
            ['shopify_variant_id' => $variant['id']],
            $data
        );

        Log::info('Synced Shopify product variant', [
            'product_id' => $product['id'],
            'variant_id' => $variant['id'],
            'sku' => $variant['sku'] ?? 'N/A',
            'price' => $variant['price'] ?? 'N/A',
            'title' => $variant['title'] ?: $product['title'],
        ]);
    }

    /**
     * Sync specific products by SKU
     */
    public function syncBySkus(array $skus): bool
    {
        try {
            $totalSynced = 0;
            
            foreach ($skus as $sku) {
                $product = $this->fetchProductBySku($sku);
                if ($product) {
                    $synced = $this->syncProduct($product);
                    if ($synced) {
                        $totalSynced++;
                    }
                }
            }

            Log::info('Synced specific SKUs from Shopify', [
                'skus' => $skus,
                'total_synced' => $totalSynced,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to sync specific SKUs', [
                'skus' => $skus,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Fetch a product by SKU (searches through all products)
     */
    private function fetchProductBySku(string $sku): ?array
    {
        try {
            // Search through all products to find the one with matching SKU
            $sinceId = null;
            
            do {
                $products = $this->fetchProductsPage($sinceId);
                
                foreach ($products as $product) {
                    foreach ($product['variants'] ?? [] as $variant) {
                        if ($variant['sku'] === $sku) {
                            return $product;
                        }
                    }
                }
                
                if (count($products) < $this->perPage) {
                    break;
                }
                
                $sinceId = end($products)['id'];
                
            } while (!empty($products));

            return null;

        } catch (\Exception $e) {
            Log::error('Failed to fetch product by SKU', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get product pricing by SKU
     */
    public function getProductPrice(string $sku): ?float
    {
        $product = ShopifyProduct::findBySku($sku);
        return $product ? $product->getEffectivePrice() : null;
    }

    /**
     * Get products that need syncing
     */
    public function getProductsNeedingSync(int $hours = 24): \Illuminate\Database\Eloquent\Collection
    {
        return ShopifyProduct::needsSync($hours)->get();
    }
}