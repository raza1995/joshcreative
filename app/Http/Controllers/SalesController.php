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
        Log::info('New sale created first: ' . json_encode($payload ));

        $mappedData = [
            'user_id' => $payload['object']['user']['id'],
            'total_amount' => $payload['object']['final_price'],
            'email' => $payload['object']['user']['email'],
            'price' => $payload['object']['user']['price']
        ];
        Sale::create($mappedData);
        return response()->json(['message' => 'Webhook received successfully']);
    }

    public function salesDataWebHook(Request $request)
    {
        Log::info('New sale created second: ' . json_encode($request->all()));

        $existingSale = Sale::where('user_id', $request->user_id_dj)
                            ->where('email', $request->email)
                            ->first();

        if ($existingSale) {
            $existingSale->update(['utm_source' => $request->utm_source]);
            return response()->json(['message' => 'Webhook data updated successfully']);
        } else {
            $mappedData = [
                'user_id' => $request->user_id_dj,
                'email' => $request->email,
                'utm_source' => $request->utm_source,
            ];

            Sale::create($mappedData);

            return response()->json(['message' => 'Webhook data saved successfully']);
        }
    }
}