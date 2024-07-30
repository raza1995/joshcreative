<?php

namespace App\Http\Controllers;

use App\DataTables\SalesDataDataTable;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

    public function salesDataWebHook(Request $request)
    {
        if ($request->has('key') && $request->key === env('WEBHOOK_SECRET_KEY')) {

        $existingSale = null;

        if ($request->has('email') || $request->has('user_id')) {
            $existingSale = Sale::where(function ($query) use ($request) {
                if ($request->has('email')) {
                    $query->where('email', $request->email);
                }
                if ($request->has('user_id')) {
                    $query->orWhere('user_id', $request->user_id);
                }
            })->first();
        }

        if ($existingSale) {
            $existingSale->update($request->all());
            return response()->json(['message' => 'Webhook data updated successfully']);
        } else {
            Sale::create($request->all());
            return response()->json(['message' => 'Webhook data saved successfully']);
        }
    } else {
        return response()->json(['error' => 'Unauthorized'], 401);
    }
    }
}