<?php

namespace App\Models\Pay;

use Framework\Model\EloquentModel;

/**
 * App\Models\Pay\CreditLinePlatformType
 *
 * @property int $id 主键ID
 * @property int $parent_id 父级ID（主键非Type）
 * @property bool $type 类型
 * @property bool $type_level 类型级别
 * @property string $name 类型名称
 * @property bool $collection_payment_type 收付款类型 0:收款 1:付款 2:收款/付款
 * @property bool $account_type 账户分类 1:非资金类 2预充值类
 * @property bool|null $status 状态  0:启用 1:禁用
 * @property string|null $memo 备注
 * @property string|null $create_user_name 创建者
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property \Illuminate\Support\Carbon|null $update_time 修改时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Pay\CreditLinePlatformType newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Pay\CreditLinePlatformType newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Pay\CreditLinePlatformType query()
 * @mixin \Eloquent
 */
class CreditLinePlatformType extends EloquentModel
{
    protected $table = 'tb_sys_credit_line_platform_type';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'parent_id',
        'type',
        'type_level',
        'name',
        'collection_payment_type',
        'account_type',
        'status',
        'memo',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
    ];
}
