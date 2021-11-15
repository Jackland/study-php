<?php

namespace App\Models\Delivery;

use App\Models\Product\Product;
use App\Models\SalesOrder\CustomerSalesOrder;
use App\Models\SalesOrder\CustomerSalesOrderLine;
use Framework\Model\EloquentModel;

/**
 * 出库表
 * Class DeliveryLine
 *
 * @package App\Models\Delivery
 * @property int $Id 自增主键
 * @property int $SalesHeaderId 销售订单头表主键Id(Order.id)
 * @property int $SalesLineId 销售订单明细表主键Id(Order_line.id)
 * @property int $TrackingId TrackingTd(order_tracking.id)
 * @property int $ProductId 发货产品Id(sku对应的skuID)
 * @property int $DeliveryType 发货类型(暂只有“销售发货类型”，值为1)
 * @property int $DeliveryQty 发货数量
 * @property int $CostId 库存表ID(库存表主键id)
 * @property float|null $SalesPrice 销售单价,多seller时为null
 * @property float|null $SalesCost 销售成本
 * @property bool $type 发货订单类型,字典数据维护在tb_sys_dictionary BUYER_DELIVERY_TYPE
 * @property string|null $Memo 备注
 * @property string|null $create_user_name 创建者
 * @property string|null $create_time 创建时间
 * @property string|null $update_user_name 更新者
 * @property string|null $update_time 更新时间
 * @property string|null $ProgramCode 程序号
 * @property-read \App\Models\Delivery\CostDetail $costDetail
 * @property-read \App\Models\Product\Product $product
 * @property-read \App\Models\SalesOrder\CustomerSalesOrder $salesOrder
 * @property-read \App\Models\SalesOrder\CustomerSalesOrderLine $salesOrderLine
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Delivery\DeliveryLine newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Delivery\DeliveryLine newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Delivery\DeliveryLine query()
 * @mixin \Eloquent
 */
class DeliveryLine extends EloquentModel
{
    protected $table = 'tb_sys_delivery_line';
    protected $primaryKey = 'Id';

    public function salesOrder()
    {
        return $this->belongsTo(CustomerSalesOrder::class, 'SalesHeaderId');
    }

    public function salesOrderLine()
    {
        return $this->belongsTo(CustomerSalesOrderLine::class, 'SalesLineId');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'ProductId');
    }

    public function costDetail()
    {
        return $this->belongsTo(CostDetail::class, 'CostId');
    }
}
