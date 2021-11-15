<?php

namespace App\Enums\Marketing;

use Framework\Enum\BaseEnum;

class CouponTemplateBuyerScope extends BaseEnum
{
    const ONLY_NEW_BUYER = 1; // 仅新Buyer才可以 领取/发放
    const ONLY_OLD_BUYER = 2; // 仅老Buyer（有过成交记录的）才可以 领取/发放
    const ALL_BUYER = 3; // 新老用户均可 领取/发放

    public static function getViewItems()
    {
        return [
            self::ONLY_NEW_BUYER => 'New Buyer',
            self::ONLY_OLD_BUYER => 'Buyer with Orders',
            self::ALL_BUYER => '',
        ];
    }

    /**
     * 通过对应的状态值获取对应文案
     *
     * @param int $buyerScope
     * @return string
     */
    public static function getViewItemByBuyerScope(int $buyerScope)
    {
        switch ($buyerScope) {
            case self::ONLY_NEW_BUYER:
                return self::getViewItems()[self::ONLY_NEW_BUYER];
            case self::ONLY_OLD_BUYER:
                return self::getViewItems()[self::ONLY_OLD_BUYER];
            default:
                return self::getViewItems()[self::ALL_BUYER];
        }
    }
}
