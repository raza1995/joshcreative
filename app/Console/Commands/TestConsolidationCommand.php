<?php

namespace App\Console\Commands;

use App\Models\ShopifyOrder;
use App\Models\ShipStationOrder;
use App\Services\ShipStationOrderConsolidatorService;
use Illuminate\Console\Command;

class TestConsolidationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shipstation:test-consolidation
                            {--order-id= : Specific Shopify order ID to test}
                            {--order-number= : Specific Shopify order number to test}
                            {--dry-run : Show what would be consolidated without saving}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test SKU consolidation logic on a specific order (dry-run mode)';

    protected $consolidator;

    public function __construct(ShipStationOrderConsolidatorService $consolidator)
    {
        parent::__construct();
        $this->consolidator = $consolidator;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 Testing SKU Consolidation Logic');
        $this->newLine();

        // Find order
        $shopifyOrder = $this->findOrder();

        if (!$shopifyOrder) {
            $this->error('❌ Order not found');
            return Command::FAILURE;
        }

        $this->info("📦 Order: {$shopifyOrder->order_number}");
        $this->info("📅 Date: {$shopifyOrder->order_date}");
        $this->info("👤 Customer: {$shopifyOrder->customer_name}");
        $this->newLine();

        // Parse raw JSON
        $rawJson = is_string($shopifyOrder->raw_json) 
            ? json_decode($shopifyOrder->raw_json, true) 
            : $shopifyOrder->raw_json;

        if (!is_array($rawJson) || empty($rawJson['line_items'])) {
            $this->error('❌ No line items found in order');
            return Command::FAILURE;
        }

        $lineItems = $rawJson['line_items'];
        $this->info("📋 Original Line Items: " . count($lineItems));
        $this->newLine();

        // Display original line items
        $this->info('BEFORE CONSOLIDATION:');
        $this->displayLineItems($lineItems);
        $this->newLine();

        // Test consolidation
        if ($this->option('dry-run')) {
            $this->warn('🔍 DRY RUN MODE - No data will be saved');
            $this->newLine();
        }

        try {
            // Consolidate
            if ($this->option('dry-run')) {
                // Manual consolidation without saving
                $consolidated = $this->simulateConsolidation($lineItems);
                $this->info('AFTER CONSOLIDATION (SIMULATED):');
                $this->displayConsolidatedItems($consolidated);
            } else {
                // Actual consolidation
                $shipstationOrder = $this->consolidator->consolidateOrder($shopifyOrder);

                if (!$shipstationOrder) {
                    $this->error('❌ Consolidation failed');
                    return Command::FAILURE;
                }

                $this->info('AFTER CONSOLIDATION:');
                $this->displayShipStationLineItems($shipstationOrder->lineItems);
                
                $this->newLine();
                $this->info("✅ Order consolidated successfully!");
                $this->info("🔑 ShipStation Order ID: {$shipstationOrder->id}");
                $this->info("📊 Status: {$shipstationOrder->consolidation_status}");
            }

            $this->newLine();
            $this->info('📊 CONSOLIDATION SUMMARY:');
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Original Items', count($lineItems)],
                    ['Consolidated Items', $this->option('dry-run') ? count($consolidated) : $shipstationOrder->lineItems->count()],
                    ['Reduction', count($lineItems) - ($this->option('dry-run') ? count($consolidated) : $shipstationOrder->lineItems->count())],
                ]
            );

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            $this->newLine();
            if ($this->output->isVerbose()) {
                $this->error($e->getTraceAsString());
            }
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Find order based on options
     */
    protected function findOrder()
    {
        if ($orderId = $this->option(key: 'order-id')) {
            return ShopifyOrder::find($orderId);
        }

        if ($orderNumber = $this->option('order-number')) {
            return ShopifyOrder::where('order_number', $orderNumber)->first();
        }

        $this->error('Please provide --order-id or --order-number');
        return null;
    }

    /**
     * Display original line items
     */
    protected function displayLineItems(array $items)
    {
        $rows = [];
        foreach ($items as $index => $item) {
            $rows[] = [
                $index + 1,
                $item['sku'] ?? 'NO-SKU',
                $item['title'] ?? $item['name'] ?? 'Unknown',
                $item['quantity'] ?? 1,
                '$' . number_format($item['price'] ?? 0, 2),
                '$' . number_format($item['total_discount'] ?? 0, 2),
            ];
        }

        $this->table(
            ['#', 'SKU', 'Product', 'Qty', 'Price', 'Discount'],
            $rows
        );
    }

    /**
     * Display consolidated items (simulated)
     */
    protected function displayConsolidatedItems(array $items)
    {
        $rows = [];
        foreach ($items as $index => $item) {
            $rows[] = [
                $index + 1,
                $item['sku'],
                $item['name'],
                $item['quantity'],
                '$' . number_format($item['unit_price'], 2),
                $item['is_consolidated'] ? '✅ Yes (' . $item['original_line_count'] . ')' : 'No',
            ];
        }

        $this->table(
            ['#', 'SKU', 'Product', 'Total Qty', 'Avg Price', 'Consolidated'],
            $rows
        );
    }

    /**
     * Display ShipStation line items
     */
    protected function displayShipStationLineItems($items)
    {
        $rows = [];
        foreach ($items as $index => $item) {
            $rows[] = [
                $index + 1,
                $item->sku,
                $item->name,
                $item->quantity,
                '$' . number_format($item->unit_price, 2),
                $item->is_consolidated ? '✅ Yes (' . $item->original_line_count . ')' : 'No',
            ];
        }

        $this->table(
            ['#', 'SKU', 'Product', 'Total Qty', 'Avg Price', 'Consolidated'],
            $rows
        );
    }

    /**
     * Simulate consolidation without saving
     */
    protected function simulateConsolidation(array $lineItems): array
    {
        $grouped = [];

        // Group by SKU
        foreach ($lineItems as $item) {
            $sku = $item['sku'] ?? 'NO-SKU';
            if (!isset($grouped[$sku])) {
                $grouped[$sku] = [];
            }
            $grouped[$sku][] = $item;
        }

        // Consolidate
        $consolidated = [];
        foreach ($grouped as $sku => $items) {
            if (count($items) === 1) {
                $item = $items[0];
                $consolidated[] = [
                    'sku' => $sku,
                    'name' => $item['title'] ?? 'Unknown',
                    'quantity' => $item['quantity'] ?? 1,
                    'unit_price' => floatval($item['price'] ?? 0),
                    'is_consolidated' => false,
                    'original_line_count' => 1,
                ];
            } else {
                $totalQty = 0;
                $totalValue = 0;
                $name = '';

                foreach ($items as $item) {
                    $qty = intval($item['quantity'] ?? 1);
                    $price = floatval($item['price'] ?? 0);
                    $totalQty += $qty;
                    $totalValue += ($qty * $price);
                    if (empty($name)) {
                        $name = $item['title'] ?? 'Unknown';
                    }
                }

                $avgPrice = $totalQty > 0 ? $totalValue / $totalQty : 0;

                $consolidated[] = [
                    'sku' => $sku,
                    'name' => $name,
                    'quantity' => $totalQty,
                    'unit_price' => round($avgPrice, 2),
                    'is_consolidated' => true,
                    'original_line_count' => count($items),
                ];
            }
        }

        return $consolidated;
    }
}

