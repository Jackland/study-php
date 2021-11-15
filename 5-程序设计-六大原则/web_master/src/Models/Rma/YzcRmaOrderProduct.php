<?php

namespace App\Models\Rma;

use App\Models\Order\OrderProduct;
use App\Models\Product\Product;
use Framework\Model\EloquentModel;

/**
 * App\Models\Rma\YzcRmaOrderProduct
 *
 * @property int $id rma_order_product_id 自增主键
 * @property int $rma_id rma_id
 * @property int $product_id product_id
 * @property string|null $item_code item_code
 * @property string|null $asin ASIN 亚马逊平台销售渠道必填
 * @property int $quantity 退换货数量
 * @property int|null $reason_id 退货理由ID
 * @property int $order_product_id oc_order_product.id
 * @property string|null $comments 备注信息
 * @property string|null $seller_reshipment_comments 卖家重发留言
 * @property string|null $seller_refund_comments 卖家返金留言
 * @property int $rma_type RMA类型 1.仅重发;2.仅退款;3.即退款又重发
 * @property string|null $apply_refund_amount 申请退款金额
 * @property string|null $actual_refund_amount 实际退款金额
 * @property float $coupon_amount  优惠券折扣
 * @property float $campaign_amount 活动满减金额
 * @property int|null $sales_order_id tb_sales_customer_order.id
 * @property string|null $memo 备注
 * @property string|null $create_user_name 创建者
 * @property string|null $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property string|null $update_time 修改时间
 * @property string|null $program_code 程序号
 * @property int|null $status_refund 返金状态 0:初始状态1:同意2:拒绝
 * @property int|null $status_reshipment 重发状态0：初始1同意2拒绝
 * @property int|null $reshipment_type 1:发新品2：发配件3：拆新品发配件
 * @property int|null $refund_type 1:返信用额度,2:返优惠券,4:退到虚拟账户,5:余额和虚拟账户
 * @property string|null $osj_sync_flag 已完成RMA记录同步在库系统标志：1-成功;0-失败
 * @property-read \App\Models\Order\OrderProduct $orderProduct
 * @property-read \App\Models\Product\Product $product
 * @property-read \App\Models\Rma\YzcRmaOrder $yzcRmaOrder
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Rma\YzcRmaOrderProduct newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Rma\YzcRmaOrderProduct newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Rma\YzcRmaOrderProduct query()
 * @mixin \Eloquent
 */
class YzcRmaOrderProduct extends EloquentModel
{
    public const CREATED_AT = 'create_time';
    public const UPDATED_AT = 'update_time';

    protected $table = 'oc_yzc_rma_order_product';
    public $timestamps = true;

    protected $fillable = [
        'rma_id',
        'product_id',
        'item_code',
        'asin',
        'quantity',
        'reason_id',
        'order_product_id',
        'comments',
        'seller_reshipment_comments',
        'seller_refund_comments',
        'rma_type',
        'apply_refund_amount',
        'actual_refund_amount',
        'coupon_amount',
        'campaign_amount',
        'sales_order_id',
        'memo',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
        'status_refund',
        'status_reshipment',
        'reshipment_type',
        'refund_type',
        'osj_sync_flag',
    ];

    public function yzcRmaOrder()
    {
        return $this->belongsTo(YzcRmaOrder::class, 'rma_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function orderProduct()
    {
        return $this->belongsTo(OrderProduct::class, 'order_product_id');
    }
}
