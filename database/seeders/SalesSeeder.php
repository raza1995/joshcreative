<?php

namespace Database\Seeders;

use App\Models\Sale;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SalesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Sale::create([
            'project_id' => 1,
            'salesname' => 'Product A',
            'ip_address' => '192.168.1.1',
            'utm_source' => 'Google',
            'total_amount' => 100.50,
            'earned_commission' => 10.25
        ]);

    }
}
