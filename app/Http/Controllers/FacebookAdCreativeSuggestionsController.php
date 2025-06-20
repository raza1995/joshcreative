<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FacebookAdStat;
use Carbon\Carbon;
use DataTables;
use Yajra\DataTables\DataTables as DataTablesDataTables;

class FacebookAdCreativeSuggestionsController extends Controller
{
    public function index()
    {
        return view('analytics.index');
    }

    public function getData(Request $request)
    {
        $startDate = Carbon::today()->subDays(7)->toDateString();
        $endDate = Carbon::today()->subDay()->toDateString();

        $stats = FacebookAdStat::where('interval', 'daily')
            ->whereDate('start_date', '>=', $startDate)
            ->whereDate('start_date', '<=', $endDate)
            ->where('status', 'active')
            ->get();

        // Group and summarize by ad_id
        $grouped = $stats->groupBy('ad_id')->map(function ($group) {
            $first = $group->firstWhere('ad_account_name', '!=', null) ?? $group->first();
            $totalSpend = $group->sum('spend');
        
            // Weighted average CPA
            $weightedCpa = $group->sum(fn($item) => $item->spend * $item->cpa) / max($totalSpend, 1);
        
        return [
    'ad_account_name' => $first->ad_account_name,
    'ad_id' => $first->ad_id,
    'campaign_name' => $first->campaign_name,
    'ad_name' => $first->ad_name,
    'adset_name' => $first->adset_name,
    'spend' => round($totalSpend, 2),
    'cpa' => round($weightedCpa, 2),
    'ad_link' => $first->ad_link,
    'thumbnail_url' => $first->thumbnail_url,
];

        });
        
        

        // Bucketing based on rules
        $buckets = collect([
            'Turn into Video' => collect(),
            'Change the visual delivery' => collect(),
            'Change the Angle' => collect(),
            'Change hook variation' => collect(),
            'Change messaging' => collect(),
            'Trash' => collect(),
        ]);

        foreach ($grouped as $data) {
            $spend = $data['spend'];
            $cpa = $data['cpa'];
        
            if ($spend >= 1000) {
                if ($cpa < 40) $buckets['Turn into Video']->push($data);
                elseif ($cpa <= 45) $buckets['Change the visual delivery']->push($data);
                else $buckets['Change the Angle']->push($data);
            } elseif ($spend >= 500) {
                if ($cpa < 40) $buckets['Change the visual delivery']->push($data);
                elseif ($cpa <= 45) $buckets['Change hook variation']->push($data);
                else $buckets['Change messaging']->push($data);
            } else {
                if ($cpa < 40) $buckets['Change the visual delivery']->push($data);
                elseif ($cpa <= 45) $buckets['Change messaging']->push($data);
                else $buckets['Trash']->push($data);
            }
        }
        

        $rows = collect($buckets->get($request->get('action_type'), []));
        return DataTables::of($rows ?? collect())
        ->editColumn('ad_link', fn($row) => "<a href='{$row['ad_link']}' target='_blank'>Ad Link</a>")
        ->editColumn('thumbnail_url', fn($row) => "<img src='{$row['thumbnail_url']}' width='60'/>")
        ->rawColumns(['ad_link', 'thumbnail_url'])
        ->make(true);
    
    }
}
