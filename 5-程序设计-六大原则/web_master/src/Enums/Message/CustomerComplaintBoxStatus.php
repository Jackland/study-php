<?php

namespace App\Enums\Message;

use Framework\Enum\BaseEnum;


/**
 * oc_customer_complaint_box -> status
 * 处理状态
 *
 * Class CustomerComplaintBoxStatus
 * @package App\Enums\Message
 */
class CustomerComplaintBoxStatus extends BaseEnum
{
    const UNPROCESSED = 1; // 已处理
    const PROCESSED = 2; // 未处理

    public static function getViewItems()
    {
        return [
            self::PROCESSED => 'Yes',
            self::UNPROCESSED => 'No'
        ];
    }
}