<?php

namespace App\Services;
use Carbon\Carbon;
use DB;
use App\Models\Sale;
use App\Models\UserEvent;
class AnalyticsService
{
    public function __construct()
    {
        //
    }

    public function calculateMetrics($userJourneys, $journeyMap)
    {
        $focusDurations = [];
        $landingPages = [];
        $exitPages = [];
        $singlePageSessions = 0;
        $totalSessions = 0;
        $uniqueVisitors = [];
        $userPaths = [];
        $extractedEvents = [];
    
        // Group user events by user_id to handle each user's journey separately
        $groupedUserJourneys = $userJourneys->groupBy('user_id');

        foreach ($groupedUserJourneys as $userId => $userEvents) {
         
            // Filter out duplicate entries for each user based on page_url and start_time
            $filteredUserJourneys = $userEvents->unique(function ($item) {
                $pageUrl = $item->page_url ?? 'unknown_page';
                $startTime = $item->start_time ?? 'unknown_time';
        
                return $pageUrl . $startTime;
            });
    
            foreach ($filteredUserJourneys as $visit) {
                $pageUrl = $visit->page_url;
                $focusTime = $visit->focus_time;
    
                if (!isset($userPaths[$userId])) {
                    $userPaths[$userId] = [];
                    $landingPages[] = $pageUrl;
                    $totalSessions++;
                }
    
                $userPaths[$userId][] = $pageUrl;
    
                if (count($userPaths[$userId]) == 1) {
                    $focusDurations[$userId] = 0;
                }
                $focusDurations[$userId] += $focusTime;
    
                if (!in_array($userId, $uniqueVisitors)) {
                    $uniqueVisitors[] = $userId;
                }
            }
    
            // Calculate exitPages and single page sessions
            $exitPages[] = end($userPaths[$userId]);
            if (count($userPaths[$userId]) == 1) {
                $singlePageSessions++;
            }
        }
    
        // Calculate additional metrics
        $bounceRate  = ($singlePageSessions / $totalSessions) * 100;
        $averageFocusDuration = array_sum($focusDurations) / $totalSessions;
        $topLandingPages = array_count_values($landingPages);
        arsort($topLandingPages);
        $topExitPages = array_count_values($exitPages);
        arsort($topExitPages);
        $totalPageViews = array_sum(array_column($journeyMap, 'visits'));
        $uniquePagesVisited = count(array_unique($landingPages));
        $averagePageViewsPerSession = $totalPageViews / $totalSessions;
        $totalUniqueVisitors = count($uniqueVisitors);
    
        // Extract event data
        $extractedEvents = [];
    
        // Fetch and process events where event_type is "click"
        $events = UserEvent::select('element', 'event_type')
            ->where('event_type', '=', 'click')
            ->get();
    
        foreach ($events as $event) {
            // Extract href links and their visible text from the element column
            preg_match_all('/<a[^>]+href="([^"]+)"[^>]*>(.*?)<\/a>/', $event->element, $matches);
            foreach ($matches[1] as $index => $href) {
                $linkText = strip_tags($matches[2][$index]); // Get the visible text (strip out any HTML tags inside)
                $key = 'link:' . $linkText . ' (' . $href . ')'; // Combine visible text and href in the label
                if (isset($extractedEvents[$key])) {
                    $extractedEvents[$key]++;
                } else {
                    $extractedEvents[$key] = 1;
                }
            }
    
            // Extract button names from the element column
            preg_match_all('/<button[^>]*>(.*?)<\/button>/', $event->element, $buttonMatches);
            foreach ($buttonMatches[1] as $buttonName) {
                $buttonText = strip_tags($buttonName); // Get the visible text (strip out any HTML tags inside)
                $key = 'button:' . $buttonText;
                if (isset($extractedEvents[$key])) {
                    $extractedEvents[$key]++;
                } else {
                    $extractedEvents[$key] = 1;
                }
            }
        }
    
        arsort($extractedEvents);
    
        return [
            'events' => $extractedEvents,
            'bounceRate' => $bounceRate,
            'averageFocusDuration' => $averageFocusDuration,
            'topLandingPages' => $topLandingPages,
            'topExitPages' => $topExitPages,
            'totalPageViews' => $totalPageViews,
            'uniquePagesVisited' => $uniquePagesVisited,
            'averagePageViewsPerSession' => $averagePageViewsPerSession,
            'totalUniqueVisitors' => $totalUniqueVisitors,
            'landingPages' => $landingPages
        ];
    }
    public function fetchUserJourneys($baseUrl)
    {
        return DB::table('user_events')
            ->select(
                'user_id',
                DB::raw('
                    CASE 
                        WHEN page_url LIKE "%fbclid%" THEN 
                            SUBSTRING_INDEX(SUBSTRING_INDEX(page_url, "fbclid", 1), "?", 1) 
                        ELSE 
                            SUBSTRING_INDEX(page_url, "?", 1) 
                    END as cleaned_url'),
                DB::raw('MIN(start_time) as start_time'), 
                DB::raw('MIN(end_time) as end_time'),
                DB::raw('SUM(focus_time) as focus_time'),
                'created_at'
            )
            ->whereNotIn('user_id', $this->excludeUsers())
            ->groupBy('user_id', 'created_at', 'cleaned_url')
            ->orderBy('user_id')
            ->orderBy('start_time')
            ->get()
            ->filter(function($event, $key) use ($baseUrl) {
                // Get previous event
                
                $previousEvent = $this->getPreviousEvent($event->user_id, $event->created_at);
    
                // Check if it's a reload (e.g., short time difference, same URL)
                $isReload = $previousEvent && $event->cleaned_url === $previousEvent->page_url && 
                            strtotime($event->start_time) - strtotime($previousEvent->end_time) < 2; 
    
                return !$isReload;
            })
            ->map(function($event) use ($baseUrl) {
                $event->page_url = $event->cleaned_url;
                unset($event->cleaned_url);
                return $event;
            });
    }
    
    public function excludeUsers(){
        $excludedUsers = ['user_5edhgpi3x', 'user_4vt4pqv8x', 'user_udztby6hd', 'user_z3agshteg'];
        return $excludedUsers;
    }


    public function createJourneyMap($userJourneys)
    {
        $journeyMap = [];
        $userPaths = [];
    
        foreach ($userJourneys as $visit) {
            $userId = $visit->user_id;
            $pageUrl = $visit->page_url;
    
            if (!isset($userPaths[$userId])) {
                $userPaths[$userId] = [];
            }
    
            $userPaths[$userId][] = $pageUrl;
    
            if (!isset($journeyMap[$pageUrl])) {
                $journeyMap[$pageUrl] = [
                    'page' => $pageUrl,
                    'visits' => 0,
                    'nextPages' => [],
                    'total_focus_time' => 0,
                    'date' => $visit->start_time
                ];
            }
    
            $journeyMap[$pageUrl]['visits']++;
            $journeyMap[$pageUrl]['total_focus_time'] += $visit->focus_time;
        }
    
        // Calculate nextPages
        foreach ($userPaths as $userId => $paths) {
            for ($i = 0; $i < count($paths) - 1; $i++) {
                $currentPage = $paths[$i];
                $nextPage = $paths[$i + 1];
                if (!isset($journeyMap[$currentPage]['nextPages'][$nextPage])) {
                    $journeyMap[$currentPage]['nextPages'][$nextPage] = 0;
                }
                $journeyMap[$currentPage]['nextPages'][$nextPage]++;
            }
        }
    
        return $journeyMap;
    }


    public function segmentUsers($userJourneys)
    {
        $newUsers = [];
        $returningUsers = [];
        $engagedUsers = [];
        $bouncedUsers = [];
        $convertedUsers = [];
        $userFirstEvents = [];
    
        foreach ($userJourneys as $visit) {
            $userId = $visit->user_id;
            $pageUrl = $visit->page_url;
            $focusTime = $visit->focus_time;
            $createdAt = Carbon::parse($visit->created_at);
    
            // Track the first event for each user and categorize new vs returning users
            if (!isset($userFirstEvents[$userId])) {
                $userFirstEvents[$userId] = $createdAt;
                $newUsers[] = $userId;
            } else {
                $timeDifference = $createdAt->diffInHours($userFirstEvents[$userId]);
                if ($timeDifference > 24) {
                    if (!in_array($userId, $returningUsers)) {
                        $returningUsers[] = $userId;
                        $newUsers = array_diff($newUsers, [$userId]); // Remove from newUsers if present
                    }
                }
            }
    
            // Determine engaged users based on focus time
            if ($focusTime > 200 && !in_array($userId, $engagedUsers)) {
                $engagedUsers[] = $userId;
            }
    
            // Determine bounced users based on the number of events
            $userEvents = $userJourneys->where('user_id', $userId);
            if ($userEvents->count() === 1 && !in_array($userId, $bouncedUsers)) {
                $bouncedUsers[] = $userId;
            }
    
            // Determine converted users based on the page URL
            if ($pageUrl === '/thank-you' && !in_array($userId, $convertedUsers)) {
                $convertedUsers[] = $userId;
            }
        }
    
        return [
            'newUsers' => count($newUsers),
            'returningUsers' => count($returningUsers),
            'engagedUsers' => count($engagedUsers),
            'bouncedUsers' => count($bouncedUsers),
            'convertedUsers' => count($convertedUsers),
        ];
    }
    public function getPreviousEvent($userId, $createdAt)
    {
        return DB::table('user_events')
            ->where('user_id', $userId)
            ->where('created_at', '<', $createdAt)
            ->orderBy('created_at', 'desc')
            ->first();
    }



}
