<?php

namespace App\Enums\Pay;

use Framework\Enum\BaseEnum;

/**
 * @deprecated 使用 PayCode
 */
class PayMethodType extends BaseEnum
{
    const PAY_WECHAT = 1; // 微信支付
    const PAY_UMF = 2; // 联动支付
    const PAY_LINE_OF_CREDIT = 3;  // 信用额度支付
    const PAY_VIRTUAL = 4; // 虚拟支付
    // 信用卡支付 暂时无法确定是哪类信用卡
    // visa 、美国运通 、jcb 、master card或者其他
    const PAY_CREDIT_CARD = 5; // 信用卡支付

    public static function getViewItems()
    {
        return [
            static::PAY_WECHAT => 'WeChat Pay (+1.81%)',
            static::PAY_UMF => 'UnionPay (+1.51%)',
            static::PAY_LINE_OF_CREDIT => 'Line Of Credit',
            static::PAY_VIRTUAL => 'Virtual Pay',
            static::PAY_CREDIT_CARD => 'Credit Card (+2.8%)',
        ];
    }
}
