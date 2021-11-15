<?php

namespace App\Models\Order;

use App\Components\RemoteApi;
use App\Components\Storage\StorageCloud;
use App\Models\Customer\Customer;
use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use Framework\Model\EloquentModel;

/**
 * App\Models\Order\OrderInvoice
 *
 * @property int $id
 * @property int $buyer_id CustomerId
 * @property int $seller_id CustomerId
 * @property string $serial_number 序列号
 * @property int $status 1:生成中 2:生成失败 3:已生成
 * @property string|null $file_path 文件路径
 * @property string|null $order_ids 目标订单IDS
 * @property \Illuminate\Support\Carbon $create_time 添加时间
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Order\OrderInvoice newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Order\OrderInvoice newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Order\OrderInvoice query()
 * @mixin \Eloquent
 * @property-read \App\Models\CustomerPartner\CustomerPartnerToCustomer $customerPartnerToCustomer
 * @property-read mixed $bol_files
 * @property-read \App\Models\Customer\Customer $seller
 */
class OrderInvoice extends EloquentModel
{
    protected $table = 'oc_order_invoice';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'buyer_id',
        'seller_id',
        'serial_number',
        'status',
        'file_path',
        'order_ids',
        'create_time',
        'update_time',
    ];

    protected $appends = ['invoice_files'];

    public function seller()
    {
        return $this->hasOne(Customer::class, 'customer_id', 'seller_id');
    }

    public function customerPartnerToCustomer()
    {
        return $this->hasOne(CustomerPartnerToCustomer::class, 'customer_id', 'seller_id');
    }

    private $_cachedPdfFiles = [];

    public function getInvoiceFilesAttribute()
    {
        if (!isset($this->_cachedPdfFiles[$this->id])) {
            $this->_cachedPdfFiles[$this->id] = $this->file_path ? StorageCloud::root()->getUrl($this->file_path) : collect();
        }
        return $this->_cachedPdfFiles[$this->id];
    }
}
