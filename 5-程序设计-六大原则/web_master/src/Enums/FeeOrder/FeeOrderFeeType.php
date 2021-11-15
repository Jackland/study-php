<?php

namespace App\Enums\FeeOrder;

use Framework\Enum\BaseEnum;

class FeeOrderFeeType extends BaseEnum
{
    const STORAGE = 1;
    const SAFEGUARD = 2;

    public static function getViewItems()
    {
        return [
            self::STORAGE => 'Storage Fee',
            self::SAFEGUARD => 'Protection Service Fee',
        ];
    }

    public static function getOrderNoPrefix($feeType)
    {
        $map = [
            self::STORAGE => FeeOrderNumPrefix::PREFIX_STORAGE,
            self::SAFEGUARD => FeeOrderNumPrefix::PREFIX_SAFEGUARD,
        ];
        return $map[$feeType] ?? '';
    }
}
