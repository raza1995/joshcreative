<?php

namespace App\Services;

use Google\Client;
use Google\Service\Sheets;
use Illuminate\Support\Facades\Storage;

class GoogleSheetService
{
protected $client;
protected $service;
protected $tokenPath;
public function __construct()
{
    $this->client = new Client();
    $this->tokenPath = storage_path('app/token.json');

    $this->client->setApplicationName('Laravel Facebook Ads Export');
    $this->client->setScopes([
        'https://www.googleapis.com/auth/spreadsheets',
        'https://www.googleapis.com/auth/drive.file', // or 'https://www.googleapis.com/auth/drive' if you need broader access
    ]);
    $this->client->setAuthConfig(storage_path('app/credentials.json'));
    $this->client->setAccessType('offline');
    $this->client->setPrompt('select_account consent');

    // Load token if exists
    if (file_exists($this->tokenPath)) {
        $accessToken = json_decode(file_get_contents($this->tokenPath), true);
        $this->client->setAccessToken($accessToken);
    }

    // Refresh the token if expired
    if ($this->client->isAccessTokenExpired()) {
        if ($this->client->getRefreshToken()) {
            $this->client->fetchAccessTokenWithRefreshToken(...);
        } else {
            $this->promptManualAuthorization();  // this part
        }
    }
    

    $this->service = new Sheets($this->client);
}
protected function promptManualAuthorization()
{
    $authUrl = $this->client->createAuthUrl();

    echo "\n🔐 Open this link in your browser:\n$authUrl\n";
    echo "\n👉 Enter the authorization code here: ";
    $authCode = trim(fgets(STDIN));

    $accessToken = $this->client->fetchAccessTokenWithAuthCode($authCode);
    $this->client->setAccessToken($accessToken);
}

public function createSheetFromJson(string $sheetTitle, string $jsonFile)
{
    $data = json_decode(Storage::disk('public')->get($jsonFile), true);

    if (empty($data)) {
        throw new \Exception("JSON file is empty or invalid.");
    }

    $spreadsheet = new Sheets\Spreadsheet([
        'properties' => ['title' => $sheetTitle]
    ]);

    $sheet = $this->service->spreadsheets->create($spreadsheet);
    $spreadsheetId = $sheet->spreadsheetId;

    $this->writeDataToSheet($spreadsheetId, $data);

    return $spreadsheet->spreadsheetUrl;
}

public function writeDataToSheet(string $spreadsheetId, array $data, string $range = 'Sheet1')
{
    $headers = array_keys($data[0]);
    $rows = array_map('array_values', $data);

    $body = new Sheets\ValueRange([
        'range' => $range,
        'values' => array_merge([$headers], $rows)
    ]);

    $params = ['valueInputOption' => 'RAW'];

    $this->service->spreadsheets_values->update($spreadsheetId, $range, $body, $params);
}

public function appendJsonToSheet(string $spreadsheetId, string $jsonFile, string $range = 'Sheet1')
{
    $data = json_decode(Storage::disk('public')->get($jsonFile), true);

    if (empty($data)) {
        throw new \Exception("JSON file is empty or invalid.");
    }

    $rows = array_map('array_values', $data);

    $body = new Sheets\ValueRange(['values' => $rows]);

    $params = ['valueInputOption' => 'RAW', 'insertDataOption' => 'INSERT_ROWS'];

    $this->service->spreadsheets_values->append($spreadsheetId, $range, $body, $params);
}
public function getSheetUrl(string $spreadsheetId): string
{
    return "https://docs.google.com/spreadsheets/d/{$spreadsheetId}";
}


}