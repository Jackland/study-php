<?php

namespace App\Models\StorageFee;

use App\Models\Customer\Customer;
use App\Models\Link\OrderAssociated;
use App\Models\Order\Order;
use App\Models\Order\OrderProduct;
use App\Models\Product\Product;
use App\Models\SalesOrder\CustomerSalesOrder;
use App\Models\SalesOrder\CustomerSalesOrderLine;
use Framework\Model\Eloquent\Builder;
use Framework\Model\EloquentModel;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\StorageFee\StorageFee
 *
 * @property int $id
 * @property int $buyer_id buyer
 * @property int $country_id 国家ID
 * @property int $order_id 采购单ID
 * @property int $order_product_id 采购单产品明细的id
 * @property int $product_id 产品ID
 * @property string $product_sku sku
 * @property string $product_size_json 产品尺寸JSON，单位英寸
 * @property string $volume_m 体积，单位立方米，向上保留四位小数
 * @property string $fee_total 当前仓租费用
 * @property string $fee_paid 已付仓租费
 * @property string $fee_unpaid 未付仓租费
 * @property int $days 计费天数
 * @property int $status 状态
 * @property int|null $sales_order_id 绑定销售订单ID
 * @property int|null $sales_order_line_id 销售订单明细id
 * @property int|null $end_type 完结类型\r\n1:销售出库\r\n2:采购RMA 参考StorageFeeEndType.php
 * @property int $transaction_type_id 入仓租时的交易方式，与交易方式定义一样，0-普通采购，2-现货。目前只做了现货，如果有其他交易再加
 * @property int|null $agreement_id 复杂交易协议id
 * @property \Carbon\Carbon $created_at 创建时间
 * @property \Carbon\Carbon $updated_at 更新时间
 * @property-read \App\Models\Customer\Customer $customer
 * @property-read \App\Models\SalesOrder\CustomerSalesOrder|null $customerSalesOrder
 * @property-read \App\Models\SalesOrder\CustomerSalesOrderLine|null $customerSalesOrderLine
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\StorageFee\StorageFeeDetail[] $details
 * @property-read int|null $details_count
 * @property-read OrderAssociated|\Builder|\Model|object|null $order_associate
 * @property-read \App\Models\Order\Order $order
 * @property-read \App\Models\Order\OrderProduct $orderProduct
 * @property-read \App\Models\Product\Product $product
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\StorageFee\StorageFee newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\StorageFee\StorageFee newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\StorageFee\StorageFee query()
 * @mixin \Eloquent
 * @property bool|null $transaction_type_id 入仓租时的交易方式，与交易方式定义一样，0-普通采购，2-现货。目前只做了现货，如果有其他交易再加
 * @property int|null $agreement_id 复杂交易协议id
 */
class StorageFee extends EloquentModel
{
    protected $table = 'oc_storage_fee';
    public $timestamps = true;

    protected $fillable = [
        'buyer_id',
        'country_id',
        'order_id',
        'order_product_id',
        'product_id',
        'product_sku',
        'product_size_json',
        'volume_m',
        'fee_total',
        'fee_paid',
        'fee_unpaid',
        'days',
        'status',
        'sales_order_id',
        'sales_order_line_id',
        'end_type',
        'transaction_type_id',
        'agreement_id'
    ];

    public function details()
    {
        return $this->hasMany(StorageFeeDetail::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'buyer_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function orderProduct()
    {
        return $this->belongsTo(OrderProduct::class, 'order_product_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function customerSalesOrder()
    {
        return $this->belongsTo(CustomerSalesOrder::class, 'sales_order_id');
    }

    public function customerSalesOrderLine()
    {
        return $this->belongsTo(CustomerSalesOrderLine::class, 'sales_order_line_id');
    }

    /**
     * @return OrderAssociated|Builder|Model|object|null
     */
    public function getOrderAssociateAttribute()
    {
        return OrderAssociated::query()
            ->where([
                'sales_order_id' => $this->sales_order_id,
                'sales_order_line_id' => $this->sales_order_line_id,
            ])
            ->first();
    }
}
