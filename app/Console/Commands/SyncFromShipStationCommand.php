<?php

namespace App\Console\Commands;

use App\Models\ShipStationProcessedOrder;
use App\Services\ShipStationPullService;
use App\Services\ShipStationApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncFromShipStationCommand extends Command
{
    protected $signature = 'shipstation:sync-from-api
                            {--minutes=60 : Pull orders modified in last X minutes}
                            {--force : Reprocess already processed orders}
                            {--dry-run : Show what would be done without actually doing it}';

    protected $description = 'Pull orders from ShipStation and consolidate duplicate SKUs';

    protected $pullService;
    protected $apiService;

    public function __construct(
        ShipStationPullService $pullService,
        ShipStationApiService $apiService
    ) {
        parent::__construct();
        $this->pullService = $pullService;
        $this->apiService = $apiService;
    }

    public function handle()
    {
        $this->info('🔄 Syncing orders from ShipStation...');
        $this->newLine();

        $minutes = (int)$this->option('minutes');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Pull orders from ShipStation
        $orders = $this->pullService->pullNewOrders($minutes);

        if (empty($orders)) {
            $this->warn('No orders found in ShipStation from last ' . $minutes . ' minutes');
            return Command::SUCCESS;
        }

        $this->info("📦 Found " . count($orders) . " order(s) from ShipStation");
        $this->newLine();

        $stats = [
            'total' => count($orders),
            'consolidated' => 0,
            'skipped_no_duplicates' => 0,
            'skipped_already_processed' => 0,
            'skipped_shipped' => 0,
            'failed' => 0,
        ];

        $bar = $this->output->createProgressBar(count($orders));
        $bar->start();

        foreach ($orders as $order) {
            $bar->advance();

            $shipstationOrderId = (string)$order['orderId'];
            $orderNumber = $order['orderNumber'] ?? 'N/A';
            $orderKey = $order['orderKey'] ?? 'N/A';
            $orderStatus = $order['orderStatus'] ?? 'unknown';

            try {
                // Check if already processed
                if (!$force && ShipStationProcessedOrder::wasProcessed($shipstationOrderId)) {
                    $stats['skipped_already_processed']++;
                    continue;
                }

                // Check if order can be updated
                if (in_array($orderStatus, ['shipped', 'cancelled'])) {
                    if (!$dryRun) {
                        ShipStationProcessedOrder::markAsProcessed(
                            $shipstationOrderId,
                            $orderNumber,
                            $orderKey,
                            'skipped_shipped',
                            count($order['items'] ?? []),
                            count($order['items'] ?? []),
                            "Order status: {$orderStatus} - cannot be updated"
                        );
                    }
                    $stats['skipped_shipped']++;
                    continue;
                }

                // Check if has duplicate SKUs
                if (!$this->pullService->hasDuplicateSKUs($order)) {
                    if (!$dryRun) {
                        ShipStationProcessedOrder::markAsProcessed(
                            $shipstationOrderId,
                            $orderNumber,
                            $orderKey,
                            'skipped_no_duplicates',
                            count($order['items'] ?? []),
                            count($order['items'] ?? []),
                            'No duplicate SKUs found'
                        );
                    }
                    $stats['skipped_no_duplicates']++;
                    continue;
                }

                // Consolidate items
                $originalItems = $order['items'] ?? [];
                $consolidatedItems = $this->pullService->consolidateItems($originalItems);

                Log::info('Consolidating order from ShipStation', [
                    'order_id' => $shipstationOrderId,
                    'order_number' => $orderNumber,
                    'items_before' => count($originalItems),
                    'items_after' => count($consolidatedItems),
                ]);

                if ($dryRun) {
                    $this->newLine();
                    $this->line("Would consolidate order {$orderNumber}: " . count($originalItems) . " → " . count($consolidatedItems) . " items");
                    $stats['consolidated']++;
                    continue;
                }

                // Update order in ShipStation with consolidated items
                $updatedOrder = array_merge($order, [
                    'items' => $consolidatedItems,
                ]);

                $response = $this->updateOrderInShipStation($updatedOrder);

                if ($response['success']) {
                    ShipStationProcessedOrder::markAsProcessed(
                        $shipstationOrderId,
                        $orderNumber,
                        $orderKey,
                        'consolidated',
                        count($originalItems),
                        count($consolidatedItems),
                        'Successfully consolidated and updated',
                        [
                            'reduction' => count($originalItems) - count($consolidatedItems),
                        ]
                    );
                    $stats['consolidated']++;
                } else {
                    ShipStationProcessedOrder::markAsProcessed(
                        $shipstationOrderId,
                        $orderNumber,
                        $orderKey,
                        'failed',
                        count($originalItems),
                        null,
                        'Update failed: ' . ($response['error'] ?? 'Unknown error')
                    );
                    $stats['failed']++;
                }

            } catch (\Exception $e) {
                Log::error('Error processing order from ShipStation', [
                    'order_id' => $shipstationOrderId,
                    'error' => $e->getMessage(),
                ]);
                $stats['failed']++;
            }
        }

        $bar->finish();
        $this->newLine(2);

        // Display summary
        $this->info('✅ Sync completed!');
        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Orders', $stats['total']],
                ['Consolidated ✅', $stats['consolidated']],
                ['No Duplicates', $stats['skipped_no_duplicates']],
                ['Already Processed', $stats['skipped_already_processed']],
                ['Already Shipped', $stats['skipped_shipped']],
                ['Failed', $stats['failed']],
            ]
        );

        return Command::SUCCESS;
    }

    protected function updateOrderInShipStation(array $order): array
    {
        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode(config('shipstation.api_key') . ':' . config('shipstation.api_secret')),
                'Content-Type' => 'application/json',
            ])
            ->timeout(30)
            ->post(config('shipstation.base_url') . '/orders/createorder', $order);

            if ($response->successful()) {
                return ['success' => true];
            }

            return [
                'success' => false,
                'error' => $response->body(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}

