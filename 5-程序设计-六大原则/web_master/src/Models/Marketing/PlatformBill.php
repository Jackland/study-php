<?php

namespace App\Models\Marketing;

use Framework\Model\EloquentModel;

/**
 * App\Models\Marketing\PlatformBill
 *
 * @property int $id 自增主键
 * @property int $type 类型1为支出，2为收入
 * @property int $order_id 绑定的订单号
 * @property int $rma_id
 * @property int $product_id
 * @property string|null $amount 金额
 * @property string $remark 备注
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\PlatformBill newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\PlatformBill newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\PlatformBill query()
 * @mixin \Eloquent
 */
class PlatformBill extends EloquentModel
{
    protected $table = 'oc_marketing_platform_bill';

    protected $fillable = [
        'type',
        'order_id',
        'product_id',
        'rma_id',
        'amount',
        'remark',
        'create_time',
        'update_time',
    ];
}
