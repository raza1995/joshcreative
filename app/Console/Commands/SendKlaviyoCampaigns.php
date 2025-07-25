<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use League\Csv\Reader;
use Carbon\Carbon;

class SendKlaviyoCampaigns extends Command
{
    protected $signature = 'funnelytics:send-dynamic
    {file : Path to the CSV file}
    {email : Email address}
    {action : Webhook action name}';

    protected $description = 'Send any CSV data with dynamic fields to Funnelytics';

    public function handle()
    {
        $file = $this->argument('file');
      
        $fullPath = storage_path('app/' . $file);

        if (!$file || !file_exists($fullPath)) {
            $this->error("❌ File not found at: storage/app/{$file}");
            return 1;
        }
        $projectId = env('FUNNELYTICS_PROJECT_ID');
        $apiKey    = env('FUNNELYTICS_API_KEY');
        $email     = $this->argument('email');
        $action    = $this->argument('action');

        $csv = Reader::createFromPath(storage_path("app/{$file}"), 'r');
        $csv->setHeaderOffset(0);
        $records = $csv->getRecords();

        foreach ($records as $index => $row) {
            try {
                $payload = [];

                // Include email
                $payload['email'] = $email;

                // Try to construct dateTime from Send Date + Send Time
                if (!empty($row['Send Date']) && !empty($row['Send Time'])) {
                    try {
                        $payload['dateTime'] = Carbon::parse("{$row['Send Date']} {$row['Send Time']}")->toIso8601String();
                    } catch (\Exception $e) {
                        $this->warn("⚠️ Row $index: invalid date/time format, skipping 'dateTime'");
                    }
                }

                // Build purchase_data if value exists
                if (!empty($row['Total Placed Order Value'])) {
                    $payload['purchase_data'] = [[
                        '__sku__'            => $row['Campaign ID'] ?? 'N/A',
                        '__label__'          => $row['Campaign Name'] ?? 'Unknown',
                        '__total_in_cents__' => (int) round((float) $row['Total Placed Order Value'] * 100),
                        '__order__'          => $row['Campaign ID'] ?? uniqid(),
                        '__currency__'       => 'USD',
                    ]];
                }

                // Add all other CSV columns as-is (flat key-values)
                foreach ($row as $key => $value) {
                    if (!in_array($key, ['Send Date', 'Send Time', 'Total Placed Order Value'])) {
                        $payload[$key] = $value;
                    }
                }

                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'X-PROJECT-ID' => '07ac73bb-23ab-4f87-a538-a1f67c28af43',
                    'X-API-KEY'    => 'Basic MDdhYzczYmItMjNhYi00Zjg3LWE1MzgtYTFmNjdjMjhhZjQzOjU1YjE1MWQ3YzIzM2E3YWMxZDdiNTk2NGJmMTlhYzZmMmQ2YTRhNmJiZGExNWZiOTI2OGRmZGRjMWI0NmJhMTk=',
                ])->post("https://events.funnelytics.io/api/v1/webhook/{$action}", $payload);

                if ($response->successful()) {
                    $this->info("✅ Sent row $index: " . ($row['Campaign Name'] ?? 'Unnamed'));
                } else {
                    $this->error("❌ Row $index failed: " . $response->status() . ' - ' . $response->body());
                }

                usleep(500000); // 0.5s delay to avoid rate limits

            } catch (\Throwable $e) {
                $this->error("❌ Exception on row $index: " . $e->getMessage());
            }
        }

        return 0;
    }
}
