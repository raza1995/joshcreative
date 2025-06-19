<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use App\Models\DeviceDim;
use App\Models\RefDomainDim;
use App\Models\ProductDim;
use App\Models\FactEvent;

class MixpanelIngestCommand extends Command
{
    /**
     * Pull the last N hours of Mixpanel events and ingest into local DB.
     *
     *   php artisan mp:ingest          # fetch previous 1 hour (default)
     *   php artisan mp:ingest --hours=24  # back-fill 24 separate hours
     */
    protected $signature   = 'mp:ingest {--hours=1 : How many past hours to pull}';
    protected $description = 'Ingest Mixpanel funnel events into analytics schema';

    /* ------------------------------------------------------------ */

    public function handle(): int
    {
        $hours = max((int) $this->option('hours'), 1);

        for ($offset = $hours; $offset >= 1; $offset--) {
            $events = $this->fetchHour($offset);
            $this->ingest($events);
        }

        $this->info("Done – ingested data for {$hours} hour(s).");
        return 0;
    }

    /* ------------------------------------------------------------ */
    /*  1. Fetch one hour of events from Mixpanel Export API        */
    /* ------------------------------------------------------------ */

    private function fetchHour(int $hoursBack): array
    {
        $end   = Carbon::now('UTC')->startOfHour()->subHours($hoursBack - 1);
        $start = (clone $end)->subHour();
        
        $query = [
            'from_date' => $start->toDateString(),
            'to_date'   => $end->toDateString(),
        
            'event' => json_encode([
                "Product viewed",
                "product_added_to_cart",
                "checkout_started",
                "checkout_completed"
            ]),
    
        ];
        
        $url = 'https://data.mixpanel.com/api/2.0/export/';
        
        $resp = Http::withBasicAuth(env('SECRET_MIXPANEL'), '')
            ->withHeaders(['Accept' => 'text/plain'])
            ->timeout(90)
            ->get($url, $query);
        
        if (!$resp->ok()) {
            $this->error("Mixpanel Export API error: HTTP {$resp->status()} - " . $resp->body());
        }
        

        \Log::info('Mixpanel Export API response', [
            'status' => $resp->status(),
            'body'   => $resp->json(),
            'url'    => $url,
            'query'  => $query,
        ]);

        if (!$resp->ok()) {
            $this->error("Export API failed for {$start}–{$end}: {$resp->status()} {$resp->body()}");
            return [];
        }

        $out = [];
        foreach (explode("\n", trim($resp->body())) as $line) {
            if ($line === '') continue;
            $json = json_decode($line, true);
            if ($json) $out[] = $json;
        }

        $this->line("Fetched ".count($out)." events   {$start} → {$end}");
        return $out;
    }

    /* ------------------------------------------------------------ */
    /*  2. Insert / upsert into star-schema tables                  */
    /* ------------------------------------------------------------ */

    private function ingest(array $events): void
    {
        if (!$events) return;

        /* mapping Mixpanel name → local enum */
        $map = [
            'Product viewed'      => 'view',
            'product_added_to_cart'       => 'atc',
            'checkout_started'    => 'cko',
            'checkout_completed'     => 'buy',
        ];

        DB::transaction(function () use ($events, $map) {

            foreach ($events as $e) {
                try {
                    $eventName = $e['event'] ?? null;
                    $deviceUuid = $e['properties']['$device_id'] ?? $e['distinct_id'] ?? null;

                    if (!$deviceUuid || !isset($map[$eventName])) {
                        continue;                    // skip bots / malformed
                    }

                    /* ── dimensions ─────────────────────────────── */
                    $device = DeviceDim::firstOrCreate(['device_uuid' => $deviceUuid]);

                    $ref = $e['properties']['$initial_referring_domain']
                        ?? $e['properties']['referrer_domain']
                        ?? null;
                    $domain = $ref ? RefDomainDim::firstOrCreate(['domain' => $ref]) : null;

                    $product = null;
                    $merch = $e['event']['data']['cartLine']['merchandise'] ?? null;
                    
                    if (isset($e['properties']['event']['data']['checkout']['lineItems'])) {
                        foreach ($e['properties']['event']['data']['checkout']['lineItems'] as $lineItem) {
                    
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
                                'product_id'    => $product?->id,
                                'event_type'    => $map[$eventName],
                                'event_ts'      => isset($e['properties']['time']) 
                                                    ? Carbon::createFromTimestampUTC($e['properties']['time']) 
                                                    : now(),
                                'order_id'      => $e['properties']['event']['data']['checkout']['order']['id'] ?? null,
                                'revenue'       => $lineItem['finalLinePrice']['amount'] 
                                                    ?? $lineItem['variant']['price']['amount'] 
                                                    ?? $e['properties']['totalPrice'] 
                                                    ?? $e['properties']['amount'] 
                                                    ?? null,
                                'qty'           => $lineItem['quantity'] ?? 1,
                                'utm_source'    => $e['properties']['utm_source']   ?? null,
                                'utm_medium'    => $e['properties']['utm_medium']   ?? null,
                                'utm_campaign'  => $e['properties']['utm_campaign'] ?? null,
                                'utm_term'      => $e['properties']['utm_term']     ?? null,
                                'raw_props'     => json_encode($e['properties']),
                            ]);
                        }
                    } else {
                        // fallback for non-line item events
                        FactEvent::create([
                            'device_id'     => $device->id,
                            'ref_domain_id' => $domain?->id,
                            'product_id'    => $product?->id ?? null,
                            'event_type'    => $map[$eventName],
                            'event_ts'      => isset($e['properties']['time']) 
                                                ? Carbon::createFromTimestampUTC($e['properties']['time']) 
                                                : now(),
                            'order_id'      => $e['properties']['event']['data']['checkout']['order']['id'] ?? null,
                            'revenue'       => $e['properties']['totalPrice'] ?? $e['properties']['amount'] ?? null,
                            'qty'           => $e['properties']['quantity'] ?? 1,
                            'utm_source'    => $e['properties']['utm_source']   ?? null,
                            'utm_medium'    => $e['properties']['utm_medium']   ?? null,
                            'utm_campaign'  => $e['properties']['utm_campaign'] ?? null,
                            'utm_term'      => $e['properties']['utm_term']     ?? null,
                            'raw_props'     => json_encode($e['properties']),
                        ]);
                    }
                    

                } catch (\Throwable $ex) {
                    $this->error("Failed to ingest event: ".json_encode($e));
                    $this->error($ex->getMessage());
                }
            }
        });
    }
}
