<?php

namespace App\Models\Margin;

use Framework\Model\EloquentModel;

/**
 * App\Models\Margin\MarginAgreementPayRecord
 *
 * @property int $id 自增主键
 * @property int $agreement_id tb_sys_margin_agreement.id协议ID
 * @property int $customer_id customer_id
 * @property int $type 1为授信额度,3应收款,4抵押物
 * @property string $amount 协议的保证金金额
 * @property int $bill_type 类型1为支出，2为收入
 * @property int $bill_status 0,未计入账单;1已计入账
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @property string|null $operator 操作人
 * @property string|null $remark 备注
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Margin\MarginAgreementPayRecord newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Margin\MarginAgreementPayRecord newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Margin\MarginAgreementPayRecord query()
 * @mixin \Eloquent
 */
class MarginAgreementPayRecord extends EloquentModel
{
    protected $table = 'tb_sys_margin_agreement_pay_record';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'agreement_id',
        'customer_id',
        'type',
        'amount',
        'bill_type',
        'bill_status',
        'create_time',
        'update_time',
        'operator',
        'remark',
    ];
}
