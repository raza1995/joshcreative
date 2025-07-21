<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Mixpanel;

class MixpanelService
{
    protected $mixpanel;

    public function __construct()
    {
        $this->mixpanel = Mixpanel::getInstance(env('MIXPANEL_TOKEN'));
    }

    public function trackEvent($event, $properties)
    {
        $this->mixpanel->track($event, $properties);
    }

    public function identifyUser($userId, $properties = [])
    {
        $this->mixpanel->identify($userId);
        $this->mixpanel->people->set($userId, $properties);
    }

    public function trackBulkEvents($events)
    {
        foreach ($events as $event) {
            $this->trackEvent($event['event_name'], $event['properties']);
        }
    }


    public function trackUserEvent($userId, $eventName, $properties = [])
    {   
        $this->mixpanel->identify($userId);
        if (isset($properties['$device_id'])) {
            $deviceId = $properties['$device_id'];
            unset($properties['$device_id']);
        } else {
            $deviceId = null;
        }
        
        
        
        $this->mixpanel->track($eventName, array_merge([
            'distinct_id' => $userId,
            '$user_id' => $userId,
            '$device_id' => $deviceId, 
        ], $properties));
        
    }
    

    public function alias($anonId, $userId)
    {
        $this->mixpanel->track('$create_alias', [
            'distinct_id' => $anonId,
            'alias' => $userId,
            'token' => env('MIXPANEL_TOKEN')
        ]);
    }
    
    
    /**
     * Custom contextual tracking wrapper to include funnel metadata
     */
    public function track($distinctId, $event, array $properties = [])
    {
        $props = array_merge([
            'distinct_id'    => $distinctId,
            'funnel_stage'   => $properties['funnel_stage'] ?? null,
            'page_url'       => $properties['page_url'] ?? null,
            'page_type'      => $properties['page_type'] ?? null,
            'referrer'       => $properties['referrer'] ?? null,
            'utm_source'     => $properties['utm']['utm_source'] ?? null,
            'utm_medium'     => $properties['utm']['utm_medium'] ?? null,
            'utm_campaign'   => $properties['utm']['utm_campaign'] ?? null,
            'screen_width'   => $properties['screen']['width'] ?? null,
            'screen_height'  => $properties['screen']['height'] ?? null,
            'platform'       => $properties['platform'] ?? 'shopify',
        ], $properties);

        $this->mixpanel->identify($distinctId);
        $this->mixpanel->track($event, $props);
    }
    public function importEvent(array $event)
    {
        // Ensure required structure
        if (!isset($event['event']) || !isset($event['properties']['time']) || !isset($event['properties']['distinct_id'])) {
            \Log::warning('Mixpanel import: Missing required fields', ['event' => $event]);
            return false;
        }
    
        // Enforce token from .env
        $event['properties']['token'] = env('MIXPANEL_TOKEN');
    
        $data = base64_encode(json_encode([$event]));
    
        $response = Http::withOptions([
            'verify' => false, // remove if SSL is properly configured
        ])->get('https://api.mixpanel.com/import/', [
            'data' => $data,
            'verbose' => 1,
        ]);
    
        if ($response->failed()) {
            \Log::error('❌ Mixpanel import failed', [
                'event' => $event,
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return false;
        }
    
        \Log::info('✅ Mixpanel import success', ['distinct_id' => $event['properties']['distinct_id'], 'time' => $event['properties']['time']]);
    
        return $response->json();
    }
    


}
