<?php
namespace App\Http\Controllers;

use App\Models\ShopifyEventLog;
use Illuminate\Http\Request;
use App\Services\MixpanelService;

class AnalyticsController extends Controller
{
    public function track(Request $request)
    {
        $validated = $request->validate([
            'anon_id' => 'required|string',
            'event_type' => 'required|string',
            'funnel_stage' => 'nullable|string',
            'element' => 'nullable|string',
            'page_url' => 'required|string',
            'page_type' => 'nullable|string',
            'referrer' => 'nullable|string',
            'timestamp' => 'nullable|date',
            'focus_time' => 'nullable|numeric',
            'utm' => 'nullable|array',
            'screen' => 'nullable|array',
            'user_agent' => 'nullable|string',
        ]);

        $validated['platform'] = 'shopify';
        $event = ShopifyEventLog::create($validated);

        // Forward to Mixpanel only if it's a funnel_stage
        if (!empty($validated['funnel_stage'])) {
            (new MixpanelService())->track($validated['anon_id'], $validated['event_type'], $validated);
        }

        return response()->json(['success' => true]);
    }
}
