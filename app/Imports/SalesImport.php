<?php

namespace App\Imports;

use App\Models\Sale;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class SalesImport implements ToModel, WithHeadingRow
{
    /**
     * @param array $row
     * 
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        return new Sale([
            'project_id' => '',  // Placeholder value
            'name' => $row['purchaser'] ?? '',
            'user_id' => (string) $row['id'] ?? '',
            'salesname' => '',  // Leaving salesname empty
            'email' => $row['purchaser_email'] ?? '',
            'ip_address' => '',  // Placeholder value
            'utm_source' => '',  // Placeholder value
            'total_amount' => $row['net_charge_usd'] ?? 0,
            'earned_commission' => '',  // Placeholder value
            'created_at' => $row['purchased_at'] ? \Carbon\Carbon::parse($row['purchased_at'])->format('Y-m-d H:i:s') : null,
            'updated_at' => $row['purchased_at'] ? \Carbon\Carbon::parse($row['purchased_at'])->format('Y-m-d H:i:s') : null,
            'dj_user_id' => '',  // Placeholder value
            'price' => $row['listed_price'] ?? 0,
            'promo_code' => $row['coupon_code'] ?? '',
            'status' => 'Purchased',  // Setting status to 'Purchased'
        ]);
    }
}
