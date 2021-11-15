<?php

namespace App\Models\Rebate;

use App\Models\Order\Order;
use App\Models\Order\OrderProduct;
use App\Models\Product\Product;
use App\Models\Rma\YzcRmaOrder;
use Framework\Model\EloquentModel;

/**
 * \App\Models\Rebate\RebateAgreementOrder
 *
 * @property int $id 自增主键
 * @property int $agreement_id 返点协议主表ID
 * @property int $item_id 返点协议从表ID
 * @property int $product_id 产品ID
 * @property int $qty 数量
 * @property int $order_id 订单ID
 * @property int $order_product_id 订单产品主键ID
 * @property int|null $rma_id RMA ID
 * @property int|null $rma_product_id RMA 产品主键ID
 * @property bool $type 数据类型 (1:采购订单，2:RMA)
 * @property string|null $memo 备注
 * @property string|null $create_user_name 创建者
 * @property string $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property string $update_time 修改时间
 * @property string|null $program_code 程序号
 * @property-read \App\Models\Order\Order $order
 * @property-read \App\Models\Order\OrderProduct $orderProduct
 * @property-read \App\Models\Product\Product $product
 * @property-read \App\Models\Rebate\RebateAgreement $rebateAgreement
 * @property-read \App\Models\Rebate\RebateAgreementItem $rebateAgreementItem
 * @property-read \App\Models\Rma\YzcRmaOrder|null $rmaOrder
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Rebate\RebateAgreementOrder newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Rebate\RebateAgreementOrder newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Rebate\RebateAgreementOrder query()
 * @mixin \Eloquent
 */
class RebateAgreementOrder extends EloquentModel
{
    protected $table = 'oc_rebate_agreement_order';

    public function rebateAgreement()
    {
        return $this->belongsTo(RebateAgreement::class, 'agreement_id');
    }

    public function rebateAgreementItem()
    {
        return $this->belongsTo(RebateAgreementItem::class, 'item_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function orderProduct()
    {
        return $this->belongsTo(OrderProduct::class, 'order_product_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function rmaOrder()
    {
        return $this->belongsTo(YzcRmaOrder::class, 'rma_id');
    }
}
