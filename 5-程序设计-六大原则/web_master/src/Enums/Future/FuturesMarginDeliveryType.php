<?php

namespace App\Enums\Future;

use Framework\Enum\BaseEnum;

/**
 * oc_futures_margin_delivery表delivery type字段
 * buyer的交割方式
 *
 * Class FuturesMarginDeliveryType
 * @package App\Enums\Future
 */
class FuturesMarginDeliveryType extends BaseEnum
{
    const FINAL_PAYMENT = 1;//支付期货协议尾款交割
    const TO_MARGIN = 2;//转现货保证金进行交割
    const TO_MARGIN_FINAL_PAYMENT = 3;//转现货保证金和支付尾款混合交割模式

    /**
     * 获取非转现货保证金交割的其他模式，包含混合模式
     *
     * @return int[]
     */
    public static function getNotToMargin()
    {
        return [static::FINAL_PAYMENT, static::TO_MARGIN_FINAL_PAYMENT];
    }
}
