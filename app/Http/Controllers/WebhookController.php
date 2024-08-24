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
    
        // Check if the incoming data is an array of events
        if (!is_array($data)) {
            Log::error('Invalid data format. Expected an array of events.');
            return response()->json(['message' => 'Invalid data format'], 400);
        }
    
        foreach ($data as $eventData) {
            // Validate that each event contains necessary fields
            if (!isset($eventData['user_id'])) {
                Log::error('Missing user_id in event data: ' . json_encode($eventData));
                continue; // Skip this event and continue with the next one
            }
    
            Log::info('Processing event data: ' . json_encode($eventData));
    
            // Check if the user_id is excluded
            $isExcluded = ExcludedIp::where('user_id', $eventData['user_id'])->exists();
            if ($isExcluded) {
                Log::info('Excluded user_id address: ' . $eventData['user_id']);
                continue; // Skip this event and continue with the next one
            }
    
            // Parse dates and calculate stay duration
            try {
                $startTime = isset($eventData['start_time']) ? Carbon::parse($eventData['start_time']) : null;
                $endTime = isset($eventData['end_time']) ? Carbon::parse($eventData['end_time']) : null;
            } catch (\Exception $e) {
                Log::error('Error parsing dates for event: ' . json_encode($eventData) . '. Error: ' . $e->getMessage());
                continue; // Skip this event and continue with the next one
            }
    
            $stayDuration = $endTime && $startTime ? $endTime->diffInSeconds($startTime) : null;
    
            // Create or update user event
            UserEvent::create([
                'user_id' => $eventData['user_id'],
                'page_url' => $eventData['page_url'] ?? '',
                'start_time' => $startTime,
                'end_time' => $endTime,
                'stay_duration' => $stayDuration ?? 0,
                'focus_time' => $eventData['focus_time'] ?? 0,
                'event_type' => $eventData['event_type'] ?? 'unknown',
                'element' => $eventData['element'] ?? '',
            ]);
    
            // Handle page view updates
            if ($stayDuration && class_exists(Pages::class)) {
                $page = Pages::firstOrCreate(['url' => $eventData['page_url'] ?? '']);
                $page->increment('views');
                $page->increment('total_stay_duration', $stayDuration);
                $page->increment('focus_time', $eventData['focus_time'] ?? 0);
            }
        }
    
        return response()->json(['message' => 'Events processed'], 201);
    }
    


}
