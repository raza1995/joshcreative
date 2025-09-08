<?php

namespace App\Console\Commands;

use App\Models\ShopifyOrder;
use Illuminate\Console\Command;

class BackfillShopifyUtms extends Command
{
    protected $signature = 'kpi:backfill-utms {--force : Overwrite existing UTM/channel values}';
    protected $description = 'Parse landing_site/referring_site from raw_json and backfill UTM fields and channel';

    public function handle()
    {
        $force = (bool) $this->option('force');
        $this->info(($force ? '[FORCE] ' : '') . 'Backfilling UTM parameters and channel...');

        $updated = 0; $scanned = 0;
        ShopifyOrder::select('id','raw_json','utm_source','utm_medium','utm_campaign','utm_content','utm_term','channel')
            ->orderBy('id')
            ->chunkById(2000, function ($chunk) use (&$updated, &$scanned, $force) {
                foreach ($chunk as $o) {
                    $scanned++;
                    $raw = is_array($o->raw_json) ? $o->raw_json : (is_string($o->raw_json) ? (json_decode($o->raw_json, true) ?: []) : []);
                    $landing = (string) ($raw['landing_site'] ?? '');
                    $ref = (string) ($raw['referring_site'] ?? '');

                    $params = [];
                    if ($landing) {
                        $qs = parse_url($landing, PHP_URL_QUERY);
                        if ($qs) parse_str($qs, $params);
                    }

                    $utm_source   = $params['utm_source']   ?? null;
                    $utm_medium   = $params['utm_medium']   ?? null;
                    $utm_campaign = $params['utm_campaign'] ?? null;
                    $utm_content  = $params['utm_content']  ?? null;
                    $utm_term     = $params['utm_term']     ?? null;

                    $channel = $this->inferChannel($utm_source, $utm_medium, $params, $ref);

                    $data = [];
                    if ($force || is_null($o->utm_source))   $data['utm_source']   = $utm_source;
                    if ($force || is_null($o->utm_medium))   $data['utm_medium']   = $utm_medium;
                    if ($force || is_null($o->utm_campaign)) $data['utm_campaign'] = $utm_campaign;
                    if ($force || is_null($o->utm_content))  $data['utm_content']  = $utm_content;
                    if ($force || is_null($o->utm_term))     $data['utm_term']     = $utm_term;
                    if ($force || is_null($o->channel))      $data['channel']      = $channel;

                    if (!empty($data)) {
                        ShopifyOrder::where('id', $o->id)->update($data);
                        $updated++;
                    }
                }
            });

        $this->info("Scanned: {$scanned}, Updated: {$updated}");
        return self::SUCCESS;
    }

    private function inferChannel(?string $source, ?string $medium, array $params, string $ref): ?string
    {
        $s = strtolower((string) $source);
        $m = strtolower((string) $medium);
        $refL = strtolower($ref);

        if (str_contains($s, 'klaviyo') || $m === 'email' || str_contains($refL, 'klaviyo')) {
            return 'Klaviyo Email';
        }
        if (str_contains($s, 'google') && ($m === 'cpc' || $m === 'ppc' || isset($params['gclid']))) {
            return 'Google Ads';
        }
        if (str_contains($s, 'facebook') || str_contains($s, 'instagram') || str_contains($refL, 'facebook') || str_contains($refL, 'instagram')) {
            return 'Meta Ads';
        }
        if (str_contains($s, 'tiktok') || str_contains($refL, 'tiktok')) {
            return 'TikTok Ads';
        }
        if (str_contains($s, 'bing') || str_contains($s, 'microsoft')) {
            return 'Bing Ads';
        }
        if (!$ref) {
            return 'Direct';
        }
        return 'Organic/Other';
    }
}

