<?php

namespace App\Models\FeeOrder;

use App\Models\Customer\Customer;
use App\Models\Rma\YzcRmaOrder;
use Framework\Model\EloquentModel;

/**
 * \App\Models\FeeOrder\FeeOrderRmaBalance
 *
 * @property int $id 主键id
 * @property int $rma_id 关联的rmaid
 * @property int $fee_order_id 关联的仓租id
 * @property float $balance 已经用信用额度支付的金额
 * @property float $need_pay 实际需要支付的金额
 * @property int $buyer_id buyer id
 * @property int $seller_id seller_id
 * @property string|null $created_at 创建时间
 * @property string|null $updated_at 更新时间
 * @property-read \App\Models\Customer\Customer $buyer
 * @property-read \App\Models\FeeOrder\FeeOrder $feeOrder
 * @property-read \App\Models\Customer\Customer $seller
 * @property-read \App\Models\Rma\YzcRmaOrder $yzcRmaOrder
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\FeeOrder\FeeOrderRmaBalance newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\FeeOrder\FeeOrderRmaBalance newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\FeeOrder\FeeOrderRmaBalance query()
 * @mixin \Eloquent
 */
class FeeOrderRmaBalance extends EloquentModel
{
    public $timestamps = true;

    protected $table = 'oc_fee_order_rma_balance';

    public function feeOrder()
    {
        return $this->belongsTo(FeeOrder::class, 'fee_order_id');
    }

    public function yzcRmaOrder()
    {
        return $this->belongsTo(YzcRmaOrder::class, 'rma_id');
    }

    public function buyer()
    {
        return $this->belongsTo(Customer::class, 'buyer_id');
    }

    public function seller()
    {
        return $this->belongsTo(Customer::class, 'seller_id');
    }
}
