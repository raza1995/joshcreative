<?php

namespace App\Http\Controllers;

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
    
        $startTime = Carbon::parse($data['start_time']);
        $endTime = isset($data['end_time']) ? Carbon::parse($data['end_time']) : null;
        $stayDuration = $endTime ? $endTime->diffInSeconds($startTime) : null;
    
        $userEvent = UserEvent::create([
            'user_id' => $data['user_id'],
            'page_url' => $data['page_url'],
            'start_time' => $startTime,
            'end_time' => $endTime,
            'stay_duration' => $stayDuration,
            'focus_time' => $data['focus_time'],
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
