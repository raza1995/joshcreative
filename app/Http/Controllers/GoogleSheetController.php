<?php

namespace App\Http\Controllers;

use App\Services\GoogleSheetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GoogleSheetController extends Controller
{
    public function showForm()
    {
        $files = Storage::files('public/fb_ads');
        $jsonFiles = array_map(fn($f) => basename($f), $files);

        return view('google-sheet.export', compact('jsonFiles'));
    }

   public function createSheet(Request $request, GoogleSheetService $sheetService)
{
    $request->validate([
        'sheet_name' => 'required|string',
        'json_file' => 'required|string',
    ]);

    $jsonPath = "fb_ads/{$request->json_file}";
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
