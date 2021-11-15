<?php

namespace App\Models\Link;

use App\Models\Customer\Customer;
use App\Models\Order\Order;
use App\Models\Order\OrderProduct;
use App\Models\Order\OrderProductInfo;
use App\Models\Product\Product;
use App\Models\SalesOrder\CustomerSalesOrder;
use Framework\Model\EloquentModel;

/**
 * App\Models\Link\OrderAssociatedPre
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
 * @property int|null $image_id 品牌Id
 * @property string $run_id 下单页的run_id,绑定
 * @property int|null $status 预绑定的状态0:预绑定,1:绑定成功
 * @property string|null $Memo 对于该条记录做备注用的
 * @property int|null $associate_type 关联类型 1-关联囤货库存 2-关联新采购库存
 * @property string|null $CreateUserName 这条记录的创建者
 * @property string|null $CreateTime 这条记录的创建时间
 * @property string|null $UpdateUserName 这条记录的创建者
 * @property string|null $UpdateTime 这条记录的更新时间
 * @property string|null $ProgramCode 程序号
 * @property CustomerSalesOrder $customerSalesOrder 销售订单
 * @property Order $order 采购订单
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Link\OrderAssociatedPre newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Link\OrderAssociatedPre newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Link\OrderAssociatedPre query()
 * @mixin \Eloquent
 * @property-read \App\Models\SalesOrder\CustomerSalesOrder $salesOrder
 * @property-read \App\Models\Customer\Customer|null $buyer
 * @property-read \App\Models\Order\OrderProduct|null $orderProduct 采购订单产品
 * @property-read \App\Models\Order\OrderProductInfo|null $orderProductInfo
 * @property-read \App\Models\Product\Product|null $product
 * @property-read \App\Models\Customer\Customer|null $seller
 */
class OrderAssociatedPre extends EloquentModel
{
    protected $table = 'tb_sys_order_associated_pre';

    protected $fillable = [
        'sales_order_id',
        'sales_order_line_id',
        'order_id',
        'order_product_id',
        'qty',
        'product_id',
        'seller_id',
        'buyer_id',
        'image_id',
        'run_id',
        'status',
        'Memo',
        'associate_type',
        'CreateUserName',
        'CreateTime',
        'UpdateUserName',
        'UpdateTime',
        'ProgramCode',
    ];

    public function salesOrder()
    {
        return $this->belongsTo(CustomerSalesOrder::class, 'sales_order_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }

    public function orderProduct()
    {
        return $this->belongsTo(OrderProduct::class, 'order_product_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function orderProductInfo()
    {
        return $this->belongsTo(OrderProductInfo::class, 'order_product_id', 'order_product_id');
    }

    public function seller()
    {
        return $this->belongsTo(Customer::class, 'seller_id');
    }

    public function buyer()
    {
        return $this->belongsTo(Customer::class, 'buyer_id');
    }
}
