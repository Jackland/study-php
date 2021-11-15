<?php

namespace App\Enums\Product;

use Framework\Enum\BaseEnum;

/**
 * oc_setting中的transaction_type
 *
 * Class ProductTransactionType
 * @package App\Enums\Product
 */
class ProductTransactionType extends BaseEnum
{
    const NORMAL = 0; // 普通
    const REBATE = 1; // 返点
    const MARGIN = 2; // 现货
    const FUTURE = 3; // 期货
    const SPOT = 4; // 议价

    //新增的 在店铺活动里面会用到下面翻译
    public static function getViewItems()
    {
        return [
            self::NORMAL => 'Normal',
            self::REBATE => 'Rebate',
            self::MARGIN => 'Margin',
            self::FUTURE => 'Futures',
            self::SPOT => 'Spot',
        ];
    }

    /**
     * 不使用定金的交易
     * @return int[]
     */
    public static function notUsedDepositTypes(): array
    {
        return [self::NORMAL, self::REBATE, self::SPOT];
    }

    /**
     * 使用定金的交易
     * @return int[]
     */
    public static function usedDepositTypes(): array
    {
        return [self::MARGIN, self::FUTURE];
    }

    //限时折扣
    public static function getMarketingTimeLimitTransactionType()
    {
        return [self::NORMAL, self::MARGIN, self::FUTURE, self::SPOT];
    }
}
