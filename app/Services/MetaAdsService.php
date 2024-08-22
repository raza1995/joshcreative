<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class MetaAdsService
{
    protected $appId;
    protected $appSecret;
    protected $accessToken;

    public function __construct()
    {
        $this->appId = env('META_APP_ID');
        $this->appSecret = env('META_APP_SECRET');
        $this->accessToken = env('META_SHORT_LIVED_ACCESS_TOKEN');
    }

    // Get Ad Insights
    public function getAdInsights($adAccountId)
    {
        $endpoint = "https://graph.facebook.com/v20.0/act_1648199708815148/insights";
        $params = [
            'fields' => 'impressions,reach,ctr,cpc',
            'date_preset' => 'last_90d',
            'access_token' => $this->accessToken,
        ];
        $response = Http::get($endpoint, $params);
        return $response->json();
    }
}
