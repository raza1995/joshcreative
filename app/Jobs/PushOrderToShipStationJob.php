<?php

namespace App\Jobs;

use App\Models\ShipStationOrder;
use App\Services\ShipStationApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PushOrderToShipStationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries;
    public $timeout;
    public $backoff = [60, 300, 900]; // Retry after 1min, 5min, 15min

    protected $shipstationOrderId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $shipstationOrderId)
    {
        $this->shipstationOrderId = $shipstationOrderId;
        $this->tries = config('shipstation.queue.max_tries', 3);
        $this->timeout = config('shipstation.queue.retry_after', 180);
        
        // Set queue connection and name from config
        $this->onConnection(config('shipstation.queue.connection', 'default'));
        $this->onQueue(config('shipstation.queue.name', 'shipstation'));
    }

    /**
     * Execute the job.
     */
    public function handle(ShipStationApiService $apiService): void
    {
        $order = ShipStationOrder::find($this->shipstationOrderId);

        if (!$order) {
            Log::warning('ShipStation order not found for push job', [
                'shipstation_order_id' => $this->shipstationOrderId,
            ]);
            return;
        }

        // Check if already pushed
        if ($order->consolidation_status === 'pushed') {
            Log::info('Order already pushed to ShipStation, skipping', [
                'order_number' => $order->order_number,
            ]);
            return;
        }

        Log::info('Pushing order to ShipStation', [
            'order_number' => $order->order_number,
            'attempt' => $this->attempts(),
        ]);

        // Rate limiting delay
        $delayMs = config('shipstation.rate_limit.delay_between_requests_ms', 1500);
        if ($delayMs > 0) {
            usleep($delayMs * 1000); // Convert ms to microseconds
        }

        // Push to ShipStation
        $result = $apiService->pushOrder($order);

        if ($result['success']) {
            // Mark as pushed
            $order->markAsPushed($result['order_id']);

            $action = $result['action'] ?? 'pushed';
            Log::info("Order {$action} in ShipStation successfully", [
                'order_number' => $order->order_number,
                'shipstation_order_id' => $result['order_id'],
                'action' => $action,
            ]);
        } else {
            // Mark as failed
            $order->markAsFailed($result['error']);

            Log::error('Failed to push order to ShipStation', [
                'order_number' => $order->order_number,
                'error' => $result['error'],
                'attempt' => $this->attempts(),
            ]);

            // If not last attempt, throw exception to retry
            if ($this->attempts() < $this->tries) {
                throw new \Exception('ShipStation push failed: ' . $result['error']);
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $order = ShipStationOrder::find($this->shipstationOrderId);

        if ($order) {
            $order->markAsFailed('Job failed after ' . $this->tries . ' attempts: ' . $exception->getMessage());
        }

        Log::error('PushOrderToShipStationJob failed permanently', [
            'shipstation_order_id' => $this->shipstationOrderId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'shipstation',
            'order:' . $this->shipstationOrderId,
        ];
    }
}

