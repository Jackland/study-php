<?php

namespace App\Models\Message;

use Framework\Model\EloquentModel;

/**
 * App\Models\Message\MsgCommonWordsSuggest
 * 
 * @property int $id ID
 * @property int $customer_id 客户ID
 * @property string $type_ids 类型ids, 多个逗号分隔
 * @property string $content 内容
 * @property int $status 1未处理 2采用 3不采用
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 */
class MsgCommonWordsSuggest extends EloquentModel
{
    protected $table = 'oc_msg_common_words_suggest';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'customer_id',
        'type_ids',
        'content',
        'status',
        'create_time',
        'update_time',
    ];
}
