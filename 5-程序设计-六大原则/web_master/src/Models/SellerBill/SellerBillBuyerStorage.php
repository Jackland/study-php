<?php

namespace App\Models\SellerBill;

use App\Models\Customer\Customer;
use Framework\Model\EloquentModel;

/**
 * App\Models\SellerBill\SellerBillBuyerStorage
 *
 * @property int $id 主键ID
 * @property int $bill_id 账单总单主键ID
 * @property int $seller_id sellerId
 * @property int $buyer_id 支付的buyer
 * @property string $buyer_name buyer名称(带编号)
 * @property string $order_no 费用单编号
 * @property int $fee_order_type 费用单订单类型 1：销售单  2：RMA
 * @property string $fee_order_type_name 费用单订单类型名称
 * @property int|null $link_order_id 关联的订单记录主键ID。如果为销售单类型，则为销售单头表主键ID；如果为RMA，则为RMAID
 * @property string|null $sales_order_no 销售单单号
 * @property string|null $rma_no RMA申请编号
 * @property string $item_code 货物ItemCode
 * @property string $volume 货物仓租体积(立方米)
 * @property int $days 在库天数
 * @property int $qty 在库数量
 * @property string $fee_total 应收费用总额
 * @property string $fee_paid 实际支付费用总额
 * @property \Illuminate\Support\Carbon $fee_create_time 费用单创建时间
 * @property \Illuminate\Support\Carbon $payment_time 费用单支付时间
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property string $create_username 创建人
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerBill\SellerBillBuyerStorage newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerBill\SellerBillBuyerStorage newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerBill\SellerBillBuyerStorage query()
 * @mixin \Eloquent
 * @property-read \App\Models\Customer\Customer $customerToBuyer
 */
class SellerBillBuyerStorage extends EloquentModel
{
    protected $table = 'tb_seller_bill_buyer_storage';

    protected $dates = [
        'fee_create_time',
        'payment_time',
        'create_time',
    ];

    protected $fillable = [
        'bill_id',
        'seller_id',
        'buyer_id',
        'buyer_name',
        'order_no',
        'fee_order_type',
        'fee_order_type_name',
        'link_order_id',
        'sales_order_no',
        'rma_no',
        'item_code',
        'volume',
        'days',
        'qty',
        'fee_total',
        'fee_paid',
        'fee_create_time',
        'payment_time',
        'create_time',
        'create_username',
    ];

    public function customerToBuyer()
    {
        return $this->belongsTo(Customer::class, 'buyer_id', 'customer_id');
    }
}
