<?php

namespace App\Services;

use Google\Client;
use Google\Service\Sheets;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
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
    $this->client->addScope(Sheets::SPREADSHEETS);  // Read-only access
    $this->client->addScope(Sheets::DRIVE);  // Read-only access

   
    $this->client->setAuthConfig(storage_path('app/credentials.json'));
    $this->client->setAccessType('offline');
    $this->client->setPrompt('select_account consent');


    
    $this->authenticate();
  
    $this->service = new Sheets($this->client);
}

private function generateNewToken()
{
    $authUrl = $this->client->createAuthUrl();
    echo "🔗 Open this URL in your browser and authenticate:\n$authUrl\n";
    echo "📥 Paste the authentication code here: ";

    $authCode = trim(fgets(\STDIN));

    if (empty($authCode)) {
        throw new \InvalidArgumentException("❌ Invalid code: The authentication code cannot be empty.");
    }

    $accessToken = $this->client->fetchAccessTokenWithAuthCode($authCode);

    if (isset($accessToken['error'])) {
        throw new \Exception("❌ Google OAuth authentication failed: " . $accessToken['error']);
    }

    file_put_contents($this->tokenPath, json_encode($accessToken));
    $this->client->setAccessToken($accessToken);

    Log::info("✅ Google API authentication successful. Token stored.");
}

private function authenticate()
{
    if (file_exists($this->tokenPath)) {
        $accessToken = json_decode(file_get_contents($this->tokenPath), true);
    
        if (json_last_error() !== JSON_ERROR_NONE || !isset($accessToken['access_token'])) {
            Log::error("❌ Invalid token format detected. Re-authenticating...");
            $this->generateNewToken();
            return;
        }
    
        $this->client->setAccessToken($accessToken);
        try {
            $newAccessToken = $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
        
            if (isset($newAccessToken['error'])) {
                throw new \Exception($newAccessToken['error_description']);
            }
        
            if (!isset($newAccessToken['refresh_token'])) {
                $newAccessToken['refresh_token'] = $this->client->getRefreshToken();
            }
        
            $this->client->setAccessToken($newAccessToken);
            file_put_contents($this->tokenPath, json_encode($newAccessToken));
            Log::info("✅ Google API token refreshed successfully.");
        } catch (\Exception $e) {
            Log::error("❌ Token refresh failed: " . $e->getMessage());
            $this->generateNewToken();
        }
        
    } else {
        Log::warning("⚠️ Google API token file not found. Generating a new token...");
        $this->generateNewToken();
    }
    
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

public function createSheetFromJson(string $sheetTitle, string $jsonFile): string
{
    $data = $this->loadJsonData($jsonFile);

    $spreadsheet = $this->service->spreadsheets->create(
        new Sheets\Spreadsheet([
            'properties' => ['title' => $sheetTitle]
        ])
    );

    $this->writeDataToSheet($spreadsheet->spreadsheetId, $data);

    return $spreadsheet->spreadsheetUrl;
}

protected function writeDataToSheet(string $spreadsheetId, array $data, string $range = 'Sheet1'): void
{
    if (empty($data)) {
        throw new \InvalidArgumentException("Data is empty or invalid.");
    }

    $headers = array_keys($data[0]);

    $rows = array_map(fn($row) =>
        array_map(fn($value) =>
            is_array($value) || is_object($value)
                ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : (string) $value,
        array_values($row)),
    $data);

    $body = new Sheets\ValueRange([
        'range' => $range,
        'values' => array_merge([$headers], $rows)
    ]);

    $this->service->spreadsheets_values->update(
        $spreadsheetId,
        $range,
        $body,
        ['valueInputOption' => 'RAW']
    );
}

protected function loadJsonData(string $jsonFile): array
{
    $json = Storage::disk('public')->get($jsonFile);

    $data = json_decode($json, true);

    if (json_last_error() !== JSON_ERROR_NONE || empty($data)) {
        throw new \RuntimeException("Failed to decode JSON file or file is empty.");
    }

    return $data;
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