<?php

namespace App\Models\FeeOrder;

use Framework\Model\EloquentModel;

/**
 * App\Models\FeeOrder\FeeOrderAgreementBalance
 *
 * @property int $id
 * @property int $agreement_id 现货协议id，如果增加类其他复杂交易，建议增加一个type，目前只有现货
 * @property int $fee_order_id 费用单id
 * @property string $balance 已经用信用额度支付的金额
 * @property string $need_pay 实际需要支付的金额
 * @property int $buyer_id
 * @property int $seller_id
 * @property \Illuminate\Support\Carbon $create_time
 * @property \Illuminate\Support\Carbon $update_time
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\FeeOrder\FeeOrderAgreementBalance newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\FeeOrder\FeeOrderAgreementBalance newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\FeeOrder\FeeOrderAgreementBalance query()
 * @mixin \Eloquent
 */
class FeeOrderAgreementBalance extends EloquentModel
{
    protected $table = 'oc_fee_order_agreement_balance';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'agreement_id',
        'fee_order_id',
        'balance',
        'need_pay',
        'buyer_id',
        'seller_id',
        'create_time',
        'update_time',
    ];
}
