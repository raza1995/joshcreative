<?php

namespace App\Http\Controllers;

use App\DataTables\SalesDataDataTable;
use App\Imports\SalesImport;
use App\Models\Sale;
use App\Models\UserEvent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class SalesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(SalesDataDataTable $dataTable)
    {
        if (Auth::user()) {
            ini_set('memory_limit', '1024M');

            return $dataTable->render('sales.index');
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Sale $sale)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Sale $sale)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Sale $sale)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Sale $sale)
    {
        //
    }

    public function teachableHandleWebhook(Request $request)
    {
        $payload = $request->json()->all();

        Log::info('New sale created first: ' . json_encode($payload));

        $mappedData = [
            'user_id' => $payload['object']['user_id'],
            'total_amount' => number_format(($payload['object']['product']['final_price'] ?? 0) / 100, 2, '.', ''),
            'email' => $payload['object']['user']['email'] ?? '',
            'name' => $payload['object']['user']['name'] ?? '',
            'promo_code' => $payload['object']['coupon']['code'] ?? '',
            'status' => 'Purchased',
            'sales_event_id' => $payload['object']['id'],
            'price' => number_format(($payload['object']['product']['price'] ?? 0) / 100, 2, '.', '')
        ];
        Log::info('mappedData: ' . json_encode($mappedData));

        $existingSale = Sale::where('email', $mappedData['email'] ?? '')->orderBy('created_at', 'desc')->first();
        Log::info('existingSale: ', ['existingSale' => $existingSale]);

        if ($existingSale) {
            Log::info('Updating existing sale', ['sales_id' => $existingSale->id]);

            $existingSale->update([
                'total_amount' => $mappedData['total_amount'] ?? $existingSale->total_amount,
                'name' => $mappedData['name'] ?? $existingSale->name,
                'price' => $mappedData['price'] ?? $existingSale->price,
                'promo_code' => $mappedData['promo_code'] ?? $existingSale->promo_code,
                'status' => $mappedData['status'] ?? $existingSale->status,
                'price' => $mappedData['price'] ?? $existingSale->price,
                'user_id' => $mappedData['user_id'] ?? $existingSale->user_id,
                'sales_event_id' => $mappedData['sales_event_id'],
                'purchase_count' => $existingSale->purchase_count + 1
            ]);
        } else {
            Log::info('Creating new sale', ['email' => $mappedData['email'] ?? '']);

            Sale::create(array_merge($mappedData, ['purchase_count' => 1]));
        }
    }
    // public function salesDataWebHook(Request $request)
    // {   
    //     $payload = $request->json()->all();

    //     Log::info('New sale created second: ' . json_encode($payload));

    //     Sale::create([
    //         'dj_user_id' => $payload['dj_user_id'] ?? '',
    //         'ip_address' => $payload['ip_address'] ?? '',
    //         'utm_source' => $payload['utm_source'] ?? '',
    //         'email' => $payload['email'] ?? '',
    //         'user_id' => $payload['user_id'] ?? '',
    //         'status' => 'added_to_cart',
    //         'project_id' => $payload['project_id'] ?? ''
    //     ]);
    // }

    public function salesDataWebHook(Request $request)
    {
        $payload = $request->json()->all();


        Log::info('New sale created second: ' . json_encode($payload));
        $existingSale = Sale::where('sales_id', $payload['sales_id'] ?? '')
            ->first();

        if ($existingSale) {
            $existingSale->update([
                'utm_source' => $payload['utm_source'] ?? '',
                'user_id' => $payload['user_id'] ?? '',
                'ip_address' => $payload['ip_address'] ?? ''
            ]);
        } else {
            $existingSale = Sale::create([
                'dj_user_id' => $payload['dj_user_id'] ?? '',
                'ip_address' => $payload['ip_address'] ?? '',
                'utm_source' => $payload['utm_source'] ?? '',
                'email' => $payload['email'] ?? '',
                'user_id' => $payload['user_id'] ?? '',
                'project_id' => $payload['project_id'] ?? '',
                'sales_id' => $payload['sales_id'] ?? '',
                'status' => $payload['status'] ?? '',
            ]);
        }
    }

    public function uploadSalesData(Request $request)
    {
        // Validate the uploaded file

        // Load the uploaded file
        $file = $request->file('sales_file');

        // Get the D folder path in $file


        if (!$file) {
            Log::error('No file was uploaded.');
            return back()->with('error', 'No file was uploaded.');
        }

        Log::info('Original file name: ' . $file->getClientOriginalName());


        $collection = Excel::toCollection(new SalesImport, $file);

        foreach ($collection->first() as $row) {
            Sale::create([
                'project_id' => 1,  // Placeholder value
                'name' => $row['purchaser'] ?? '',
                'user_id' => (string) $row['id'] ?? '',
                'salesname' => '',  // Leaving salesname empty
                'email' => $row['purchaser_email'] ?? '',
                'ip_address' => '',  // Placeholder value
                'utm_source' => '',  // Placeholder value
                'total_amount' => $row['net_charge_usd'] ?? 0,
                'earned_commission' => 0,  // Placeholder value
                'created_at' => $row['purchased_at'] ? Carbon::parse($row['purchased_at'])->format('Y-m-d H:i:s') : null,
                'updated_at' => $row['purchased_at'] ? Carbon::parse($row['purchased_at'])->format('Y-m-d H:i:s') : null,
                'dj_user_id' => '',  // Placeholder value
                'price' => $row['listed_price'] ?? 0,
                'promo_code' => $row['coupon_code'] ?? '',
                'sales_event_id' => $row['sale_id'] ?? '',
                'status' => 'Purchased',  // Setting status to 'Purchased'
            ]);
        }

        return back()->with('success', 'Sales data has been uploaded and processed successfully.');
    }


    public function rev()
    {
        if (!Auth::check()) {
            return redirect('login');
        }

        // Fetch daily, monthly, and yearly revenue
        $dailyRevenue = $this->getRevenueBy('day');
        $monthlyRevenue = $this->getRevenueBy('month');
        $yearlyRevenue = $this->getRevenueBy('year');

        // Calculate current month's revenue
        $currentMonthRevenue = $this->getCurrentMonthRevenue();
        $pageVisits = DB::table('pages')
        ->select(
            DB::raw('
                CASE 
                    WHEN url LIKE "%fbclid%" THEN 
                        SUBSTRING_INDEX(SUBSTRING_INDEX(url, "fbclid", 1), "?", 1) 
                    ELSE 
                        SUBSTRING_INDEX(url, "??", 1) 
                END as path'), 
            DB::raw('SUM(views) as views'), 
            DB::raw('SUM(total_stay_duration) as total_stay_duration')
        )
        ->groupBy('path')
        ->orderBy('views', 'desc')
        ->get()
        ->map(function ($visit) {
            $visit->avg_stay_duration = ($visit->total_stay_duration / 60) / $visit->views;
            return $visit;
        });





        return view('welcome', compact('dailyRevenue', 'monthlyRevenue', 'yearlyRevenue', 'currentMonthRevenue', 'pageVisits'));
    }

    private function getRevenueBy($interval)
    {
        $dateFormat = [
            'day' => '%Y-%m-%d',
            'month' => '%Y-%m',
            'year' => '%Y',
        ];

        // Step 1: Fetch unique sales_event_id values and related data
        $uniqueSales = Sale::select('sales_event_id', 'total_amount', DB::raw("DATE_FORMAT(created_at, '{$dateFormat[$interval]}') as date"))
            ->get()
            ->unique('sales_event_id');

        // Step 2: Group by date and sum the total_amount
        $groupedSales = $uniqueSales->groupBy('date')->map(function ($row) {
            return [
                'date' => $row->first()->date,
                'total' => $row->sum('total_amount'),
            ];
        })->values();

        return $groupedSales;
    }

    private function getCurrentMonthRevenue()
    {
        // Step 1: Fetch unique sales_event_id values for the current month
        $uniqueSales = Sale::select('sales_event_id', 'total_amount')
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->get()
            ->unique('sales_event_id');

        // Step 2: Sum the total_amount for these unique sales records
        return $uniqueSales->sum('total_amount');
    }
    public function showJourney()
    {
        $baseUrl2 = 'https://academyofdjs.com';
        
        // Fetch the user journey data
        $userJourneys = $this->fetchUserJourneys($baseUrl2);
    
        // Process data to create a journey map and additional metrics
        $journeyMap = $this->createJourneyMap($userJourneys);
        $metrics = $this->calculateMetrics($userJourneys, $journeyMap);
        $segments = $this->segmentUsers($userJourneys);

        return view('sales.journey', array_merge($metrics, [
            'journeyMap' => $journeyMap,
            'landingPages' => array_unique($metrics['landingPages']),
            'segments' => $segments
        ]));
    }
    
    private function fetchUserJourneys($baseUrl)
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
                'start_time', 
                'end_time',
                'focus_time'
            )
            ->whereNotIn('user_id', $this->excludeUsers())
            ->orderBy('user_id')
            ->orderBy('start_time')
            ->get()
            ->map(function($event) use ($baseUrl) {
                $event->page_url = $event->cleaned_url;
                unset($event->cleaned_url);
                return $event;
            });
    }
    
    private function createJourneyMap($userJourneys)
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
    
    private function calculateMetrics($userJourneys, $journeyMap)
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
    
    
    
    private function segmentUsers($userJourneys)
    {
        $newUsers = [];
        $returningUsers = [];
        $engagedUsers = [];
        $bouncedUsers = [];
        $convertedUsers = []; // Define what conversion means for your application
    
        foreach ($userJourneys as $visit) {
            $userId = $visit->user_id;
            $pageUrl = $visit->page_url;
            $focusTime = $visit->focus_time;
    
            // Check if user is new or returning
            if (!in_array($userId, $newUsers) && !in_array($userId, $returningUsers)) {
                $newUsers[] = $userId;
            } elseif (in_array($userId, $returningUsers)) {
                $returningUsers[] = $userId;
            }
    
            // Check if user is engaged
            if ($focusTime > 300) { // Example: more than 5 minutes of focus time
                $engagedUsers[] = $userId;
            }
    
            // Check if user bounced
            if (count(array_filter($userJourneys->toArray(), function($item) use ($userId) { return $item->user_id == $userId; })) == 1) {
                $bouncedUsers[] = $userId;
            }
    
            // Check if user converted
            // Define your conversion criteria, e.g., visiting a specific page
            if ($pageUrl == '/thank-you') { // Example: user visited the thank you page
                $convertedUsers[] = $userId;
            }
        }
    
        return [
            'newUsers' => count(array_unique($newUsers)),
            'returningUsers' => count(array_unique($returningUsers)),
            'engagedUsers' => count(array_unique($engagedUsers)),
            'bouncedUsers' => count(array_unique($bouncedUsers)),
            'convertedUsers' => count(array_unique($convertedUsers)),
        ];
    }
    

    private function excludeUsers(){
        $excludedUsers = ['user_5edhgpi3x', 'user_4vt4pqv8x', 'user_udztby6hd', 'user_z3agshteg'];
        return $excludedUsers;
    }
    

    public function getUserJourney($userId)
    {
        $userJourneys = UserEvent::where('user_events.user_id', $userId)
            ->join('sales', 'sales.dj_user_id', '=', 'user_events.user_id')
            ->orderBy('start_time', 'asc')
            ->select('user_events.*', 'sales.email', 'sales.name')
            ->get();
    
        $filteredUserJourneys = $userJourneys->unique(function ($item) {
            return $item['page_url'] . $item['start_time'];
        });
    
        $journeyMap = [];
        $previousPage = null;
    
        foreach ($filteredUserJourneys as $event) {
            $pageUrl = $event->page_url;
    
            if (!isset($journeyMap[$pageUrl])) {
                $journeyMap[$pageUrl] = [
                    'visits' => 0,
                    'total_focus_time' => 0,
                    'nextPages' => [],
                    'click_events' => [], // Initialize click events
                ];
            }
    
            $journeyMap[$pageUrl]['visits']++;
            $journeyMap[$pageUrl]['total_focus_time'] += $event->focus_time;
    
            if ($event->event_type === 'click' && $event->element) {
                $dom = new \DOMDocument();
                @$dom->loadHTML($event->element);
            
           
                $elementText = '';
                $elementHref = '';
            
           
                $link = $dom->getElementsByTagName('a')->item(0);
                $button = $dom->getElementsByTagName('button')->item(0);
                $form = $dom->getElementsByTagName('form')->item(0);
            
                if ($link) {
                    $elementText = $link->nodeValue;
                    $elementHref = $link->getAttribute('href');
                } elseif ($button) {
                    $elementText = $button->nodeValue;
                    $elementHref = $button->getAttribute('onclick');
                } elseif ($form) {
                    $elementText = $form->getAttribute('name') ?: 'Form Submission';
                    $elementHref = $form->getAttribute('action');
                }
            
                $journeyMap[$pageUrl]['click_events'][] = [
                    'text' => $elementText,
                    'url' => $elementHref,
                ];
            }
            
    
            if ($previousPage) {
                if (!isset($journeyMap[$previousPage]['nextPages'][$pageUrl])) {
                    $journeyMap[$previousPage]['nextPages'][$pageUrl] = 0;
                }
                $journeyMap[$previousPage]['nextPages'][$pageUrl]++;
            }
    
            $previousPage = $pageUrl;
        }
    
        $metrics = [
            'totalPagesVisited' => count($journeyMap),
            'totalVisits' => array_sum(array_column($journeyMap, 'visits')),
            'totalFocusTime' => array_sum(array_column($journeyMap, 'total_focus_time')),
        ];
    
        return view('user_journey', [
            'userJourneys' => $filteredUserJourneys,
            'journeyMap' => $journeyMap,
            'metrics' => $metrics,
        ]);
    }
    
    

    

    
  
}
