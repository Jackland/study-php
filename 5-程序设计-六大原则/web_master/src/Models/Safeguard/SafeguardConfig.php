<?php

namespace App\Models\Safeguard;

use Framework\Model\EloquentModel;

/**
 * App\Models\Safeguard\SafeguardConfig
 *
 * @property int $id ID
 * @property int $rid 最初创建的服务配置的ID
 * @property int $config_type 保障服务类型,1:退货,2:物流
 * @property string $title 保障服务英文名称
 * @property string $title_cn 保障服务中文名称
 * @property string $service_rate 服务费率
 * @property string $coverage_rate 保额系数
 * @property int $order_product_max 订单产品数量上限
 * @property int $duration 保障期限
 * @property \Illuminate\Support\Carbon|null $action_time 保障服务执行时间
 * @property string $buyer_scope 账号作用范围，空代表全部buyer,{"type":"or","scope":{"business_type":[1,2],"account_attributes":[1,2],"account_type":[1,2]}}
 * @property int $is_timing 0:不是定时,1:是定时
 * @property int $action_type 1:生效操作,2::失效操作,
 * @property string $remark 备注
 * @property int $attach_menu_id 附件主ID
 * @property int $operator_id 操作人
 * @property int $is_executed 是否执行过,0:未执行过,1:执行过
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardConfig newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardConfig newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardConfig query()
 * @mixin \Eloquent
 */
class SafeguardConfig extends EloquentModel
{
    protected $table = 'oc_safeguard_config';

    protected $dates = [
        'action_time',
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'rid',
        'title',
        'title_cn',
        'config_type',
        'service_rate',
        'coverage_rate',
        'order_product_max',
        'duration',
        'action_time',
        'buyer_scope',
        'is_timing',
        'action_type',
        'remark',
        'attach_menu_id',
        'operator_id',
        'is_executed',
        'create_time',
        'update_time',
    ];
}
