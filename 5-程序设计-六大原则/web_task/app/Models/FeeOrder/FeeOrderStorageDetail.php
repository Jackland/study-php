<?php

namespace App\Models\FeeOrder;


use App\Models\StorageFee\StorageFee;
use Illuminate\Database\Eloquent\Model;

/**
 * \App\Models\FeeOrder\FeeOrderStorageDetail
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
 * @property-read \App\Models\FeeOrder\FeeOrder $feeOrder
 * @property-read \App\Models\StorageFee\StorageFee $storageFee
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\FeeOrder\FeeOrderStorageDetail newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\FeeOrder\FeeOrderStorageDetail newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\FeeOrder\FeeOrderStorageDetail query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\FeeOrder\FeeOrderStorageDetail whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\FeeOrder\FeeOrderStorageDetail whereDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\FeeOrder\FeeOrderStorageDetail whereFeeOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\FeeOrder\FeeOrderStorageDetail whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\FeeOrder\FeeOrderStorageDetail whereSalesOrderLineId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\FeeOrder\FeeOrderStorageDetail whereStorageFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\FeeOrder\FeeOrderStorageDetail whereStorageFeeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\FeeOrder\FeeOrderStorageDetail whereStorageFeePaid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\FeeOrder\FeeOrderStorageDetail whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class FeeOrderStorageDetail extends Model
{
    protected $table = 'oc_fee_order_storage_detail';
    public $timestamps = true;

    public function feeOrder()
    {
        return $this->belongsTo(FeeOrder::class);
    }


    public function storageFee()
    {
        return $this->belongsTo(StorageFee::class);
    }
}
