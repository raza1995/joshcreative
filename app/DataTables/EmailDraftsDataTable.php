<?php

namespace App\DataTables;

use App\Models\EmailDraft;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class EmailDraftsDataTable extends DataTable
{
    public function dataTable($query): EloquentDataTable
{   

    return (new EloquentDataTable($query))
        ->addColumn('checkbox', function ($row) {
            return '<div class="checkbox-container" style="cursor: pointer; text-align: center;">
                        <input type="checkbox" class="select-draft" value="' . $row->id . '">
                    </div>';
        })
        ->addColumn('customer_name', function ($row) {
            return $row->shopifyOrder ? $row->shopifyOrder->customer_name : '-';
        })
        ->addColumn('email_address', function ($row) {
            return $row->shopifyOrder ? $row->shopifyOrder->email_address : '-';
        })
        ->editColumn('status', function ($data) {
            $badgeClass = match ($data->status) {
                'approved' => 'bg-success',
                'pending' => 'bg-warning text-dark',
                'disapproved' => 'bg-danger',
                default => 'bg-secondary',
            };
            return '<span class="badge ' . $badgeClass . '">' . ucfirst($data->status) . '</span>';
        })
        ->addColumn('actions', function ($row) {
            return '
                <div style="display: flex; gap: 5px;">
                    <a href="' . route('email-draft.edit', $row->id) . '" class="btn btn-warning btn-sm"><i class="fas fa-edit"></i></a>
                    <button onclick="approveDraft(' . $row->id . ')" class="btn btn-sm btn-success"><i class="fas fa-check"></i></button>
                    <button onclick="disapproveDraft(' . $row->id . ')" class="btn btn-sm btn-danger"><i class="fas fa-times"></i></button>
                    <button onclick="sendEmail(' . $row->id . ')" class="btn btn-sm btn-info text-white"><i class="fas fa-paper-plane"></i></button>
                </div>
            ';
        })
        
        ->editColumn('body', function ($data) {
            $body = htmlspecialchars($data->body, ENT_QUOTES, 'UTF-8');
            return '
                <span class="email-body" 
                    data-id="' . $data->id . '" 
                    data-subject="' . htmlspecialchars($data->subject, ENT_QUOTES, 'UTF-8') . '" 
                    data-body="' . $body . '" 
                    style="cursor: pointer; color: #007bff; text-decoration: underline;">
                    ' . (mb_strlen($body) > 50 ? mb_substr($body, 0, 50) . '...' : $body) . '
                </span>
            ';
        })
        ->editColumn('shopify_order_id', function ($row) {
            if ($row->shopifyOrder) {
                $order = $row->shopifyOrder;
        
                return '<span class="shopify-order-link" 
                            data-order=\'' . json_encode($order) . '\'
                            style="cursor: pointer; color: #007bff; text-decoration: underline;">
                            ' . htmlspecialchars($order->order_number, ENT_QUOTES, 'UTF-8') . '
                        </span>';
            }
            return '-';
        })
        
        
        
        ->editColumn('created_at', fn($data) => $data->created_at->format('Y-m-d h:i A'))
        ->editColumn('updated_at', fn($data) => $data->updated_at->format('Y-m-d h:i A'))
        ->rawColumns(['checkbox', 'status', 'actions', 'body', 'shopify_order_id'])
        ->setRowId('id');
}


    public function query(EmailDraft $model)
    {
        return $model->newQuery()->with('shopifyOrder')->orderByDesc('created_at');
    }



public function html(): HtmlBuilder
{
    return $this->builder()
        ->setTableId('emailDraftsTable')
        ->columns($this->getColumns())
        ->minifiedAjax()
        ->parameters([
            'dom' => 'Bfrtip',
            'responsive' => true,
            'autoWidth' => false,
            'processing' => true,
            'pagingType' => 'full_numbers',
            'lengthMenu' => [10, 25, 50, 75, 100],
            'language' => [
                'paginate' => [
                    'next' => '›',
                    'previous' => '‹',
                    'first' => '«',
                    'last' => '»',
                ],
            ],
            'buttons' => ['create', 'export', 'print', 'reset', 'reload'],
            'theme' => 'bootstrap5',
            'initComplete' => function() {
                return 'function(settings, json) {
                    var table = settings.oInstance.api();

                    // Pagination loading indicator
                    table.on("preDraw", function() {
                        if ($(".loading-overlay").length === 0) {
                            $("body").append("<div class=\"loading-overlay\"><div class=\"spinner-border text-primary\" role=\"status\"></div></div>");
                        }
                    });

                    table.on("draw", function() {
                        $(".loading-overlay").remove();
                    });

                    // Column search functionality
                    this.api().columns().every(function() {
                        var column = this;
                        var input = document.createElement("input");
                        input.placeholder = "Search " + $(column.header()).text();
                        $(input).appendTo($(column.footer()).empty())
                               .on("keyup change clear", function() {
                                   if (column.search() !== this.value) {
                                       column.search(this.value).draw();
                                   }
                               });
                    });
                }';
            }
        ]);
}


    public function getColumns(): array
{
    return [
        Column::computed('checkbox')
            ->exportable(false)
            ->printable(false)
            ->width(30)
            ->addClass('text-center')
            ->title('<input type="checkbox" id="select-all">'),

        Column::make('subject')->title('Subject'),
        Column::make('body')->title('Body'),
        Column::make('status')->title('Status'),

        Column::computed('customer_name')
            ->title('Customer Name')
            ->addClass('text-center'),

        Column::computed('email_address')
            ->title('Email Address')
            ->addClass('text-center'),

            Column::computed('shopify_order_id')
            ->title('Shopify Order')
            ->addClass('text-center')
            ->defaultContent('-'),
        Column::computed('actions')
            ->exportable(false)
            ->printable(false)
            ->width(150)
            ->addClass('text-center'),

        Column::make('created_at')->title('Created At'),
        Column::make('updated_at')->title('Updated At'),
    ];
}


    protected function filename(): string
    {
        return 'EmailDrafts_' . date('YmdHis');
    }


    
}
