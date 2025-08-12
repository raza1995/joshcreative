<?php

namespace App\Http\Controllers;

use App\Services\KlaviyoAnalytics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class KlaviyoKpiController extends Controller
{
    public function __construct(private KlaviyoAnalytics $kl) {}

    public function index(Request $request)
    {
        return view('klaviyo.kpis');
    }

    public function options(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date'   => 'nullable|date_format:Y-m-d',
            'channel'    => 'nullable|in:email,sms',
        ]);
    
        $start   = $request->query('start_date');
        $end     = $request->query('end_date');
        $channel = $request->query('channel', 'email');
    
        return response()->json([
            'campaigns' => $this->kl->listCampaigns(100, $start, $end, $channel),
            'segments'  => $this->kl->listSegments(100),
        ]);
    }

    public function campaignKpis(Request $request)
    {
        
        $data = $request->validate([
            'campaign_ids'   => 'array',
            'campaign_ids.*' => 'string',
            'start_date'     => 'required|date_format:Y-m-d',
            'end_date'       => 'required|date_format:Y-m-d|after_or_equal:start_date',
            // optional override; if not provided, we’ll use env/default inside
            'conversion_metric_id' => 'nullable|string',
        ]);

        try {
            // Use request override → env → fallback literal
            $metricId = $data['conversion_metric_id']
                ?? env('KLAVIYO_CONVERSION_METRIC_ID', 'X8AY2d');

            // Call your current service signature (3 args) OR (4 args) if you updated to accept optional metric.
            // If your service HARCODES the metric, use the 3-arg version:
            // $rows = $this->kl->getCampaignKpis($data['campaign_ids'] ?? [], $data['start_date'], $data['end_date']);

            // If you updated service to accept metric (recommended), use this 4-arg call:
            $rows = $this->kl->getCampaignKpis(
                $data['campaign_ids'] ?? [],
                $data['start_date'],
                $data['end_date'],
                $metricId
            );
           
            // Attach campaign names (nice cards)
            $nameMap = collect($this->kl->listCampaigns(200))
                ->keyBy('id')
                ->map(fn ($c) => $c['name'] ?? null)
                ->all();
                
            foreach ($rows as &$r) {
                $r['name'] = $nameMap[$r['campaign_id']] ?? $r['campaign_id'];
            }
           
            return response()->json(['rows' => $rows]);
        } catch (\Throwable $e) {
            Log::error('Klaviyo campaign KPIs error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Failed to fetch campaign KPIs'], 422);
        }
    }

    public function segmentKpis(Request $request)
    {
        $data = $request->validate([
            'start_date'   => ['required','date'],
            'end_date'     => ['required','date','after_or_equal:start_date'],
            'segment_ids'  => ['array'],
            'segment_ids.*'=> ['string'],
        ]);

        try {
            $segments = $this->kl->listSegments(200);
            $byId = collect($segments)->keyBy('id');

            $rows = [];
            foreach (($data['segment_ids'] ?? []) as $sid) {
                $name   = $byId->get($sid)['name'] ?? $sid;
                $nowCnt = $this->kl->getSegmentMembersCount($sid);
                // use 'daily' to match service signature
                $series = $this->kl->getSegmentMembersSeries($sid, $data['start_date'], $data['end_date'], 'daily');

                $rows[] = [
                    'segment_id'   => $sid,
                    'name'         => $name,
                    'members_now'  => $nowCnt,
                    // service returns [{date, total_members}]
                    'series'       => $series,
                ];
            }

            return response()->json(['rows' => $rows]);
        } catch (\Throwable $e) {
            Log::error('Klaviyo segment KPIs error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Failed to fetch segment KPIs'], 422);
        }
    }
}
