<?php

namespace App\Models\Link;

use App\Enums\YzcRmaOrder\RmaType;
use App\Models\Customer\Customer;
use App\Models\Order\Order;
use App\Models\Order\OrderCombo;
use App\Models\Order\OrderProduct;
use App\Models\Order\OrderProductInfo;
use App\Models\Product\Product;
use App\Models\Rma\YzcRmaOrder;
use App\Models\SalesOrder\CustomerSalesOrder;
use App\Models\SalesOrder\CustomerSalesOrderLine;
use Framework\Model\EloquentModel;

/**
 * App\Models\Link\OrderAssociated
 *
 * @property int $id
 * @property int|null $sales_order_id 销售订单ID
 * @property int|null $sales_order_line_id 销售订单明细ID
 * @property int|null $order_id 采购订单ID
 * @property int|null $order_product_id 采购订单产品ID
 * @property int|null $qty 采购数量
 * @property int|null $product_id 产品Id
 * @property int|null $seller_id 卖家id
 * @property int|null $buyer_id 买家ID
 * @property int|null $pre_id 预绑定的id
 * @property string $coupon_amount 优惠券折扣
 * @property string $campaign_amount 这条记录的创建者
 * @property int|null $image_id 品牌Id
 * @property string|null $Memo 对于该条记录做备注用的
 * @property string|null $CreateUserName 这条记录的创建者
 * @property string|null $CreateTime 这条记录的创建时间
 * @property string|null $UpdateUserName 这条记录的创建者
 * @property string|null $UpdateTime 这条记录的更新时间
 * @property string|null $ProgramCode 程序号
 * @property-read \App\Models\Customer\Customer|null $buyer
 * @property-read \App\Models\SalesOrder\CustomerSalesOrder|null $customerSalesOrder
 * @property-read \App\Models\SalesOrder\CustomerSalesOrderLine|null $customerSalesOrderLine
 * @property-read \App\Models\Order\Order|null $order
 * @property-read \App\Models\Order\OrderProduct|null $orderProduct
 * @property-read \App\Models\Order\OrderProductInfo|null $orderProductInfo
 * @property-read \App\Models\Product\Product|null $product
 * @property-read \App\Models\Customer\Customer|null $seller
 * @property-read YzcRmaOrder[]|\Framework\Model\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Query\Builder[]|\Illuminate\Support\Collection $rma_list
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Link\OrderAssociated newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Link\OrderAssociated newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Link\OrderAssociated query()
 * @mixin \Eloquent
 * @property bool|null $is_sync 是否同步至在库系统 0 未同步  1 同步
 * @property bool|null $last 是否是最后一个库存
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Order\OrderCombo[] $orderCombos
 * @property-read int|null $order_combos_count
 */
class OrderAssociated extends EloquentModel
{
    protected $table = 'tb_sys_order_associated';

    protected $fillable = [
        'sales_order_id',
        'sales_order_line_id',
        'order_id',
        'order_product_id',
        'qty',
        'product_id',
        'seller_id',
        'buyer_id',
        'pre_id',
        'image_id',
        'Memo',
        'CreateUserName',
        'CreateTime',
        'UpdateUserName',
        'UpdateTime',
        'ProgramCode',
    ];

    public function customerSalesOrder()
    {
        return $this->belongsTo(CustomerSalesOrder::class, 'sales_order_id');
    }

    public function customerSalesOrderLine()
    {
        return $this->belongsTo(CustomerSalesOrderLine::class, 'sales_order_line_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function orderProduct()
    {
        return $this->belongsTo(OrderProduct::class, 'order_product_id');
    }

    public function orderProductInfo()
    {
        return $this->belongsTo(OrderProductInfo::class, 'order_product_id', 'order_product_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function seller()
    {
        return $this->belongsTo(Customer::class, 'seller_id');
    }

    public function buyer()
    {
        return $this->belongsTo(Customer::class, 'buyer_id');
    }

    public function orderCombos()
    {
        return $this->hasMany(OrderCombo::class, 'order_product_id', 'order_product_id');
    }

    /**
     * 获取绑定明细的所有相关rma
     * @return YzcRmaOrder[]|\Framework\Model\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Query\Builder[]|\Illuminate\Support\Collection
     */
    public function getRmaListAttribute()
    {
        return YzcRmaOrder::query()->alias('ro')
            ->select(['ro.*'])
            ->with(['yzcRmaOrderProduct'])
            ->leftJoin('oc_yzc_rma_order_product as rop', 'rop.rma_id', '=', 'ro.id')
            ->where([
                'ro.order_id' => $this->order_id,
                'ro.from_customer_order_id' => $this->customerSalesOrder->order_id,
                'ro.buyer_id' => $this->buyer_id,
                'ro.order_type' => RmaType::SALES_ORDER,
                'rop.order_product_id' => $this->order_product_id,
            ])
            ->get();
    }
}
