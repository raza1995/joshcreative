<?php

namespace App\Services;

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
        $this->mixpanel->track($eventName, array_merge([
            'distinct_id' => $userId,
        ], $properties));
    }
    public function alias($newId, $originalId)
    {
        // Identify as the anonymous ID first
        $this->mixpanel->identify($originalId);
    
        // Send alias event to associate anonymous with known ID
        $this->mixpanel->track('$create_alias', [
            'distinct_id' => $originalId,
            'alias' => $newId,
        ]);
    }
    
    public function track($distinctId, $event, array $properties = [])
    {
        $props = [
            'funnel_stage' => $properties['funnel_stage'] ?? null,
            'page_url' => $properties['page_url'] ?? null,
            'page_type' => $properties['page_type'] ?? null,
            'referrer' => $properties['referrer'] ?? null,
            'utm_source' => $properties['utm']['utm_source'] ?? null,
            'utm_medium' => $properties['utm']['utm_medium'] ?? null,
            'utm_campaign' => $properties['utm']['utm_campaign'] ?? null,
            'screen_width' => $properties['screen']['width'] ?? null,
            'screen_height' => $properties['screen']['height'] ?? null,
            'platform' => $properties['platform'] ?? 'shopify',
        ];
        $props['distinct_id'] = $distinctId;
        $this->mixpanel->track($event, $props);
    }

}
