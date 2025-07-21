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
    
        $response = Http::withBasicAuth('f9cd5c927ad2cdc5ddbababef49a3220', '')
            ->asForm()
            ->post('https://mixpanel.com/api/2.0/jql', [
                'script' => $query,
            ]);
    
        return count($response->json()) > 0;
    }
    
}
