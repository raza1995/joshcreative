<?php

namespace App\DataTables;

use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;

class SalesDataDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
       
        return (new EloquentDataTable($query))
            ->addColumn('action', function($row) {

                if ($row->dj_user_id) {
                    return '<a href="'.route('sales.journey', ['user_id' => $row->dj_user_id]).'" class="btn btn-sm btn-primary">See Journey</a>';
                } else {
                    return '<button class="btn btn-sm btn-secondary" disabled>No Journey Available</button>';
                }            })
            ->editColumn('status', function ($data) {
                if ($data->status == 'Purchased') {
                    return '<span class="badge bg-success">Purchased</span>';
                } elseif ($data->status == 'added_to_cart') {
                    return '<span class="badge bg-warning">Added to Cart</span>';
                }
                elseif ($data->status == 'seasonal_application') {
                    return '<span class="badge bg-info">Seasonal Application</span>';
                }
                return $data->status;
            })
            ->editColumn('created_at', function ($data) {
                return $data->created_at->format('Y-m-d H:i:s');
            })
            ->editColumn('updated_at', function ($data) {
                return $data->updated_at->format('Y-m-d H:i:s');
            })
            ->rawColumns(['status', 'action'])
            ->setRowId('id');
    }
    

    /**
     * Get the query source of dataTable.
     */
    public function query(Sale $model): QueryBuilder
    {
        return $model->newQuery()->orderBy('created_at', 'desc');
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
                    ->setTableId('salesdata-table')
                    ->columns($this->getColumns())
                    ->minifiedAjax()
                    ->orderBy(1, 'desc')
                    ->selectStyleSingle()
                    ->buttons([
                        Button::make('export'),
                        Button::make('print'),
                        Button::make('reset'),
                        Button::make('reload'),
                        Button::make('collection')
                            ->text('Upload Sales Data')
                            ->action('function ( e, dt, node, config ) {
                                $("#uploadSalesModal").modal("show");
                            }')
                    ])
                    ->parameters([
                        'dom' => 'Bfrtip',
                        'initComplete' => 'function() {
                            var api = this.api();
                            $(\'#salesdata-table_filter\').append(\'<select id="status-filter" class="ms-2 btn btn-secondary buttons-collection dropdown-toggle btn-primary"><option value="" class="dt-button dropdown-item">Filter by Status</option><option value="Purchased" class="dt-button dropdown-item">Purchased</option><option value="added_to_cart" class="dt-button dropdown-item">Added to Cart</option></select>\');
                            $(\'#status-filter\').on(\'change\', function() {
                                var val = $.fn.dataTable.util.escapeRegex(
                                    $(this).val()
                                );
                                api.column(7) // Adjust the column index for your status column
                                    .search(val ? \'^\'+val+\'$\' : \'\', true, false)
                                    .draw();
                            });
                        }'
                    ]);
    }

    /**
     * Get the dataTable columns definition.
     */
    public function getColumns(): array
    {
        return [

            Column::make('id'),
            Column::make('email'),
            Column::make('price')->title('Product Price'),
            Column::make('ip_address'),
            Column::make('utm_source'),
            Column::make('total_amount')->title('Paid Price'),
            Column::make('promo_code')->title('Coupon Code'),
            Column::make('status')->title('Status'), 
            Column::make('user_id')->title('Teachable User Id'),
            Column::make('earned_commission'),
            Column::make('created_at'),
            Column::make('updated_at'),
            Column::make('action'),
        ];
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'Sales_' . date('YmdHis');
    }
}
