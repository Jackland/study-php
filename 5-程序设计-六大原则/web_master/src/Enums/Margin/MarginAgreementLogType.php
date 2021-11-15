<?php

namespace App\Enums\Margin;

use Framework\Enum\BaseEnum;

/**
 * 现货协议日志类型【自定义的】
 *
 * Class MarginAgreementLogType
 * @package App\Enums\Margin
 */
class MarginAgreementLogType extends BaseEnum
{
    const BID_TO_APPLIED = 1; //bid 产生的applied数据

    const APPLIED_TO_PENDING = 5; //seller查看了此agreement, applied -> pending
    const PENDING_TO_REJECT = 10; //seller查看了此agreement并且拒绝了, applied -> pending
    const PENDING_TO_APPROVED = 15; //seller查看了此agreement并且同意了, applied -> approved

    const APPLIED_TO_FAILED = 20;//seller从未打开过此协议，且超时未处理
    const PENDING_TO_FAILED = 25; //seller查看了此agreement，且超时未处理
    const ADVANCED_PRODUCT_PAY_FAILED = 30; //buyer未支付定金，且超时未处理

    const QUICK_VIEW_TO_APPROVED = 35; // quick view直接自动同意
    const APPLIED_TO_CANCELED = 40; //buyer取消agreement,  applied ->canceld
    const APPROVED_TO_TO_BE_PAID = 45; //头款商品购买成功

    const TO_BE_PAID_TO_COMPLETED = 50; //协议完成

    const BUYER_FAILED = 55;

    const COMPLETED_TO_TO_BE_PAID = 60; //尾款RMA时候，status会被设置成6

}
