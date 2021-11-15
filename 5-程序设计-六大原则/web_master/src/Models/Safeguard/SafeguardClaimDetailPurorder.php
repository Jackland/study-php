<?php

namespace App\Models\Safeguard;

use Framework\Model\EloquentModel;

/**
 * App\Models\Safeguard\SafeguardClaimDetailPurorder
 *
 * @property int $id ID
 * @property int $claim_id oc_safeguard_claim.id
 * @property int $claim_detail_id oc_safeguard_claim_detail.id
 * @property int $product_id product_id
 * @property string $item_code
 * @property int $qty 采购单数量
 * @property int $order_product_id 采购订单明细id,oc_order_product.id
 * @property string $order_amount 单个产品采购价
 * @property string $add_freight 单个产品补运费金额
 * @property string $sales_platform_amount 单个产品销售平台成交金额
 * @property string $sales_platform_refund 单个产品销售平台退款金额
 * @property string $rma_refund 单个产品的RMA退款金额
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @property int $audit_id oc_safeguard_claim_audit.id
 * @property int $order_id 采购订单id,oc_order.id
 * @property float $freight_amount 补运费总金额
 * @property bool $is_deleted 是否删除[1:已删除，0:未删除]
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardClaimDetailPurorder newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardClaimDetailPurorder newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardClaimDetailPurorder query()
 * @mixin \Eloquent
 */
class SafeguardClaimDetailPurorder extends EloquentModel
{
    protected $table = 'oc_safeguard_claim_detail_purorder';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'claim_id',
        'claim_detail_id',
        'product_id',
        'item_code',
        'qty',
        'order_product_id',
        'order_amount',
        'add_freight',
        'sales_platform_amount',
        'sales_platform_refund',
        'rma_refund',
        'create_time',
        'update_time',
    ];
}
