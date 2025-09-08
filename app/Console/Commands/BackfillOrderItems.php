<?php

namespace App\Console\Commands;

use App\Models\ShopifyOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillOrderItems extends Command
{
    protected $signature = 'kpi:backfill-items {--from=} {--to=}';
    protected $description = 'Normalize line_items from raw_json into order_items table';

    public function handle()
    {
        $from = $this->option('from');
        $to = $this->option('to');
        $this->info('Backfilling order_items ' . ($from && $to ? "between {$from} and {$to}" : '(all)'));

        $inserted = 0; $scanned = 0;
        $query = ShopifyOrder::select('id','order_number','raw_json');
        if ($from && $to) {
            $query->whereBetween('order_date', [$from.' 00:00:00', $to.' 23:59:59']);
        }

        $query->orderBy('id')->chunkById(1000, function ($chunk) use (&$inserted, &$scanned) {
            $rows = [];
            foreach ($chunk as $o) {
                $scanned++;
                $raw = is_array($o->raw_json) ? $o->raw_json : (is_string($o->raw_json) ? (json_decode($o->raw_json, true) ?: []) : []);
                foreach (($raw['line_items'] ?? []) as $li) {
                    $rows[] = [
                        'order_number' => (string) $o->order_number,
                        'sku'         => (string) ($li['sku'] ?? ''),
                        'product_id'  => isset($li['product_id']) ? (int) $li['product_id'] : null,
                        'variant_id'  => isset($li['variant_id']) ? (int) $li['variant_id'] : null,
                        'title'       => (string) ($li['title'] ?? ''),
                        'quantity'    => (int) ($li['quantity'] ?? 0),
                        'price'       => (float) ($li['price'] ?? 0),
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ];
                }
            }
            if (!empty($rows)) {
                $inserted += count($rows);
                DB::table('order_items')->upsert($rows, ['order_number','variant_id','sku'], ['quantity','price','title','updated_at']);
            }
        });

        $this->info("Scanned orders: {$scanned}, Upserted items: {$inserted}");
        return self::SUCCESS;
    }
}

