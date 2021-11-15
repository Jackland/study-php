<?php

namespace App\Models\Margin;

use Framework\Model\EloquentModel;

/**
 * App\Models\Margin\MarginAgreementLog
 *
 * @property int $id 自增主键
 * @property int $agreement_id 协议ID
 * @property int $customer_id customer_id
 * @property int $type
 * @property string $content 日志内容
 * @property string $operator 操作人
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Margin\MarginAgreementLog newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Margin\MarginAgreementLog newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Margin\MarginAgreementLog query()
 * @mixin \Eloquent
 */
class MarginAgreementLog extends EloquentModel
{
    protected $table = 'tb_sys_margin_agreement_log';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'agreement_id',
        'customer_id',
        'type',
        'content',
        'operator',
        'create_time',
        'update_time',
    ];
}
