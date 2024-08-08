<?php

namespace App\Http\Controllers;

use App\Models\ExcludedIp;
use App\Models\Pages;
use App\Models\UserEvent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        // Log the entire request payload for debugging
        Log::info('Incoming request data: ' . json_encode($request->all()));
    
        $data = $request->json()->all();
    
        Log::info('Validated data: ' . json_encode($data));
        // $isExcluded = ExcludedIp::where('user_id', $data['user_id'])->exists();
        
        // if ($isExcluded) {
        //     Log::info('Excluded user_id address: ' . $data['user_id']);
        //     return response()->json(['message' => 'IP address is excluded'], 403);
        // }
    
        try {
            $startTime = Carbon::parse($data['start_time']);
            $endTime = isset($data['end_time']) ? Carbon::parse($data['end_time']) : null;
            $stayDuration = $endTime ? $endTime->diffInSeconds($startTime) : null;
        } catch (\Exception $e) {
            Log::error('Error parsing start or end time: ' . $e->getMessage());
            $startTime = null;
            $endTime = null;
            $stayDuration = null;
        }
    
        $userEvent = UserEvent::create([
            'user_id' => $data['user_id'],
            'page_url' => $data['page_url'],
            'start_time' => $startTime,
            'end_time' => $endTime,
            'stay_duration' => $stayDuration,
            'focus_time' => $data['focus_time'],
            'event_type' => $data['event_type'] ?? '',
            'element' => $data['element'] ?? ''
        ]);
    
        if ($stayDuration && class_exists(Pages::class)) {
            $page = Pages::firstOrCreate(['url' => $data['page_url']]);
            $page->increment('views');
            $page->increment('total_stay_duration', $stayDuration);
            $page->increment('focus_time', $data['focus_time']); // Increment focus_time if Pages has this column
        }
    
        return response()->json(['message' => 'Event recorded'], 201);
    }
}
