<?php

namespace App\Console\Commands;

use App\Models\ShopifyOrder;
use App\Models\ShipStationOrder;
use App\Services\ShipStationOrderConsolidatorService;
use App\Services\ShipStationApiService;
use App\Jobs\PushOrderToShipStationJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PushOrdersToShipStationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shipstation:push-orders
                            {--order-id= : Specific Shopify order ID to push}
                            {--order-number= : Specific Shopify order number to push}
                            {--since= : Push orders since this date (Y-m-d format)}
                            {--limit=100 : Maximum number of orders to process}
                            {--sync : Push synchronously instead of queueing}
                            {--force : Force re-consolidation even if already consolidated}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Push Shopify orders to ShipStation with SKU consolidation';

    protected $consolidator;
    protected $apiService;

    public function __construct(
        ShipStationOrderConsolidatorService $consolidator,
        ShipStationApiService $apiService
    ) {
        parent::__construct();
        $this->consolidator = $consolidator;
        $this->apiService = $apiService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting ShipStation order push process...');
        $this->newLine();

        // Build query
        $query = $this->buildQuery();
        
        if (!$query) {
            $this->error('❌ No valid query parameters provided');
            return Command::FAILURE;
        }

        $limit = (int)$this->option('limit');
        $orders = $query->limit($limit)->get();

        if ($orders->isEmpty()) {
            $this->warn('⚠️  No orders found matching criteria');
            return Command::SUCCESS;
        }

        $this->info("📦 Found {$orders->count()} order(s) to process");
        $this->newLine();

        $bar = $this->output->createProgressBar($orders->count());
        $bar->start();

        $stats = [
            'consolidated' => 0,
            'created' => 0,
            'updated' => 0,
            'queued' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        foreach ($orders as $shopifyOrder) {
            $bar->advance();

            try {
                // Check if already consolidated
                $shipstationOrder = ShipStationOrder::where('order_number', $shopifyOrder->order_number)->first();

                if ($shipstationOrder && !$this->option('force')) {
                    if ($shipstationOrder->consolidation_status === 'pushed') {
                        Log::info("Order already pushed, skipping", [
                            'order_number' => $shopifyOrder->order_number,
                            'pushed_at' => $shipstationOrder->pushed_at,
                            'shipstation_order_id' => $shipstationOrder->shipstation_order_id,
                        ]);
                        $stats['skipped']++;
                        continue;
                    }
                } else {
                    // Consolidate order
                    $shipstationOrder = $this->consolidator->consolidateOrder($shopifyOrder);

                    if (!$shipstationOrder) {
                        Log::warning("Order consolidation failed (validation)", [
                            'order_number' => $shopifyOrder->order_number,
                            'note' => 'Check validation rules in config/shipstation.php',
                        ]);
                        $stats['failed']++;
                        continue;
                    }

                    $stats['consolidated']++;
                }

                // Push to ShipStation
                if ($this->option('sync')) {
                    // Synchronous push
                    $result = $this->apiService->pushOrder($shipstationOrder);

                    if ($result['success']) {
                        $shipstationOrder->markAsPushed($result['order_id']);
                        
                        $action = $result['action'] ?? 'pushed';
                        if ($action === 'created') {
                            $stats['created']++;
                        } elseif ($action === 'updated') {
                            $stats['updated']++;
                        } elseif ($action === 'skipped') {
                            $stats['skipped']++;
                        }
                    } else {
                        $shipstationOrder->markAsFailed($result['error']);
                        $stats['failed']++;
                    }
                } else {
                    // Async push via queue
                    PushOrderToShipStationJob::dispatch($shipstationOrder->id);
                    $stats['queued']++;
                }

            } catch (\Exception $e) {
                $this->error("\n❌ Error processing order {$shopifyOrder->order_number}: {$e->getMessage()}");
                $stats['failed']++;
            }
        }

        $bar->finish();
        $this->newLine(2);

        // Display summary
        $this->info('✅ Process completed!');
        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Consolidated', $stats['consolidated']],
                ['Created (New)', $stats['created']],
                ['Updated (Existing)', $stats['updated']],
                ['Queued (Async)', $stats['queued']],
                ['Skipped', $stats['skipped']],
                ['Failed', $stats['failed']],
            ]
        );

        return Command::SUCCESS;
    }

    /**
     * Build query based on options
     */
    protected function buildQuery()
    {
        $query = ShopifyOrder::query();

        // Specific order ID
        if ($orderId = $this->option('order-id')) {
            return $query->where('id', $orderId);
        }

        // Specific order number
        if ($orderNumber = $this->option('order-number')) {
            return $query->where('order_number', $orderNumber);
        }

        // Date range
        if ($since = $this->option('since')) {
            try {
                $date = Carbon::parse($since);
                $query->where('order_date', '>=', $date);
            } catch (\Exception $e) {
                $this->error("Invalid date format: {$since}");
                return null;
            }
        } else {
            // Default: last 7 days
            $query->where('order_date', '>=', now()->subDays(7));
        }

        // Ensure raw_json exists
        $query->whereNotNull('raw_json');

        // Order by date
        $query->orderBy('order_date', 'desc');

        return $query;
    }
}

