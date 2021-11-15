<?php

namespace App\Enums\Message;

use Framework\Enum\BaseEnum;

class MsgCommonWordsTypeCustomerType extends BaseEnum
{
    const ALL = 1;
    const BUYER = 2;
    const SELLER = 3;

    /**
     * seller的
     * @return int[]
     */
    public static function sellerTypes(): array
    {
        return [static::ALL, static::SELLER];
    }

    /**
     * buyer的
     * @return int[]
     */
    public static function buyerTypes(): array
    {
        return [static::ALL, static::BUYER];
    }

    /**
     * 根据用户获取类型
     * @return int[]
     */
    public static function getTypesByCustomer(): array
    {
        if (customer()->isPartner()) {
            return static::sellerTypes();
        }

        return static::buyerTypes();
    }
}
