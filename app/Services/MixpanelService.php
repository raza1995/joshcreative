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
        $this->mixpanel->people->set($userId, $properties);
    }
}
