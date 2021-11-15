<?php

namespace App\Models\Futures;

use Framework\Model\EloquentModel;

/**
 * App\Models\Futures\FuturesAgreementMarginPayRecord
 *
 * @property int $id 自增主键
 * @property int $agreement_id oc_futures_margin_agreement.id协议ID
 * @property int $customer_id customer_id
 * @property int $type 1为授信额度,3应收款,4抵押物
 * @property string $amount 协议的保证金金额
 * @property int $flow_type 流水类型:1为seller保证金支出，2为seller保证金返还,3为seller违约金支出,4为seller支付平台费,5为buyer违约金返还,6为返还buyer保证金的支出
 * @property int $bill_type 类型1为支出，2为收入
 * @property int $bill_status 0,未计入账单;1,已计入账;
 * @property string $operator 操作人
 * @property string $remark 备注
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Futures\FuturesAgreementMarginPayRecord newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Futures\FuturesAgreementMarginPayRecord newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Futures\FuturesAgreementMarginPayRecord query()
 * @mixin \Eloquent
 */
class FuturesAgreementMarginPayRecord extends EloquentModel
{
    protected $table = 'oc_futures_agreement_margin_pay_record';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'agreement_id',
        'customer_id',
        'type',
        'amount',
        'flow_type',
        'bill_type',
        'bill_status',
        'operator',
        'remark',
        'create_time',
        'update_time',
    ];
}
