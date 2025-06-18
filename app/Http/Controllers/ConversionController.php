<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\ShopifyOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ConversionController extends Controller
{
    public function landingSiteConversions(Request $request)
    {
        $start = Carbon::parse($request->input('start_date', now()->subDays(7)->toDateString()))
        ->startOfDay();
$end   = Carbon::parse($request->input('end_date', now()->toDateString()))
        ->endOfDay();

if ($start->gt($end)) {
return back()->withErrors(['date' => 'Start date must be before end date.']);
}

// The Segmentation API needs YYYY-MM-DD (no time)
$from = $start->toDateString();
$to   = $end->toDateString();

/* ─── 2. Query Mixpanel (same logic, just dynamic dates) ─── */
$viewsRes = Http::withBasicAuth(env('SECRET_MIXPANEL'), '')
->get('https://mixpanel.com/api/query/segmentation', [
 'event'     => '$mp_web_page_view',
 'from_date' => $from,
 'to_date'   => $to,
 'on'        => 'properties["$initial_referring_domain"]',
 'type'      => 'unique',
 'where'     => 'properties["$device_id"] != ""',
]);

$convRes = Http::withBasicAuth(env('SECRET_MIXPANEL'), '')
->get('https://mixpanel.com/api/query/segmentation', [
 'event'     => 'checkout_completed',
 'from_date' => $from,
 'to_date'   => $to,
 'on'        => 'properties["$initial_referring_domain"]',
 'where'     => 'properties["$device_id"] != ""',
]);

if (!$viewsRes->ok() || !$convRes->ok()) {
return response()->json([
 'error'   => 'Failed to fetch Mixpanel data',
 'details' => [
     'views' => $viewsRes->json(),
     'conv'  => $convRes->json(),
 ],
], 500);
}

/* ─── 3. Flatten, merge & compute rates (helper unchanged) ─── */
$views = $this->flattenSegmentation($viewsRes->json());
$conv  = $this->flattenSegmentation($convRes->json());

$stats = collect($views)
->merge($conv)
->map(function ($val, $domain) use ($views, $conv) {
 $visits      = $views[$domain] ?? 0;
 $conversions = $conv[$domain] ?? 0;
 return [
     'domain'          => $domain ?: 'unknown',
     'visits'          => $visits,
     'conversions'     => $conversions,
     'conversion_rate' => $visits ? round(($conversions / $visits) * 100, 2) : 0.00,
 ];
})
->sortByDesc('conversions')
->values();

/* ─── 4. Return view with data ─── */
return view('conversion.landing_sites', [
'conversions' => $stats,
]);

    }

    
    
    private function flattenSegmentation(array $resp): array
    {
        $out     = [];
        $values  = $resp['data']['values'] ?? [];
    
        foreach ($values as $domain => $dateSeries) {
            // $dateSeries is a map of 'YYYY-MM-DD' => count
            $out[$domain ?: 'unknown'] = array_sum($dateSeries);
        }
        return $out;
    }
  
}
