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
        ShopifyOrder::select('id','raw_json','utm_source','utm_medium','utm_campaign','utm_content','utm_term','channel','ad_id')
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
                    // normalize keys to lowercase for robust lookups
                    $norm = [];
                    foreach ($params as $k => $v) { $norm[strtolower($k)] = $v; }

                    $utm_source   = $norm['utm_source']   ?? null;
                    $utm_medium   = $norm['utm_medium']   ?? null;
                    $utm_campaign = $norm['utm_campaign'] ?? null;
                    $utm_content  = $norm['utm_content']  ?? null;
                    $utm_term     = $norm['utm_term']     ?? null;

                    // extract ids
                    $gclid        = $norm['gclid']        ?? null;
                    $gbraid       = $norm['gbraid']       ?? null;
                    $fbclid       = $norm['fbclid']       ?? null;
                    $utm_id       = $norm['utm_id']       ?? null;
                    $campaign_id  = $norm['campaign_id']  ?? ($norm['gad_campaignid'] ?? ($norm['tw_campaign'] ?? null));

                    // Prefer ad_id if present, else utm_content if looks like numeric id, else tw_adid/fl_adid
                    $adIdParam = $norm['ad_id']
                        ?? ($this->looksLikeId($utm_content) ? $utm_content : null)
                        ?? ($norm['tw_adid'] ?? ($norm['fl_adid'] ?? null));

                    $channel = $this->inferChannel($utm_source, $utm_medium, $norm, $ref);

                    $data = [];
                    if ($force || is_null($o->utm_source))   $data['utm_source']   = $utm_source;
                    if ($force || is_null($o->utm_medium))   $data['utm_medium']   = $utm_medium;
                    if ($force || is_null($o->utm_campaign)) $data['utm_campaign'] = $utm_campaign;
                    if ($force || is_null($o->utm_content))  $data['utm_content']  = $utm_content;
                    if ($force || is_null($o->utm_term))     $data['utm_term']     = $utm_term;
                    if ($force || is_null($o->channel))      $data['channel']      = $channel;
                    if ($adIdParam && ($force || empty($o->ad_id))) $data['ad_id'] = (string) $adIdParam;
                    if ($utm_id !== null)       $data['utm_id'] = $utm_id;
                    if ($campaign_id !== null)  $data['campaign_id'] = $campaign_id;
                    if ($gclid !== null)        $data['gclid'] = $gclid;
                    if ($fbclid !== null)       $data['fbclid'] = $fbclid;

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

        // Klaviyo: source=klaviyo OR medium=email/campaign with klaviyo hints
        if (str_contains($s, 'klaviyo') || $m === 'email' || $m === 'campaign' || str_contains($refL, 'klaviyo') || isset($params['msg_type'])) {
            return 'Klaviyo Email';
        }
        // Google Ads: source=google OR gclid/gbraid/gad_* OR tw/fl hints
        if (str_contains($s, 'google') || isset($params['gclid']) || isset($params['gbraid']) || isset($params['gad_campaignid']) || (isset($params['tw_source']) && strtolower($params['tw_source']) === 'google') || (isset($params['fl_adsrc']) && strtolower($params['fl_adsrc']) === 'google') || in_array($m, ['cpc','ppc'])) {
            return 'Google Ads';
        }
        // Meta Ads
        if (str_contains($s, 'facebook') || str_contains($s, 'instagram') || str_contains($refL, 'facebook') || str_contains($refL, 'instagram') || isset($params['fbclid']) || isset($params['ad_id']) || isset($params['campaign_id'])) {
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

    private function looksLikeId(?string $val): bool
    {
        if ($val === null) return false;
        return preg_match('/^\d{10,30}$/', $val) === 1;
    }
}
