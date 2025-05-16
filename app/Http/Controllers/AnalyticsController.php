<?php
namespace App\Http\Controllers;

use App\Models\ShopifyEventLog;
use Illuminate\Http\Request;
use App\Services\MixpanelService;

class AnalyticsController extends Controller
{
    public function track(Request $request)
    {
        $data = $request->all();
        \Log::info('Request Data:', $data); // Log the request data
        $data['platform'] = 'shopify';
        $event = ShopifyEventLog::create($data);

        // Forward to Mixpanel only if it's a funnel_stage
        if (!empty($data['funnel_stage'])) {
            (new MixpanelService())->track($data['anon_id'], $data['event_type'], $data);
        }

        return response()->json(['success' => true]);
    }
}
