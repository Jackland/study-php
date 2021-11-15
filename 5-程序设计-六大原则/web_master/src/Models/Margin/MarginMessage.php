<?php

namespace App\Models\Margin;

use Framework\Model\EloquentModel;

/**
 * App\Models\Margin\MarginMessage
 *
 * @property int $id 自增主键
 * @property int $margin_agreement_id tb_sys_margin_agreement.id
 * @property int $customer_id 消息发送人customer_id
 * @property string $message 消息内容
 * @property \Illuminate\Support\Carbon $create_time 消息创建时间
 * @property string|null $memo 系统备注
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Margin\MarginMessage newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Margin\MarginMessage newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Margin\MarginMessage query()
 * @mixin \Eloquent
 * @property-read \App\Models\Margin\MarginAgreement $agreement
 */
class MarginMessage extends EloquentModel
{
    protected $table = 'tb_sys_margin_message';

    protected $dates = [
        'create_time',
    ];

    protected $fillable = [
        'margin_agreement_id',
        'customer_id',
        'message',
        'create_time',
        'memo',
    ];

    public function agreement()
    {
        return $this->belongsTo(MarginAgreement::class, 'margin_agreement_id');
    }
}
