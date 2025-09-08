<?php

namespace App\Console\Commands;

use App\Models\ShopifyOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DedupeShopifyOrders extends Command
{
    protected $signature = 'shopify:dedupe-orders {--dry-run : Analyze only, no writes}';
    protected $description = 'Normalize order_number from raw_json.id, merge duplicates, and remove extras safely';

    public function handle()
    {
        $dry = (bool) $this->option('dry-run');
        $this->info(($dry ? '[DRY RUN] ' : '') . 'Scanning Shopify orders for duplicates...');

        $total = 0;
        $removed = 0;
        $fixedKeys = 0;

        // Streaming dedupe: keep best row per canonical key, collect ids to delete
        $groups = [];
        $progressEvery = 2000;

        DB::beginTransaction();
        try {
            ShopifyOrder::select('id', 'order_number', 'raw_json', 'tracking_number', 'tracking_url', 'customer_name', 'email_address', 'order_date', 'paid_amount', 'discount', 'number_of_items', 'coupon', 'ad_id', 'anon_id', 'created_at', 'updated_at')
                ->orderBy('id')
                ->chunkById(2000, function ($chunk) use (&$groups, &$total, $progressEvery) {
                    foreach ($chunk as $row) {
                        $total++;
                        $raw = is_array($row->raw_json) ? $row->raw_json : (is_string($row->raw_json) ? (json_decode($row->raw_json, true) ?: []) : []);
                        $jsonId = $raw['id'] ?? null;
                        $key = $jsonId ? (string) $jsonId : (string) ($row->order_number ?? '');
                        if ($key === '') {
                            $key = 'UNK:' . $row->id; // isolate truly unknowns to avoid merging unrelated rows
                        }

                        if (!isset($groups[$key])) {
                            $groups[$key] = [
                                'keep' => $row,
                                'delete' => [],
                            ];
                        } else {
                            $keep = $groups[$key]['keep'];
                            // Decide best keeper: prefer has raw_json, then latest updated_at, then highest id
                            $keepHas = !empty($keep->raw_json);
                            $rowHas = !empty($row->raw_json);
                            $preferRow = false;
                            if ($keepHas !== $rowHas) {
                                $preferRow = $rowHas; // pick the one that has raw_json
                            } elseif ($keep->updated_at != $row->updated_at) {
                                $preferRow = $row->updated_at > $keep->updated_at;
                            } else {
                                $preferRow = $row->id > $keep->id;
                            }

                            if ($preferRow) {
                                $groups[$key]['delete'][] = $keep->id;
                                $groups[$key]['keep'] = $row;
                            } else {
                                $groups[$key]['delete'][] = $row->id;
                            }
                        }
                    }
                });

            // Apply fixes and deletions
            foreach ($groups as $key => $data) {
                /** @var \App\Models\ShopifyOrder $keep */
                $keep = $data['keep'];
                if ((string) $keep->order_number !== (string) $key && strpos($key, 'UNK:') !== 0) {
                    $fixedKeys++;
                    if (!$dry) {
                        $keep->order_number = (string) $key;
                        $keep->save();
                    }
                }

                if (!empty($data['delete'])) {
                    if (!$dry) {
                        ShopifyOrder::whereIn('id', $data['delete'])->delete();
                    }
                    $removed += count($data['delete']);
                }
            }

            if ($dry) {
                DB::rollBack();
            } else {
                DB::commit();
            }

            $this->info(($dry ? '[DRY RUN] ' : '') . sprintf('Scanned: %d rows, Groups: %d, Keys fixed: %d, Duplicates removed: %d', $total, count($groups), $fixedKeys, $removed));
            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
