<?php

namespace App\DataTables;

use App\Models\ShopifyOrder;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;
use Yajra\DataTables\Html\Button;
class ShopifyOrdersDataTable extends DataTable
{
    /**
     * Build the DataTable from the query builder.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        // Optional: filter by product SKU coming from request()->product
        $product = (string) $this->request()->get('product', 'WATERMELON WAVE 3');

        // Try to push SKU filtering to SQL using JSON_SEARCH (MySQL 5.7+/8.0).
        // If your DB doesn't support JSON_* functions, remove this whereRaw and do client-side filtering instead.
        if ($product !== '') {
            $query->whereRaw(
                "JSON_SEARCH(raw_json, 'one', ?, NULL, '$.line_items[*].sku') IS NOT NULL",
                [$product]
            );
        }

        $dt = (new EloquentDataTable($query))
            // Decode raw_json lazily in each column closure
            ->addColumn('full_name', function ($o) {
                $raw = is_string($o->raw_json) ? (json_decode($o->raw_json, true) ?: []) : ($o->raw_json ?? []);
                $name = $o->customer_name ?: trim(($raw['customer']['first_name'] ?? '') . ' ' . ($raw['customer']['last_name'] ?? ''));
                return e($name ?: 'Unknown Customer');
            })
            ->addColumn('email', function ($o) {
                $raw = is_string($o->raw_json) ? (json_decode($o->raw_json, true) ?: []) : ($o->raw_json ?? []);
                $email = $o->email_address ?: ($raw['email'] ?? '');
                return e($email);
            })
            ->addColumn('shipping_method', function ($o) {
                $raw = is_string($o->raw_json) ? (json_decode($o->raw_json, true) ?: []) : ($o->raw_json ?? []);
                return e($raw['shipping_lines'][0]['title'] ?? 'N/A');
            })
            ->addColumn('shipping_cost', function ($o) {
                $raw = is_string($o->raw_json) ? (json_decode($o->raw_json, true) ?: []) : ($o->raw_json ?? []);
                $cost = (float)($raw['shipping_lines'][0]['price'] ?? 0);
                return '$' . number_format($cost, 2);
            })
            ->addColumn('tracking', function ($o) {
                $raw = is_string($o->raw_json) ? (json_decode($o->raw_json, true) ?: []) : ($o->raw_json ?? []);
                $num = $o->tracking_number ?: ($raw['fulfillments'][0]['tracking_number'] ?? 'Not shipped');
                $url = $o->tracking_url ?: ($raw['fulfillments'][0]['tracking_urls'][0] ?? '');
                return $url
                    ? '<a href="' . e($url) . '" target="_blank" rel="noopener noreferrer">' . e($num) . '</a>'
                    : e($num);
            })
            ->addColumn('order_total', function ($o) {
                $raw = is_string($o->raw_json) ? (json_decode($o->raw_json, true) ?: []) : ($o->raw_json ?? []);
                $total = (float)($raw['total_price'] ?? $o->paid_amount ?? 0);
                return '$' . number_format($total, 2);
            })
            ->addColumn('discount_codes', function ($o) {
                $raw = is_string($o->raw_json) ? (json_decode($o->raw_json, true) ?: []) : ($o->raw_json ?? []);
                $codes = $raw['discount_codes'] ?? [];
                if (empty($codes)) return e($o->coupon ?? '—');

                $parts = [];
                foreach ($codes as $d) {
                    $label = trim($d['code'] ?? '');
                    if (!empty($d['type']))   $label .= ' (' . trim($d['type']) . ')';
                    if (array_key_exists('amount', $d))  $label .= ' $' . number_format((float)$d['amount'], 2);
                    $parts[] = e($label);
                }
                return implode(', ', $parts);
            })
            ->addColumn('total_discount', function ($o) {
                $raw = is_string($o->raw_json) ? (json_decode($o->raw_json, true) ?: []) : ($o->raw_json ?? []);
                $disc = (float)($raw['total_discounts'] ?? $o->discount ?? 0);
                return '$' . number_format($disc, 2);
            })
            ->addColumn('order_date_formatted', fn ($o) => optional($o->order_date)->format('M d, Y H:i'))
            ->rawColumns(['tracking'])
            ->setRowId('id');

        return $dt;
    }

    /**
     * MUST return an Eloquent Builder (not a Collection).
     */
    public function query(ShopifyOrder $model): QueryBuilder
    {
        return $model->newQuery()
            ->orderByDesc('order_date')
            ->distinct('order_number')
            ->limit(1000); // guard; tune/replace with real pagination later
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('shopify-orders-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->buttons(
                Button::make('create'),
                Button::make('colvis'),
                Button::make('print'),
                Button::make('export'), 
                Button::make('reset'),
                Button::make('reload')->text('<i class="fa fa-sync"></i> Reload'),
            )
            ->parameters([
                'dom'     => 'Bfrtip',
                'responsive' => true,
                'autoWidth' => false,
                'processing' => true,
                'lengthMenu' => [10, 25, 50, 75, 100, 200, 300],
                'order'   => [[0, 'desc']],
                'buttons' => ['postExcel', 'postCsv', 'postPdf'],
                'ajax'    => [
                    'data' => 'function(d) { d.product = document.getElementById("product")?.value || ""; }'
                ]
            ]);
    }
    

    public function getColumns(): array
    {
        return [
            Column::make('order_number')->title('Order #'),
            Column::make('full_name')->orderable(false)->searchable(false),
            Column::make('email'),
            Column::make('order_total')->orderable(false)->searchable(false)->title('Paid'),
            Column::make('shipping_method')->title('Shipping'),
            Column::make('shipping_cost')->orderable(false)->searchable(false)->title('Ship Cost'),
            Column::make('discount_codes')->orderable(false)->searchable(true),
            Column::make('total_discount')->orderable(false)->searchable(false),
            Column::make('tracking')->orderable(false)->searchable(false),
            Column::make('order_date_formatted')->title('Order Date'),
        ];
    }

    protected function filename(): string
    {
        return 'ShopifyOrders_' . date('YmdHis');
    }
}
