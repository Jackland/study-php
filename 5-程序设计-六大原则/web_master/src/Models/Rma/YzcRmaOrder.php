<?php

namespace App\Models\Rma;

use App\Models\Customer\Customer;
use App\Models\FeeOrder\FeeOrder;
use App\Models\Link\OrderAssociated;
use App\Models\Order\Order;
use App\Models\SalesOrder\CustomerSalesOrder;
use Framework\Model\EloquentModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Rma\YzcRmaOrder
 *
 * @property int $id rma_id 自增主键
 * @property string|null $rma_order_id RMA 订单ID 日期+四位序列号，不足补0
 * @property int $order_id oc_order.order_id
 * @property string|null $from_customer_order_id RMA来自销售订单
 * @property int $seller_id seller_id
 * @property int $buyer_id buyer_id
 * @property int|null $admin_status Admin 更改状态值
 * @property int|null $seller_status Seller 更改状态值
 * @property int|null $cancel_rma 取消RMA
 * @property int|null $solve_rma 解决RMA
 * @property string|null $memo 备注
 * @property string|null $create_user_name 创建者
 * @property string|null $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property string|null $update_time 修改时间
 * @property string|null $program_code 程序号
 * @property string|null $processed_date 退返品完成时间
 * @property int|null $order_type 1:销售订单退货2：采购订单退货
 * @property int $is_timeout seller是否超时未处理rma
 * @property-read \App\Models\Customer\Customer $buyer
 * @property-read \App\Models\SalesOrder\CustomerSalesOrder|null $customerSalesOrder
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\FeeOrder\FeeOrder[] $feeOrders
 * @property-read int|null $fee_orders_count
 * @property-read OrderAssociated|\Framework\Model\Eloquent\Builder|\Builder|\Model|object|null $associate_product
 * @property-read \App\Models\Order\Order $order
 * @property-read \App\Models\Customer\Customer $seller
 * @property-read \App\Models\Rma\YzcRmaOrderProduct $yzcRmaOrderProduct
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Rma\YzcRmaOrder newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Rma\YzcRmaOrder newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Rma\YzcRmaOrder query()
 * @mixin \Eloquent
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Rma\YzcRmaFile[] $buyerFiles
 * @property-read int|null $buyer_files_count
 */
class YzcRmaOrder extends EloquentModel
{
    public const CREATED_AT = 'create_time';
    public const UPDATED_AT = 'update_time';

    protected $table = 'oc_yzc_rma_order';
    public $timestamps = true;

    protected $fillable = [
        'rma_order_id',
        'order_id',
        'from_customer_order_id',
        'seller_id',
        'buyer_id',
        'admin_status',
        'seller_status',
        'cancel_rma',
        'solve_rma',
        'memo',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
        'processed_date',
        'order_type',
        'is_timeout',
    ];

    public function yzcRmaOrderProduct()
    {
        return $this->hasOne(YzcRmaOrderProduct::class, 'rma_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function customerSalesOrder()
    {
        return $this->belongsTo(CustomerSalesOrder::class, 'from_customer_order_id', 'order_id')
            ->where('buyer_id', $this->buyer_id);
    }

    public function buyer()
    {
        return $this->belongsTo(Customer::class, 'buyer_id');
    }

    public function seller()
    {
        return $this->belongsTo(Customer::class, 'seller_id');
    }

    public function buyerFiles()
    {
        return $this->hasMany(YzcRmaFile::class, 'rma_id', 'id')->where('type', 1);
    }

    public function feeOrders()
    {
        return $this->morphMany(FeeOrder::class, null, 'order_type_alias', 'order_id');
    }

    /**
     * @return OrderAssociated|\Framework\Model\Eloquent\Builder|Builder|Model|object|null
     */
    public function getAssociateProductAttribute()
    {
        $salesOrder = $this->customerSalesOrder;
        $rmaOrderProduct = $this->yzcRmaOrderProduct;
        return OrderAssociated::query()
            ->where([
                'sales_order_id' => $salesOrder->id,
                'order_product_id' => $rmaOrderProduct->order_product_id,
            ])
            ->first();
    }
}
