<?php

namespace App\Models\Message;

use Framework\Model\EloquentModel;

/**
 * App\Models\Message\MsgCustomerExt
 *
 * @property int $id ID
 * @property int $customer_id 客户ID
 * @property int $language_type 语言类型 0不限制 1中文 2英文
 * @property int $common_words_description 是否出现过免责说明 0没出现 1出现
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\MsgCustomerExt newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\MsgCustomerExt newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\MsgCustomerExt query()
 * @mixin \Eloquent
 */
class MsgCustomerExt extends EloquentModel
{
    protected $table = 'oc_msg_customer_ext';

    protected $dates = [
        
    ];

    protected $fillable = [
        'customer_id',
        'language_type',
        'common_words_description',
    ];
}
