<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ExcludedIp;

class ExcludedIpController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $ips = ExcludedIp::all();
        return view('excludedips.index', compact('ips'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('excludedips.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
      
        ExcludedIp::create($request->all());

        return redirect()->route('excludedips.index')
                         ->with('success', 'IP Address excluded successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $ip = ExcludedIp::find($id);
        return view('excludedips.show', compact('ip'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $ip = ExcludedIp::find($id);
        return view('excludedips.edit', compact('ip'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
    

        $ip = ExcludedIp::find($id);
        $ip->update($request->all());

        return redirect()->route('excludedips.index')
                         ->with('success', 'IP Address updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        ExcludedIp::find($id)->delete();

        return redirect()->route('excludedips.index')
                         ->with('success', 'IP Address removed successfully.');
    }

    /**
     * Check if the given IP address is excluded.
     */
    public function isExcluded($ipAddress)
    {
        $isExcluded = ExcludedIp::where('ip_address', $ipAddress)->exists();
        return response()->json(['isExcluded' => $isExcluded]);
    }
}
