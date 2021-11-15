<?php

namespace App\Models\Order;

use Framework\Model\EloquentModel;

/**
 * App\Models\Order\OrderHistory
 *
 * @property int $order_history_id
 * @property int $order_id
 * @property int $order_status_id
 * @property int $notify
 * @property string $comment
 * @property \Illuminate\Support\Carbon $date_added
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Order\OrderHistory newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Order\OrderHistory newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Order\OrderHistory query()
 * @mixin \Eloquent
 */
class OrderHistory extends EloquentModel
{
    protected $table = 'oc_order_history';
    protected $primaryKey = 'order_history_id';

    protected $dates = [
    ];

    protected $fillable = [
        'order_id',
        'order_status_id',
        'notify',
        'comment',
        'date_added',
    ];
}
