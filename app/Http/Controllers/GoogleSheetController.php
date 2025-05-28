<?php

namespace App\Http\Controllers;

use App\Services\GoogleSheetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GoogleSheetController extends Controller
{
    public function showForm()
    {
        // Fetch files from both directories
        $files1 = Storage::disk('public')->files('fb_ads');
        $files2 = Storage::disk('public')->files('fb_ads_merged');
    
        // Merge and filter only JSON files
        $allFiles = collect(array_merge($files1, $files2))
            ->filter(fn($file) => str_ends_with($file, '.json'))
            ->map(fn($f) => basename($f))
            ->unique()
            ->values()
            ->toArray();
    
        return view('google-sheet.export', ['jsonFiles' => $allFiles]);
    }

public function createSheet(Request $request, GoogleSheetService $sheetService)
{
    $request->validate([
        'sheet_name' => 'required|string',
        'json_file' => 'required|string',
    ]);

    // Define possible directories to check
    $possiblePaths = [
        "fb_ads/{$request->json_file}",
        "fb_ads_merged/{$request->json_file}",
    ];

    $jsonPath = null;

    // Find the first file that exists
    foreach ($possiblePaths as $path) {
        if (Storage::disk('public')->exists($path)) {
            $jsonPath = $path;
            break;
        }
    }

    // Handle missing file
    if (!$jsonPath) {
        return response()->json([
            'error' => 'JSON file not found in expected directories (fb_ads, fb_ads_merged).'
        ], 404);
    }

    // Create the sheet
    $sheetUrl = $sheetService->createSheetFromJson($request->sheet_name, $jsonPath);

    return response()->json(['sheet_url' => $sheetUrl]);
}


public function appendToSheet(Request $request, GoogleSheetService $sheetService)
{
    $request->validate([
        'spreadsheet_id' => 'required|string',
        'json_file' => 'required|string',
    ]);

    $jsonPath = "fb_ads/{$request->json_file}";
    $sheetService->appendJsonToSheet($request->spreadsheet_id, $jsonPath);

    return response()->json([
        'message' => '📌 Data appended successfully.',
        'sheet_url' => $sheetService->getSheetUrl($request->spreadsheet_id)
    ]);
}

}
