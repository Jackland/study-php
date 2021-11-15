<?php

namespace App\Enums\Message;

use Framework\Enum\BaseEnum;

/**
 * Message - user_type
 *
 * Class MessageUserType
 * @package App\Enums\Message
 */
class MessageTaskMsgType extends BaseEnum
{
    const SELLER_TO_BUYER = 0;


    //oc_message_setting
    const IS_IN_SELLER_RECOMMEND = 1;//是否参与系统推荐
}
