<?php
namespace App\Exports;

use App\Models\ShopifyOrder;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ShopifyShippingExport implements FromCollection, WithHeadings
{
    public function collection(): Collection
    {
        $cutoff = Carbon::now()->subMonths(3);
        $orders = ShopifyOrder::where('order_date', '>=', $cutoff)->get();

        $data = [];

        foreach ($orders as $order) {
            $json = json_decode($order->raw_json, true);
            $lineItems = $json['line_items'] ?? [];
            $shippingAddress = $json['shipping_address'] ?? [];
            $fulfillment = $json['fulfillments'][0] ?? [];
        
            $shippingCost = $json['total_shipping_price_set']['shop_money']['amount'] ?? '0.00';
            $serviceType = $json['shipping_lines'][0]['title'] ?? ($fulfillment['tracking_company'] ?? 'Unknown');
            $destinationZip = $shippingAddress['zip'] ?? 'N/A';
        
            // Default to 1 bottle
            $dimensions = [
                'length' => 9,
                'width' => 6,
                'height' => 0.2,
                'weight' => 0.2, // fallback for 1 bottle (already in lb)
            ];
            
            foreach ($lineItems as $item) {
                $name = strtolower($item['title'] ?? '');
                $grams = $item['grams'] ?? 0;
            
                if (str_contains($name, '4-pack') || str_contains($name, 'bundle')) {
                    $dimensions = [
                        'length' => 9,
                        'width' => 6,
                        'height' => 0.1,
                        'weight' => $grams > 0
                            ? round($grams / 453.592, 4)  // convert g to lb
                            : round(0.2 / 453.592, 5),    // static 0.2g to lb
                    ];
                    break;
                } else {
                    $dimensions['weight'] = $grams > 0
                        ? round($grams / 453.592, 4)
                        : 0.2; // 1 bottle fallback in lb
                }
            }
        
            $data[] = [
                'Date' => $order->order_date,
                'Order ID' =>  $order->order_number,
                'Origin ZIP' => '92648',
                'Destination ZIP' => $destinationZip,
                'Distance (mi)' => '', // optional, blank
                'Weight (lb)' => $dimensions['weight'],
                'Length (in)' => $dimensions['length'],
                'Width (in)' => $dimensions['width'],
                'Height (in)' => $dimensions['height'],
                'Shipping Cost' => $shippingCost,
                'Service Type' => $serviceType,
            ];
        }
        

        return collect($data);
    }

    public function headings(): array
    {
        return [
            'Date', 'Order ID', 'Origin ZIP', 'Destination ZIP', 'Distance (mi)',
            'Weight (lb)', 'Length (in)', 'Width (in)', 'Height (in)',
            'Shipping Cost', 'Service Type',
        ];
    }
}
