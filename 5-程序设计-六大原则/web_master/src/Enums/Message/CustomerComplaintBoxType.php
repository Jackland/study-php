<?php

namespace App\Enums\Message;

use Framework\Enum\BaseEnum;


/**
 * oc_customer_complaint_box -> type
 * 投诉针对对象
 *
 * Class CustomerComplaintBoxType
 * @package App\Enums\Message
 */
class CustomerComplaintBoxType extends BaseEnum
{
    const MESSAGE = 1; // 针对站内信
    const SELLER = 2; // 针对用户

    public static function getViewItems()
    {
        return [
            self::MESSAGE => 'Complaint Against Message',
            self::SELLER => 'Complaint Against Seller'
        ];
    }
}