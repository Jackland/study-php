<?php

namespace App\Enums\CreditLine;

use Framework\Enum\BaseEnum;

class SysCreditLintPlatformType extends BaseEnum
{
    const ACCOUNT_TYPE_NON_CAPITAL = 1;
    const ACCOUNT_TYPE_PRE_CHARGE = 2;

    public static function getAccountTypeViewItems()
    {
        return [
            self::ACCOUNT_TYPE_NON_CAPITAL => '非资金类',
            self::ACCOUNT_TYPE_PRE_CHARGE => '预充值类',
        ];
    }

    const STATUS_ENABLE = 0;
    const STATUS_DISABLE = 1;

    public static function getStatusViewItems()
    {
        return [
            self::STATUS_ENABLE => '启用',
            self::STATUS_DISABLE => '禁用',
        ];
    }

    const COLLECTION_TYPE = 0;
    const PAYMENT_TYPE = 1;
    const COLLECTION_PAYMENT_TYPE = 2;

    public static function getTypeViewItems()
    {
        return [
            self::COLLECTION_TYPE => '收款',
            self::PAYMENT_TYPE => '付款',
            self::COLLECTION_PAYMENT_TYPE => '收款/付款',
        ];
    }
}
