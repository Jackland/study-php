<?php

namespace App\Models\Pay;

use Framework\Model\EloquentModel;

/**
 * App\Models\Pay\RechargeApply
 *
 * @property int $id 自增主键
 * @property string $serial_number 交易申请ID(Serial Number)
 * @property string $recharge_method 交易充值方式字典表RECHARGE_METHODS
 * @property string $amount 金额
 * @property string $currency 充值币种
 * @property int $buyer_id BuyerId
 * @property int $apply_status 申请状态字典表RECHARGE_APPLY_STATUS
 * @property int $apply_order_id 申请充值交易订单表ID
 * @property \Illuminate\Support\Carbon|null $apply_date 申请时间
 * @property int $recharge_order_header_id 充值交易订单头表ID
 * @property string|null $memo 备注
 * @property string|null $create_user_name 创建者
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property \Illuminate\Support\Carbon|null $update_time 修改时间
 * @property string|null $program_code 程序号
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Pay\RechargeApply newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Pay\RechargeApply newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Pay\RechargeApply query()
 * @mixin \Eloquent
 */
class RechargeApply extends EloquentModel
{
    protected $table = 'oc_recharge_apply';

    protected $dates = [
        'apply_date',
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'serial_number',
        'recharge_method',
        'amount',
        'currency',
        'buyer_id',
        'apply_status',
        'apply_order_id',
        'apply_date',
        'recharge_order_header_id',
        'memo',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
    ];
}
