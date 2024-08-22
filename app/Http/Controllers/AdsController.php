<?php

namespace App\Http\Controllers;

use App\Services\MetaAdsService as ServicesMetaAdsService;
use Illuminate\Http\Request;
use MetaAdsService;

class AdsController extends Controller
{
    protected $metaAdsService;

    // public function __construct(ServicesMetaAdsService $metaAdsService)
    // {
    //     $this->metaAdsService = $metaAdsService;
    // }

    // public function showAdPerformance($adAccountId)
    // {
    //     $data = $this->metaAdsService->getAdInsights($adAccountId);
  
    //     return view('ads.performance', compact('data'));
    // }
}
