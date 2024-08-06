<?php

namespace App\Http\Controllers;

use App\DataTables\SalesDataDataTable;
use App\Imports\SalesImport;
use App\Models\Sale;
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
            'total_amount' => $payload['object']['final_price'] ?? 0,
            'email' => $payload['object']['user']['email'] ?? '',
            'name' => $payload['object']['user']['name'] ?? '',
            'promo_code' => $payload['object']['coupon']['code'] ?? '',
            'status' => 'Purchased',
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
                'status' => 'added_to_cart',
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
        $currentMonthRevenue = Sale::whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->sum('total_amount');

        return view('welcome', compact('dailyRevenue', 'monthlyRevenue', 'yearlyRevenue', 'currentMonthRevenue'));
    }

    private function getRevenueBy($interval)
    {
        $dateFormat = [
            'day' => '%Y-%m-%d',
            'month' => '%Y-%m',
            'year' => '%Y',
        ];

        return Sale::select(
            DB::raw('SUM(total_amount) as total'),
            DB::raw("DATE_FORMAT(created_at, '{$dateFormat[$interval]}') as date")
        )
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }
}
