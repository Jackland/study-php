<?php

namespace App\Models\SalesOrder;

use App\Models\Product\Product;
use Framework\Model\EloquentModel;

/**
 * App\Models\SalesOrder\PurchasePayRecord
 *
 * @property int $id 自增id
 * @property int|null $order_id 销售订单id
 * @property int|null $line_id sales_order_line 销售订单明细id
 * @property string|null $item_code line表中的sku
 * @property int|null $product_id 产品id
 * @property int|null $sales_order_quantity 订单需要的数量
 * @property int|null $quantity 需要购买的数量
 * @property int $type_id 交易类型，用于持久化购物车环境的交易类型，type_id字典值维护在oc_setting表,code:transaction_type
 * @property int|null $agreement_id 各个交易类型设计的协议记录主键ID，若为普通类别，值为null
 * @property int|null $customer_id 添加记录的buyer id
 * @property int|null $seller_id 购买的seller id
 * @property string $run_id 生成时间，用于生成下单页
 * @property string|null $memo 备注
 * @property string|null $create_user_name 创建者
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $update_user_name 更新者
 * @property \Illuminate\Support\Carbon|null $update_time 更新时间
 * @property string|null $program_code 程序号
 * @property-read  Product $product 销售单
 * @property-read  CustomerSalesOrder $salesOrder 销售单
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\PurchasePayRecord newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\PurchasePayRecord newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\PurchasePayRecord query()
 * @mixin \Eloquent
 */
class PurchasePayRecord extends EloquentModel
{
    protected $table = 'tb_purchase_pay_record';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'order_id',
        'line_id',
        'item_code',
        'product_id',
        'sales_order_quantity',
        'quantity',
        'type_id',
        'agreement_id',
        'customer_id',
        'seller_id',
        'run_id',
        'memo',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function salesOrder()
    {
        return $this->belongsTo(CustomerSalesOrder::class, 'order_id');
    }
}
