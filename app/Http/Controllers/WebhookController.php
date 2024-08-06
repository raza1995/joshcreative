<?php

namespace App\Http\Controllers;

use App\Models\Pages;
use App\Models\UserEvent;
use Carbon\Carbon;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'nullable|string',
            'page_url' => 'required|string',
            'start_time' => 'required|date',
            'end_time' => 'nullable|date',
        ]);

        $startTime = Carbon::parse($data['start_time']);
        $endTime = isset($data['end_time']) ? Carbon::parse($data['end_time']) : null;
        $stayDuration = $endTime ? $endTime->diffInSeconds($startTime) : null;

        $userEvent = UserEvent::create([
            'user_id' => $data['user_id'],
            'page_url' => $data['page_url'],
            'start_time' => $startTime,
            'end_time' => $endTime,
            'stay_duration' => $stayDuration,
        ]);

        if ($stayDuration && class_exists(Pages::class)) {
            $page = Pages::firstOrCreate(['url' => $data['page_url']]);
            $page->increment('views');
            $page->increment('total_stay_duration', $stayDuration);
        }

        return response()->json(['message' => 'Event recorded'], 201);
    }
}
