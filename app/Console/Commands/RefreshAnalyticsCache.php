<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use App\Services\AnalyticsService;

class RefreshAnalyticsCache extends Command
{
    protected $signature = 'cache:refresh-analytics';
    protected $description = 'Refresh the analytics cache';

    protected $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        parent::__construct();
        $this->analyticsService = $analyticsService;
    }

    public function handle()
    {
        $baseUrl = 'https://academyofdjs.com';

        // Fetch and cache the user journey data
        $userJourneys = $this->analyticsService->fetchUserJourneys($baseUrl);
        Cache::put('userJourneys', $userJourneys);

        // Create and cache the journey map
        $journeyMap = $this->analyticsService->createJourneyMap($userJourneys);
        Cache::put('journeyMap', $journeyMap);

        // Calculate and cache the metrics
        $metrics = $this->analyticsService->calculateMetrics($userJourneys, $journeyMap);
        Cache::put('metrics', $metrics);

        // Segment and cache the user data
        $segments = $this->analyticsService->segmentUsers($userJourneys);
        Cache::put('userSegments', $segments);

        $this->info('Analytics cache refreshed successfully.');
    }
}
