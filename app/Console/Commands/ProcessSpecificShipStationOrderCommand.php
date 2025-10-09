<?php

namespace App\Console\Commands;

use App\Models\ShipStationProcessedOrder;
use App\Services\ShipStationPullService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessSpecificShipStationOrderCommand extends Command
{
    protected $signature = 'shipstation:process-order {order-number}';
    protected $description = 'Process a specific ShipStation order with detailed logging';

    protected $pullService;

    public function __construct(ShipStationPullService $pullService)
    {
        parent::__construct();
        $this->pullService = $pullService;
    }

    public function handle()
    {
        $orderNumber = $this->argument('order-number');
        
        $this->info("🔍 Looking for order: {$orderNumber} in ShipStation...");
        $this->newLine();

        // Search for the order in ShipStation
        $order = $this->findOrderByNumber($orderNumber);

        if (!$order) {
            $this->error("❌ Order {$orderNumber} not found in ShipStation");
            return Command::FAILURE;
        }

        $this->info("✅ Found order in ShipStation!");
        $this->displayOrderDetails($order);
        $this->newLine();

        // Check if already processed
        $shipstationOrderId = (string)$order['orderId'];
        if (ShipStationProcessedOrder::wasProcessed($shipstationOrderId)) {
            $this->warn("⚠️ Order already processed!");
            $processed = ShipStationProcessedOrder::where('shipstation_order_id', $shipstationOrderId)->first();
            $this->table(
                ['Field', 'Value'],
                [
                    ['Action', $processed->action],
                    ['Items Before', $processed->items_before],
                    ['Items After', $processed->items_after],
                    ['Processed At', $processed->processed_at],
                    ['Notes', $processed->notes],
                ]
            );
            
            if (!$this->confirm('Reprocess this order?', false)) {
                return Command::SUCCESS;
            }
        }

        // Check order status
        $orderStatus = $order['orderStatus'] ?? 'unknown';
        if (in_array($orderStatus, ['shipped', 'cancelled'])) {
            $this->warn("⚠️ Order status is '{$orderStatus}' - cannot be updated via API");
            return Command::FAILURE;
        }

        // Display current items
        $this->info("📦 Current Items in Order:");
        $this->displayItems($order['items'] ?? []);
        $this->newLine();

        // Check for duplicate SKUs
        $hasDuplicates = $this->pullService->hasDuplicateSKUs($order);
        
        if (!$hasDuplicates) {
            $this->info("✅ No duplicate SKUs found - no consolidation needed");
            
            ShipStationProcessedOrder::markAsProcessed(
                $shipstationOrderId,
                $order['orderNumber'] ?? 'N/A',
                $order['orderKey'] ?? 'N/A',
                'skipped_no_duplicates',
                count($order['items'] ?? []),
                count($order['items'] ?? []),
                'No duplicate SKUs found'
            );
            
            return Command::SUCCESS;
        }

        $this->warn("🔄 Duplicate SKUs detected! Consolidating...");
        $this->newLine();

        // Consolidate items
        $originalItems = $order['items'] ?? [];
        $consolidatedItems = $this->pullService->consolidateItems($originalItems);

        $this->info("✅ Consolidated Items:");
        $this->displayItems($consolidatedItems);
        $this->newLine();

        $this->line("📊 Consolidation Summary:");
        $this->line("  Items Before: " . count($originalItems));
        $this->line("  Items After: " . count($consolidatedItems));
        $this->line("  Reduction: " . (count($originalItems) - count($consolidatedItems)) . " line items merged");
        $this->newLine();

        // Log the consolidation details
        Log::info('Processing specific order', [
            'order_number' => $orderNumber,
            'shipstation_order_id' => $shipstationOrderId,
            'items_before' => count($originalItems),
            'items_after' => count($consolidatedItems),
            'original_items' => $originalItems,
            'consolidated_items' => $consolidatedItems,
        ]);

        if (!$this->confirm('Push consolidated order to ShipStation?', true)) {
            $this->warn("⏸️ Aborted by user");
            return Command::SUCCESS;
        }

        // Update order with consolidated items
        $updatedOrder = array_merge($order, [
            'items' => $consolidatedItems,
        ]);

        $this->info("🚀 Pushing to ShipStation...");
        
        $response = $this->updateOrderInShipStation($updatedOrder);

        if ($response['success']) {
            $this->info("✅ Successfully updated order in ShipStation!");
            
            ShipStationProcessedOrder::markAsProcessed(
                $shipstationOrderId,
                $order['orderNumber'] ?? 'N/A',
                $order['orderKey'] ?? 'N/A',
                'consolidated',
                count($originalItems),
                count($consolidatedItems),
                'Successfully consolidated and updated',
                [
                    'reduction' => count($originalItems) - count($consolidatedItems),
                    'processed_via' => 'manual_command',
                ]
            );

            Log::info('Successfully processed order', [
                'order_number' => $orderNumber,
                'shipstation_order_id' => $shipstationOrderId,
            ]);

        } else {
            $this->error("❌ Failed to update order in ShipStation");
            $this->line("Error: " . ($response['error'] ?? 'Unknown error'));
            
            Log::error('Failed to update order in ShipStation', [
                'order_number' => $orderNumber,
                'shipstation_order_id' => $shipstationOrderId,
                'error' => $response['error'] ?? 'Unknown',
                'status_code' => $response['status_code'] ?? null,
                'response_body' => $response['response_body'] ?? null,
            ]);

            ShipStationProcessedOrder::markAsProcessed(
                $shipstationOrderId,
                $order['orderNumber'] ?? 'N/A',
                $order['orderKey'] ?? 'N/A',
                'failed',
                count($originalItems),
                null,
                'Update failed: ' . ($response['error'] ?? 'Unknown error')
            );

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    protected function findOrderByNumber(string $orderNumber): ?array
    {
        try {
            $authHeader = 'Basic ' . base64_encode(
                config('shipstation.api_key') . ':' . config('shipstation.api_secret')
            );

            $response = Http::withHeaders([
                'Authorization' => $authHeader,
            ])
            ->timeout(30)
            ->get(config('shipstation.base_url') . '/orders', [
                'orderNumber' => $orderNumber,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $orders = $data['orders'] ?? [];
                
                if (count($orders) > 0) {
                    return $orders[0];
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to find order', [
                'order_number' => $orderNumber,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    protected function displayOrderDetails(array $order): void
    {
        $this->table(
            ['Field', 'Value'],
            [
                ['Order ID', $order['orderId'] ?? 'N/A'],
                ['Order Number', $order['orderNumber'] ?? 'N/A'],
                ['Order Key', $order['orderKey'] ?? 'N/A'],
                ['Status', $order['orderStatus'] ?? 'N/A'],
                ['Customer', $order['customerEmail'] ?? 'N/A'],
                ['Order Date', $order['orderDate'] ?? 'N/A'],
                ['Total Items', count($order['items'] ?? [])],
                ['Amount Paid', '$' . number_format($order['amountPaid'] ?? 0, 2)],
            ]
        );
    }

    protected function displayItems(array $items): void
    {
        $rows = [];
        foreach ($items as $index => $item) {
            $rows[] = [
                $index + 1,
                $item['sku'] ?? 'N/A',
                $item['name'] ?? 'N/A',
                $item['quantity'] ?? 0,
                '$' . number_format($item['unitPrice'] ?? 0, 2),
                '$' . number_format(($item['quantity'] ?? 0) * ($item['unitPrice'] ?? 0), 2),
            ];
        }

        $this->table(
            ['#', 'SKU', 'Name', 'Qty', 'Unit Price', 'Total'],
            $rows
        );
    }

    protected function updateOrderInShipStation(array $order): array
    {
        try {
            $authHeader = 'Basic ' . base64_encode(
                config('shipstation.api_key') . ':' . config('shipstation.api_secret')
            );

            Log::info('Pushing consolidated order to ShipStation', [
                'order_number' => $order['orderNumber'] ?? 'N/A',
                'order_id' => $order['orderId'] ?? 'N/A',
                'items_count' => count($order['items'] ?? []),
            ]);

            $response = Http::withHeaders([
                'Authorization' => $authHeader,
                'Content-Type' => 'application/json',
            ])
            ->timeout(30)
            ->post(config('shipstation.base_url') . '/orders/createorder', $order);

            $statusCode = $response->status();
            $responseBody = $response->body();

            Log::info('ShipStation API Response', [
                'status_code' => $statusCode,
                'response_body' => $responseBody,
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'error' => $responseBody,
                'status_code' => $statusCode,
                'response_body' => $responseBody,
            ];
        } catch (\Exception $e) {
            Log::error('Exception updating order in ShipStation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}

