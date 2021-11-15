<?php

namespace App\Models\FeeOrder;

use App\Models\SalesOrder\CustomerSalesOrderLine;
use App\Models\StorageFee\StorageFee;
use Framework\Model\EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\FeeOrder\FeeOrderStorageDetail
 *
 * @property int $id
 * @property int $fee_order_id 主表id，oc_fee_order表id
 * @property int $storage_fee_id 仓租id，oc_storage_fee表id
 * @property int|null $sales_order_line_id 销售订单明细id
 * @property int $days 第几天
 * @property float $storage_fee 费用
 * @property float $storage_fee_paid 当前已支付费用
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \App\Models\SalesOrder\CustomerSalesOrderLine|null $customerSalesOrderLine
 * @property-read \App\Models\FeeOrder\FeeOrder $feeOrder
 * @property-read \App\Models\StorageFee\StorageFee $storageFee
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\FeeOrder\FeeOrderStorageDetail newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\FeeOrder\FeeOrderStorageDetail newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\FeeOrder\FeeOrderStorageDetail query()
 * @mixin \Eloquent
 */
class FeeOrderStorageDetail extends EloquentModel
{
    protected $table = 'oc_fee_order_storage_detail';
    public $timestamps = true;

    protected $fillable = [
        'fee_order_id',
        'storage_fee_id',
        'sales_order_line_id',
        'days',
        'storage_fee',
    ];

    /**
     * 费用单
     *
     * @return BelongsTo
     */
    public function feeOrder()
    {
        return $this->belongsTo(FeeOrder::class);
    }

    /**
     * 仓租
     *
     * @return BelongsTo
     */
    public function storageFee()
    {
        return $this->belongsTo(StorageFee::class);
    }

    /**
     * 销售订单明细
     *
     * @return BelongsTo
     */
    public function customerSalesOrderLine()
    {
        return $this->belongsTo(CustomerSalesOrderLine::class, 'sales_order_line_id');
    }
}
