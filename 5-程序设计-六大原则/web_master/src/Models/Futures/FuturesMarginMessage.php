<?php

namespace App\Models\Futures;

use Framework\Model\EloquentModel;

/**
 * App\Models\FuturesMarginMessage
 *
 * @property int $id 自增主键
 * @property int $agreement_id 期货协议主键ID
 * @property int $customer_id 消息发送人customer_id
 * @property int $apply_id oc_futures_agreement_apply.id,期货协议的协商消息
 * @property string|null $message 消息体
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property string|null $create_user_name
 * @property int $receive_customer_id 消息接收者customer_id
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Futures\FuturesMarginMessage newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Futures\FuturesMarginMessage newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Futures\FuturesMarginMessage query()
 * @mixin \Eloquent
 */
class FuturesMarginMessage extends EloquentModel
{
    protected $table = 'oc_futures_margin_message';

    protected $dates = [
        'create_time',
    ];

    protected $fillable = [
        'agreement_id',
        'customer_id',
        'apply_id',
        'message',
        'create_time',
        'create_user_name',
        'receive_customer_id',
    ];
}
