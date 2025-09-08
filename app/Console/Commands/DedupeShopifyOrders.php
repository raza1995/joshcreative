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
        $groups = 0;
        $removed = 0;
        $fixedKeys = 0;

        DB::beginTransaction();
        try {
            // Build groups keyed by canonical key: raw_json.id if present, else current order_number
            $all = ShopifyOrder::select('id', 'order_number', 'raw_json', 'tracking_number', 'tracking_url', 'customer_name', 'email_address', 'order_date', 'paid_amount', 'discount', 'number_of_items', 'coupon', 'ad_id', 'anon_id', 'created_at', 'updated_at')
                ->orderBy('id')
                ->get();

            $map = [];
            foreach ($all as $row) {
                $total++;
                $raw = is_array($row->raw_json) ? $row->raw_json : (is_string($row->raw_json) ? (json_decode($row->raw_json, true) ?: []) : []);
                $jsonId = $raw['id'] ?? null;
                $key = $jsonId ? (string) $jsonId : (string) ($row->order_number ?? '');
                if (!isset($map[$key])) $map[$key] = [];
                $map[$key][] = $row;
            }

            foreach ($map as $key => $rows) {
                if ($key === '' || count($rows) === 0) continue;
                $groups++;
                if (count($rows) === 1) {
                    $row = $rows[0];
                    if ((string) $row->order_number !== (string) $key) {
                        $fixedKeys++;
                        if (!$dry) {
                            $row->order_number = (string) $key;
                            $row->save();
                        }
                    }
                    continue;
                }

                // Prefer rows with raw_json, then latest updated_at, then highest id
                usort($rows, function ($a, $b) {
                    $aHas = !empty($a->raw_json);
                    $bHas = !empty($b->raw_json);
                    if ($aHas !== $bHas) return $aHas ? -1 : 1;
                    if ($a->updated_at != $b->updated_at) return $a->updated_at < $b->updated_at ? 1 : -1;
                    return $a->id < $b->id ? 1 : -1;
                });

                $keep = array_shift($rows);

                // Normalize key on keeper
                if ((string) $keep->order_number !== (string) $key) {
                    $fixedKeys++;
                    if (!$dry) {
                        $keep->order_number = (string) $key;
                    }
                }

                // Merge best-effort fields from duplicates
                foreach ($rows as $d) {
                    // fill empty tracking fields
                    if (!$keep->tracking_number && $d->tracking_number) $keep->tracking_number = $d->tracking_number;
                    if (!$keep->tracking_url && $d->tracking_url) $keep->tracking_url = $d->tracking_url;
                    if (!$keep->customer_name && $d->customer_name) $keep->customer_name = $d->customer_name;
                    if (!$keep->email_address && $d->email_address) $keep->email_address = $d->email_address;
                    if (!$keep->order_date && $d->order_date) $keep->order_date = $d->order_date;
                    if ((!$keep->paid_amount || $keep->paid_amount == 0) && ($d->paid_amount && $d->paid_amount != 0)) $keep->paid_amount = $d->paid_amount;
                    if ((!$keep->discount || $keep->discount == 0) && ($d->discount && $d->discount != 0)) $keep->discount = $d->discount;
                    if ((!$keep->number_of_items || $keep->number_of_items == 0) && ($d->number_of_items && $d->number_of_items != 0)) $keep->number_of_items = $d->number_of_items;
                    if (!$keep->coupon && $d->coupon) $keep->coupon = $d->coupon;
                    if (!$keep->ad_id && $d->ad_id) $keep->ad_id = $d->ad_id;
                    if (!$keep->anon_id && $d->anon_id) $keep->anon_id = $d->anon_id;
                    if (empty($keep->raw_json) && !empty($d->raw_json)) $keep->raw_json = $d->raw_json;
                }

                if (!$dry) {
                    $keep->save();
                    // Delete the rest
                    $idsToDelete = array_map(fn($r) => $r->id, $rows);
                    if (!empty($idsToDelete)) {
                        ShopifyOrder::whereIn('id', $idsToDelete)->delete();
                        $removed += count($idsToDelete);
                    }
                } else {
                    $removed += count($rows); // estimate what would be removed
                }
            }

            if ($dry) {
                DB::rollBack();
            } else {
                DB::commit();
            }

            $this->info(($dry ? '[DRY RUN] ' : '') . sprintf('Scanned: %d rows, Groups: %d, Keys fixed: %d, Duplicates removed: %d', $total, $groups, $fixedKeys, $removed));
            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}

