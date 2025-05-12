<?php

namespace App\Services;

use Mixpanel;

class MixpanelServiceMycolean
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
    $this->mixpanel->track($eventName, $properties);
}

}
