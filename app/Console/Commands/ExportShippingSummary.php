<?php

namespace App\Console\Commands;

use App\Exports\ShopifyShippingExport;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;

class ExportShippingSummary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:export-shipping-summary';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $fileName = 'shipping_summary_' . now()->format('Ymd_His') . '.xlsx';
        $filePath = 'exports/' . $fileName;

        Excel::store(new ShopifyShippingExport, $filePath, 'local');

        $this->info("Shipping summary exported successfully to storage/app/{$filePath}");
    }
}
