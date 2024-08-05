<?php

namespace App\Http\Controllers;

use App\DataTables\SalesDataDataTable;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SalesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(SalesDataDataTable $dataTable)
    {
    if(Auth::user()){
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
        'promo_code' => $payload['object']['coupon']['code'] ?? '',
        'status' => 'Purchased',
        'price' => number_format(($payload['object']['product']['price'] ?? 0) / 100, 2, '.', '')
    ];
    Log::info('mappedData: ' . json_encode($mappedData));
    
    $existingSale = Sale::where('email', $mappedData['email'] ?? '')->first();

    if ($existingSale) {

        $existingSale->update([
            'total_amount' => $mappedData['total_amount'],
            'price' => $mappedData['price'],
            'purchase_count' => $existingSale->purchase_count + 1 
        ]);
    } else {
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
}