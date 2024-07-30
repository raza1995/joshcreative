<?php

namespace App\Http\Controllers;

use App\DataTables\SalesDataDataTable;
use App\Models\Sale;
use Illuminate\Http\Request;

class SalesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(SalesDataDataTable $dataTable)
    {
        ini_set('memory_limit', '1024M');

        return $dataTable->render('sales.index');
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

    public function salesDataWebHook(Request $request)
    {
        dd('test');
        if ($request->has('key') && $request->key === env('WEBHOOK_SECRET_KEY')) {
            // Get the webhook data
            $data = $request->all();

            Sale::create($data);

            return response()->json(['message' => 'Webhook data saved successfully']);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
}