<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\DeviceDim;
use App\Models\RefDomainDim;
use App\Models\ProductDim;
use App\Models\FactEvent;

class MixpanelIngestService
{
    protected array $map = [
        'Product viewed'         => 'view',
        'product_added_to_cart'  => 'atc',
        'checkout_started'       => 'cko',
        'checkout_completed'     => 'buy',
    ];

    public function ingest(array $events): void
    {
        if (!$events) return;

        DB::transaction(function () use ($events) {
            foreach ($events as $e) {
                try {
                    $eventName  = $e['event'] ?? null;
                    $deviceUuid = $e['properties']['$device_id'] ?? $e['distinct_id'] ?? null;

                    if (!$deviceUuid || !isset($this->map[$eventName])) {
                        continue;
                    }

                    $device = DeviceDim::firstOrCreate(['device_uuid' => $deviceUuid]);

                    $ref = $e['properties']['$initial_referring_domain']
                        ?? $e['properties']['referrer_domain']
                        ?? null;

                    $domain = $ref ? RefDomainDim::firstOrCreate(['domain' => $ref]) : null;
                    $product = null;

                    $checkoutLines = $e['properties']['event']['data']['checkout']['lineItems'] ?? [];

                    if ($checkoutLines) {
                        foreach ($checkoutLines as $lineItem) {
                            $product = ProductDim::firstOrCreate(
                                ['title' => $lineItem['title']],
                                [
                                    'sku'        => $lineItem['variant']['sku'] ?? null,
                                    'list_price' => $lineItem['variant']['price']['amount'] ?? null,
                                ]
                            );

                            FactEvent::create([
                                'device_id'     => $device->id,
                                'ref_domain_id' => $domain?->id,
                                'product_id'    => $product->id,
                                'event_type'    => $this->map[$eventName],
                                'event_ts'      => Carbon::createFromTimestampUTC($e['properties']['time'] ?? time()),
                                'order_id'      => $e['properties']['event']['data']['checkout']['order']['id'] ?? null,
                                'revenue'       => $lineItem['finalLinePrice']['amount']
                                    ?? $lineItem['variant']['price']['amount']
                                    ?? $e['properties']['totalPrice']
                                    ?? $e['properties']['amount']
                                    ?? null,
                                'qty'           => $lineItem['quantity'] ?? 1,
                                'utm_source'    => $e['properties']['utm_source'] ?? null,
                                'utm_medium'    => $e['properties']['utm_medium'] ?? null,
                                'utm_campaign'  => $e['properties']['utm_campaign'] ?? null,
                                'utm_term'      => $e['properties']['utm_term'] ?? null,
                                'raw_props'     => json_encode($e['properties']),
                            ]);
                        }
                    } else {
                        FactEvent::create([
                            'device_id'     => $device->id,
                            'ref_domain_id' => $domain?->id,
                            'product_id'    => $product?->id ?? null,
                            'event_type'    => $this->map[$eventName],
                            'event_ts'      => Carbon::createFromTimestampUTC($e['properties']['time'] ?? time()),
                            'order_id'      => $e['properties']['event']['data']['checkout']['order']['id'] ?? null,
                            'revenue'       => $e['properties']['totalPrice'] ?? $e['properties']['amount'] ?? null,
                            'qty'           => $e['properties']['quantity'] ?? 1,
                            'utm_source'    => $e['properties']['utm_source'] ?? null,
                            'utm_medium'    => $e['properties']['utm_medium'] ?? null,
                            'utm_campaign'  => $e['properties']['utm_campaign'] ?? null,
                            'utm_term'      => $e['properties']['utm_term'] ?? null,
                            'raw_props'     => json_encode($e['properties']),
                        ]);
                    }
                } catch (\Throwable $ex) {
                    \Log::error('Mixpanel ingestion failed', ['event' => $e, 'error' => $ex->getMessage()]);
                }
            }
        });
    }
}
