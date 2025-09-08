<?php

namespace App\Services;

class AttributionService
{
    public static function extract(array $order): array
    {
        $landing = (string) ($order['landing_site'] ?? '');
        $ref     = (string) ($order['referring_site'] ?? '');

        $params = [];
        if ($landing) {
            $qs = parse_url($landing, PHP_URL_QUERY);
            if ($qs) parse_str($qs, $params);
        }

        // normalize keys to lowercase
        $p = [];
        foreach ($params as $k => $v) { $p[strtolower($k)] = $v; }

        $utm_source   = $p['utm_source']   ?? null;
        $utm_medium   = $p['utm_medium']   ?? null;
        $utm_campaign = $p['utm_campaign'] ?? null;
        $utm_content  = $p['utm_content']  ?? null;
        $utm_term     = $p['utm_term']     ?? null;

        $utm_id       = $p['utm_id']       ?? null;
        $campaign_id  = $p['campaign_id']  ?? ($p['gad_campaignid'] ?? ($p['tw_campaign'] ?? null));
        $gclid        = $p['gclid']        ?? ($p['gbraid'] ?? null);
        $fbclid       = $p['fbclid']       ?? null;

        $adIdParam = $p['ad_id']
            ?? (self::looksLikeId($utm_content) ? $utm_content : null)
            ?? ($p['tw_adid'] ?? ($p['fl_adid'] ?? null));

        $channel = self::inferChannel($utm_source, $utm_medium, $p, $ref);

        return array_filter([
            'utm_source'   => $utm_source,
            'utm_medium'   => $utm_medium,
            'utm_campaign' => $utm_campaign,
            'utm_content'  => $utm_content,
            'utm_term'     => $utm_term,
            'utm_id'       => $utm_id,
            'campaign_id'  => $campaign_id,
            'gclid'        => $gclid,
            'fbclid'       => $fbclid,
            'ad_id'        => $adIdParam,
            'channel'      => $channel,
        ], function ($v) { return !is_null($v) && $v !== ''; });
    }

    private static function inferChannel(?string $source, ?string $medium, array $params, string $ref): ?string
    {
        $s = strtolower((string) $source);
        $m = strtolower((string) $medium);
        $refL = strtolower($ref);

        if (str_contains($s, 'klaviyo') || $m === 'email' || $m === 'campaign' || str_contains($refL, 'klaviyo') || isset($params['msg_type'])) {
            return 'Klaviyo Email';
        }
        if (str_contains($s, 'google') || isset($params['gclid']) || isset($params['gbraid']) || isset($params['gad_campaignid']) || (isset($params['tw_source']) && strtolower($params['tw_source']) === 'google') || (isset($params['fl_adsrc']) && strtolower($params['fl_adsrc']) === 'google') || in_array($m, ['cpc','ppc'])) {
            return 'Google Ads';
        }
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

    private static function looksLikeId(?string $val): bool
    {
        if ($val === null) return false;
        return preg_match('/^\d{10,30}$/', $val) === 1;
    }
}

