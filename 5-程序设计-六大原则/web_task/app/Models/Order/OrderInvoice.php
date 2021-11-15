<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class OrderInvoice extends Model
{
    protected $table = 'oc_order_invoice';
    public $timestamps = false;
    protected $connection = 'mysql_proxy';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'buyer_id',
        'seller_id',
        'serial_number',
        'status',
        'pdf_file_id',
        'order_ids',
        'create_time',
        'update_time',
    ];
}
