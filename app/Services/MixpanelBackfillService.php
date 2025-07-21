<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class MixpanelBackfillService
{
    public function hasAlreadyImported($distinctId, $eventName, $timestamp)
    {
        $query = <<<EOD
    function main() {
      return Events({
        from_date: "2020-01-01",
        to_date: "2025-12-31"
      })
      .filter(function(e) {
        return e.name === "$eventName" && e.properties["distinct_id"] === "$distinctId" && e.time === $timestamp;
      });
    }
    EOD;
    
        $response = Http::withBasicAuth(env('MIXPANEL_SECRET'), '')
            ->asForm()
            ->post('https://mixpanel.com/api/2.0/jql', [
                'script' => $query,
            ]);
    
        $data = $response->json();
    
        if (!is_array($data)) {
            \Log::error('Mixpanel JQL failed or returned invalid JSON', [
                'response' => $response->body(),
                'status' => $response->status(),
                'distinct_id' => $distinctId,
                'timestamp' => $timestamp,
            ]);
            return false; // fail open (assume event missing)
        }
    
        return count($data) > 0;
    }
    
    
}
