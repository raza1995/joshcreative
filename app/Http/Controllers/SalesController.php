<?php

namespace App\Http\Controllers;

use App\DataTables\SalesDataDataTable;
use App\Imports\SalesImport;
use App\Models\Sale;
use App\Models\UserEvent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\AnalyticsService;

class SalesController extends Controller
{
    private $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

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

    public function getCurrentMonthRevenue()
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
    // Attempt to retrieve data from cache
    $userJourneys = Cache::get('userJourneys');
    $journeyMap = Cache::get('journeyMap');
    $metrics = Cache::get('metrics');
    $segments = Cache::get('userSegments');

    // Check if any of the data is missing from the cache
    // if (!$userJourneys || !$journeyMap || !$metrics || !$segments) {
    //     // Data is missing from the cache, so manually refresh it
    //     $baseUrl = 'https://academyofdjs.com';
    //     $userJourneys = $this->analyticsService->fetchUserJourneys($baseUrl);
    //     $journeyMap = $this->analyticsService->createJourneyMap($userJourneys);
    //     $metrics = $this->analyticsService->calculateMetrics($userJourneys, $journeyMap);
    //     $segments = $this->analyticsService->segmentUsers($userJourneys);

    //     // Cache the data for future use
    //     Cache::put('userJourneys', $userJourneys, 60);
    //     Cache::put('journeyMap', $journeyMap, 60);
    //     Cache::put('metrics', $metrics, 60);
    //     Cache::put('userSegments', $segments, 60);
    // }

    return view('sales.journey', array_merge($metrics, [
        'journeyMap' => $journeyMap,
        'landingPages' => array_unique($metrics['landingPages']),
        'segments' => $segments,
    ]));
}

 
    

public function getUserJourney($userId)
{
    $userJourneys = UserEvent::where('user_events.user_id', $userId)
        ->join('sales', 'sales.dj_user_id', '=', 'user_events.user_id')
        ->orderBy('start_time', 'asc')
        ->select('user_events.*', 'sales.email', 'sales.name', 'sales.utm_source')
        ->get();

   $filteredUserJourneys = $userJourneys->unique(function ($item) {
    // Create a unique key based on event_type, page_url, and start_time
    $eventType = $item['event_type'];
    $pageUrl = $item['page_url'];
    $startTime = $item['start_time'];
    
    // Combine event_type, page_url, and start_time to create a unique identifier
    return md5($eventType . '|' . $pageUrl . '|' . $startTime);
});

        

    $journeyMap = [];
    $previousPage = null;
    $previousEventTime = null;

    foreach ($filteredUserJourneys as $event) {
        $pageUrl = $event->page_url;

        // Skip reloads by checking if the page is the same as the previous one and the time difference is small
        if ($previousPage === $pageUrl && $previousEventTime && strtotime($event->start_time) - strtotime($previousEventTime) < 3) {
            continue;
        }

        if (!isset($journeyMap[$pageUrl])) {
            $journeyMap[$pageUrl] = [
                'visits' => 0,
                'total_focus_time' => 0,
                'nextPages' => [],
                'click_events' => [],  // Initialize click events
                'scroll_depths' => [], // Initialize scroll depths
                'focus_events' => [],  // Initialize focus events
                'change_events' => [], // Initialize change events
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
        } elseif ($event->event_type == 'scroll' && $event->element) {
            $journeyMap[$pageUrl]['scroll_depths'][] = json_decode($event->element, true); // Assume element contains scroll depth data in JSON format
        } elseif ($event->event_type == 'focus' && $event->element) {
            $journeyMap[$pageUrl]['focus_events'][] = json_decode($event->element, true); // Assume element contains focus data in JSON format
        } elseif ($event->event_type == 'change' && $event->element) {
            $journeyMap[$pageUrl]['change_events'][] = json_decode($event->element, true); // Assume element contains change data in JSON format
        }

        if ($previousPage) {
            if (!isset($journeyMap[$previousPage]['nextPages'][$pageUrl])) {
                $journeyMap[$previousPage]['nextPages'][$pageUrl] = 0;
            }
            $journeyMap[$previousPage]['nextPages'][$pageUrl]++;
        }

        $previousPage = $pageUrl;
        $previousEventTime = $event->start_time;
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
