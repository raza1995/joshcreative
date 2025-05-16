<?php
namespace App\Http\Controllers;

use App\Models\ShopifyEventLog;
use Illuminate\Http\Request;
use App\Services\MixpanelService;

class AnalyticsController extends Controller
{
    public function track(Request $request)
    {
        // Fallback for sendBeacon text/plain
        $data = $request->all();
    
        if (empty($data)) {
            $raw = $request->getContent();
            $data = json_decode($raw, true);
        }
    
        \Log::info('📥 Event Payload:', $data);
    
        if (empty($data['anon_id'])) {
            return response()->json(['error' => 'Missing anon_id'], 422);
        }
    
        $data['platform'] = 'shopify';
    
        $event = ShopifyEventLog::create($data);
    
        if (!empty($data['funnel_stage'])) {
            (new MixpanelService())->track($data['anon_id'], $data['event_type'], $data);
        }
    
        return response()->json(['success' => true]);
    }
    
}
