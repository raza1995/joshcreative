<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class KlaviyoAnalytics
{
    private string $api = 'https://a.klaviyo.com/api';
    private string $key;
    private string $stableRev;
    private string $betaRev;

    public function __construct()
    {
        $this->key       = (string) env('KLAVIYO_PRIVATE_KEY', '');
        $this->stableRev = (string) env('KLAVIYO_REVISION_STABLE', '2025-07-15');
        $this->betaRev   = (string) env('KLAVIYO_REVISION_BETA', '2025-04-15.pre');

        if ($this->key === '') {
            throw new \RuntimeException('KLAVIYO_PRIVATE_KEY is missing.');
        }
    }

    private function http(string $rev)
    {
        return Http::withHeaders([
            'Authorization' => 'Klaviyo-API-Key '.$this->key,
            // JSON:API headers per docs
            'accept'        => 'application/vnd.api+json',
            'content-type'  => 'application/vnd.api+json',
            'revision'      => $rev,
        ])->timeout(30);
    }

    /* ================= LISTERS ================= */

    /**
     * Get campaigns (newest first), up to $limit, via cursor pagination.
     * NOTE: Get Campaigns requires a filter; use a broad status OR created_at window.
     * Allowed operators list includes status:any(...) + created_at sorting. :contentReference[oaicite:2]{index=2}
     */
   // app/Services/KlaviyoAnalytics.php

public function listCampaigns(
    int $limit = 50,
    ?string $startDate = null,   // 'YYYY-MM-DD'
    ?string $endDate   = null,   // 'YYYY-MM-DD'
    string $channel    = 'email' // or 'sms'
): array
{
    $out = [];
    $url = "$this->api/campaigns";

    $filters = [];
    // channel filter (works via nested field on campaign messages)
    if ($channel) {
        $filters[] = 'equals(messages.channel,"'.addslashes($channel).'")';
    }

    // date range on created_at (UTC)
    if ($startDate) {
        $filters[] = 'greater-or-equal(created_at,"'.$startDate.'T00:00:00+00:00")';
    }
    if ($endDate) {
        $filters[] = 'less-or-equal(created_at,"'.$endDate.'T23:59:59+00:00")';
    }

    // combine filters if needed
    $filter = null;
    if (count($filters) === 1) {
        $filter = $filters[0];
    } elseif (count($filters) > 1) {
        $filter = 'and('.implode(',', $filters).')';
    }

    $params = [
        'sort' => '-created_at',
        'fields[campaign]' => 'name,status,created_at,updated_at',
    ];
    if ($filter) {
        $params['filter'] = $filter; // IMPORTANT: leave raw; Http client will URL-encode
    }

    for ($i = 0; $i < 20 && $url && count($out) < $limit; $i++) {
        $res = $this->http($this->stableRev)->get($url, $params)->json();

        foreach (data_get($res, 'data', []) as $row) {
            $out[] = [
                'id'      => $row['id'] ?? null,
                'name'    => data_get($row, 'attributes.name'),
                'status'  => data_get($row, 'attributes.status'),     // e.g. "Sent"
                'created' => data_get($row, 'attributes.created_at'), // ISO
            ];
            if (count($out) >= $limit) break;
        }
   
        $url = data_get($res, 'links.next');
       
        $params = []; // next link already has the query string
    }
    return $out;
}


    /**
     * Segments with optional profile_count.
     * Get Segments supports filtering/sorting; pagination is 10/page. :contentReference[oaicite:3]{index=3}
     */
    public function listSegments(int $limit = 50): array
    {
        $out = [];
        $url = "{$this->api}/segments";
        // Only fields supported by the list endpoint (no profile_count here)
        $params = [
            'fields[segment]' => 'name,created,updated,is_active,is_starred',
            'sort'            => '-created',
        ];
    
        for ($i = 0; $i < 50 && $url && count($out) < $limit; $i++) {
            $res = $this->http($this->stableRev)->get($url, $params)->json();
     
            foreach (data_get($res, 'data', []) as $row) {
                $out[] = [
                    'id'            => $row['id'] ?? null,
                    'name'          => data_get($row, 'attributes.name'),
                    'created'       => data_get($row, 'attributes.created'),
                    'updated'       => data_get($row, 'attributes.updated'),
                    // no profile_count here (the list endpoint doesn't return it)
                ];
                if (count($out) >= $limit) break;
            }
    
            // cursor pagination
            $url    = data_get($res, 'links.next');
            $params = []; // next link has its own querystring
        }
    
        return $out;
    }
    

    /* ================= METRICS ================= */

    /**
     * Fetch metric ID by name (no server-side name filter is allowed).
     * We page locally and cache. Docs: filterable fields are integration.* only. :contentReference[oaicite:4]{index=4}
     */
    public function getMetricIdByName(string $metricName): ?string
    {
        $cacheKey = 'klv:metric-id:'.Str::lower($metricName);
        return Cache::remember($cacheKey, 3600, function () use ($metricName) {
            $url = "$this->api/metrics";
            $params = ['fields[metric]' => 'name,integration', 'page[size]' => 200];

            for ($i = 0; $i < 20 && $url; $i++) {
                $resp = $this->http($this->stableRev)->get($url, $params)->json();
             
                foreach ($resp['data'] ?? [] as $row) {
                    $name = Arr::get($row, 'attributes.name', '');
                    if (strcasecmp($name, $metricName) === 0) {
                        return $row['id'] ?? null;
                    }
                }
                $url = Arr::get($resp, 'links.next');
                $params = [];
            }
            return null;
        });
    }

    /* ================= SEGMENTS ================= */

    public function getSegmentMembersCount(string $segmentId): int
    {
        $res = $this->http($this->stableRev)->get("$this->api/segments/$segmentId", [
            'additional-fields[segment]' => 'profile_count',
        ])->json();

        return (int) Arr::get($res, 'data.attributes.profile_count', 0);
    }

    /**
     * Segment series: returns date-indexed total_members.
     * Request/endpoint per docs. :contentReference[oaicite:5]{index=5}
     */
    public function getSegmentMembersSeries(string $segmentId, string $start, string $end, string $interval = 'daily'): array
    {
        $payload = [
            'data' => [
                'type' => 'segment-series-report',
                'attributes' => [
                    'timeframe' => [
                        'start' => $start.'T00:00:00+00:00',
                        'end'   => $end.'T23:59:59+00:00',
                    ],
                    'interval'   => $interval,         // daily|weekly|monthly
                    'filter'     => 'equals(segment_id,"'.addslashes($segmentId).'")',
                    'statistics' => ['total_members'], // doc-supported
                ],
            ],
        ];
    
        $res = $this->http($this->stableRev)
            ->post($this->api.'/segment-series-reports', $payload)
          
            ->json();
    
        $dates  = data_get($res, 'data.attributes.date_times', []);
        $values = data_get($res, 'data.attributes.results.0.statistics.total_members', []);
    
        return collect($dates)->map(fn($dt, $i) => [
            'date' => $dt, 'total_members' => (int) ($values[$i] ?? 0),
        ])->all();
    }
    
    /**
     * Campaign KPIs (opens, clicks, rates, optional conversions).
     * Endpoint/shape per docs. :contentReference[oaicite:6]{index=6}
     *
     * @param array  $campaignIds  optional; leave empty to aggregate ALL
     * @param string $start        YYYY-MM-DD (UTC)
     * @param string $end          YYYY-MM-DD (UTC)
     * @param string|null $conversionMetricId e.g. "RESQ6t" (required to return conversions & conversion_rate)
     */
    public function getCampaignKpis(array $campaignIds, string $start, string $end): array
{
    $conversionMetricId = 'X8AY2d'; // Placed Order ID from your Klaviyo account

    $stats = [
        'recipients',
        'delivered',
        'opens',
        'open_rate',
        'clicks',
        'click_rate',
        'conversions',
        'conversion_rate'
    ];

    $filter = null;
    if (!empty($campaignIds)) {
        $parts = array_map(
            fn($id) => 'equals(campaign_id,"'.addslashes($id).'")',
            $campaignIds
        );
        $filter = count($parts) === 1 ? $parts[0] : 'or('.implode(',', $parts).')';
    }

    $payload = [
        'data' => [
            'type' => 'campaign-values-report',
            'attributes' => [
                'statistics' => $stats,
                'timeframe'  => [
                    'start' => $start.'T00:00:00+00:00',
                    'end'   => $end.'T23:59:59+00:00',
                ],
                'filter'               => $filter,
                'conversion_metric_id' => $conversionMetricId,
            ],
        ],
    ];

    $res = $this->http($this->stableRev)
        ->post($this->api.'/campaign-values-reports', $payload)
        ->json();

    $rows = data_get($res, 'data.attributes.results', []);
    return array_map(function ($r) {
        $g = data_get($r, 'groupings', []);
        $s = data_get($r, 'statistics', []);
        return [
            'campaign_id'     => $g['campaign_id'] ?? null,
            'recipients'      => (int) ($s['recipients'] ?? 0),
            'delivered'       => (int) ($s['delivered'] ?? 0),
            'opens'           => (int) ($s['opens'] ?? 0),
            'open_rate'       => isset($s['open_rate']) ? round($s['open_rate'] * 100, 2) : null,
            'clicks'          => (int) ($s['clicks'] ?? 0),
            'click_rate'      => isset($s['click_rate']) ? round($s['click_rate'] * 100, 2) : null,
            'conversions'     => (int) ($s['conversions'] ?? 0),
            'conversion_rate' => isset($s['conversion_rate']) ? round($s['conversion_rate'] * 100, 2) : null,
        ];
    }, $rows);
}

    
}
