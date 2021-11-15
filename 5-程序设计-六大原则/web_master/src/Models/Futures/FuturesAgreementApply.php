<?php

namespace App\Models\Futures;

use Framework\Model\EloquentModel;

/**
 * App\Models\FuturesAgreementApply
 *
 * @property int $id 自增主键
 * @property int $agreement_id oc_futures_contract.id合约ID
 * @property int $customer_id 发送消息人的ID
 * @property int $apply_type １提前交付，２取消交付，３协议终止,4申诉,5正常交付
 * @property int $status 0待审批，１审批通过，２审批拒绝,3超时
 * @property int $is_read 0为未读,1已读
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @property string $operator 操作人
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Futures\FuturesAgreementApply newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Futures\FuturesAgreementApply newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Futures\FuturesAgreementApply query()
 * @mixin \Eloquent
 * @property string $remark 备注内容
 */
class FuturesAgreementApply extends EloquentModel
{
    protected $table = 'oc_futures_agreement_apply';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'agreement_id',
        'customer_id',
        'apply_type',
        'status',
        'is_read',
        'create_time',
        'update_time',
        'operator',
    ];
}
