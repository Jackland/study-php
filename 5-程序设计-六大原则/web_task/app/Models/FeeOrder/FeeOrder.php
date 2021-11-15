<?php

namespace App\Models\FeeOrder;

use App\Models\Customer\Customer;
use App\Models\StorageFee\StorageFee;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\FeeOrder\FeeOrder
 *
 * @property int $id
 * @property string $order_no 费用单订单编号
 * @property int $order_type 订单类型
 * 1-销售订单
 * 2-rma订单...
 * @property string $order_type_alias 订单类型别名
 * 1-order_sales
 * 2-order_rma
 * @property int $order_id 订单id，根据不同的订单类型，关联不同的订单
 * 1-销售订单 tb_sys_customer_sales_order
 * 2-RMA oc_yzc_rma_order
 * @property int|null $buyer_id 费用单创建buyer
 * @property int $fee_type 费用类型
 * 1-仓租费
 * .......
 * @property float $fee_total 总费用
 * @property string|null $payment_method 支付方式
 * @property float $balance 使用余额金额
 * @property float $poundage 该笔费用单的手续费
 * @property string|null $comment 购买备注
 * @property int $status 状态
 * @property string|null $paid_at 支付时间
 * @property string|null $purchase_run_id tb_purchase_pay_record表的run id，用于二次支付使用
 * @property \Illuminate\Support\Carbon $created_at 创建时间
 * @property \Illuminate\Support\Carbon $updated_at 更新时间
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\FeeOrder\FeeOrder newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\FeeOrder\FeeOrder newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\FeeOrder\FeeOrder query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\FeeOrder\FeeOrder whereBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\FeeOrder\FeeOrder whereBuyerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\FeeOrder\FeeOrder whereComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\FeeOrder\FeeOrder whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\FeeOrder\FeeOrder whereFeeTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\FeeOrder\FeeOrder whereFeeType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\FeeOrder\FeeOrder whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\FeeOrder\FeeOrder whereOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\FeeOrder\FeeOrder whereOrderNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\FeeOrder\FeeOrder whereOrderType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\FeeOrder\FeeOrder whereOrderTypeAlias($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\FeeOrder\FeeOrder wherePaidAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\FeeOrder\FeeOrder wherePaymentMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\FeeOrder\FeeOrder wherePoundage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\FeeOrder\FeeOrder wherePurchaseRunId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\FeeOrder\FeeOrder whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\FeeOrder\FeeOrder whereUpdatedAt($value)
 * @mixin \Eloquent
 * @property int $is_show 是否显示
 * 1-是
 * 0-否
 * @property-read \App\Models\Customer\Customer|null $buyer
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\FeeOrder\FeeOrderStorageDetail[] $storageDetails
 * @property-read int|null $storage_details_count
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\FeeOrder\FeeOrder whereIsShow($value)
 * @property-read StorageFee[]|Collection $storage_fees
 */
class FeeOrder extends Model
{
    protected $table = 'oc_fee_order';

    public function buyer()
    {
        return $this->belongsTo(Customer::class, 'buyer_id');
    }

    public function storageDetails()
    {
        return $this->hasMany(FeeOrderStorageDetail::class);
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function getStorageFeesAttribute()
    {
        return StorageFee::whereIn('id', $this->storageDetails->pluck('storage_fee_id')->toArray())
            ->get();
    }
}
