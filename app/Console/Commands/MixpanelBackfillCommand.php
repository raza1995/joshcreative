<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use App\Models\DeviceDim;
use App\Models\RefDomainDim;
use App\Models\ProductDim;
use App\Models\FactEvent;
use Illuminate\Support\Facades\DB;
use App\Services\MixpanelIngestService;
class MixpanelBackfillCommand extends Command
{
    protected $signature = 'mp:backfill {--days=7 : Number of past days to backfill}';
    protected $description = 'Backfill Mixpanel data for N days in one go (manual)';

    public function handle(): int
    {
        $days = max((int) $this->option('days'), 1);
        $start = now('UTC')->subDays($days)->startOfDay();
        $end   = now('UTC')->startOfDay();

        $events = $this->fetchRange($start, $end);
        $this->ingest($events);

        $this->info("✅ Backfilled {$days} day(s) of Mixpanel data.");
        return 0;
    }

    private function fetchRange(Carbon $start, Carbon $end): array
    {
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

        $resp = Http::withBasicAuth(env('SECRET_MIXPANEL'), '')
            ->withHeaders(['Accept' => 'text/plain'])
            ->timeout(300)
            ->get('https://data.mixpanel.com/api/2.0/export/', $query);

        if (!$resp->ok()) {
            $this->error("❌ API Error: HTTP {$resp->status()} - " . $resp->body());
            return [];
        }

        $out = [];
        foreach (explode("\n", trim($resp->body())) as $line) {
            if ($line === '') continue;
            $json = json_decode($line, true);
            if ($json) $out[] = $json;
        }

        $this->line("📦 Retrieved ".count($out)." events between {$start->toDateString()} → {$end->toDateString()}");
        return $out;
    }

    private function ingest(array $events): void
    {
        // Reuse your ingestion logic here
        (new MixpanelIngestService)->ingest($events);
    }
}
