<?php

namespace App\Models\Message;

use Framework\Model\EloquentModel;

/**
 * App\Models\Message\MessageLimit
 *
 * @property int $id
 * @property int $seller_id Seller ID
 * @property string|null $buyer_ids Buyer IDs: xx,xx,xx
 * @property \Illuminate\Support\Carbon $create_time
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\MessageLimit newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\MessageLimit newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\MessageLimit query()
 * @mixin \Eloquent
 */
class MessageLimit extends EloquentModel
{
    protected $table = 'oc_message_limit';

    protected $dates = [
        'create_time',
    ];

    protected $fillable = [
        'seller_id',
        'buyer_ids',
        'create_time',
    ];
}
