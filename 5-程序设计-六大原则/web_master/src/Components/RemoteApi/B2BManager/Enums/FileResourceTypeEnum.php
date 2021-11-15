<?php

namespace App\Components\RemoteApi\B2BManager\Enums;

use Framework\Enum\BaseEnum;

class FileResourceTypeEnum extends BaseEnum
{
    // 根据实际业务增加相关类型，增加时需要通知 JAVA 负责人增加相同类型
    // 不要使用 BUYER_FILE 和 SELLER_FILE ！！
    const BUYER_FILE = 'BUYER_FILE';
    const SELLER_FILE = 'SELLER_FILE';
    const SELLER_INVENTORY_ADJUSTMENT_FILE_CONFIRM = 'SELLER_INVENTORY_ADJUSTMENT_FILE_CONFIRM'; // seller库存调整申报确认文件
    const SAFEGUARD = 'SAFEGUARD'; // 保险业务附件
    const MESSAGE_SELLER = 'MESSAGE_SELLER'; // seller发送消息附件
    const MESSAGE_BUYER = 'MESSAGE_BUYER'; // buyer发送消息附件
    const SALES_ORDER_PICK_UP_BOL = 'SALES_ORDER_PICK_UP_BOL'; // 销售订单自提货业务的 BOL 文件
}
