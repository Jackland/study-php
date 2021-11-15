<?php

namespace App\Enums\Buyer;

use Framework\Enum\BaseEnum;

class BuyerType extends BaseEnum
{
    const PICK_UP = 1; // 上门取货
    const DROP_SHIP = 2; // 一件代发

    const CONTACTS_OPEN_STATUS = 1; //开启联系人推送给seller

    public static function getViewItems()
    {
        return [
            static::PICK_UP => __('上门取货', [], 'enums/buyer'),
            static::DROP_SHIP => __('一件代发', [], 'enums/buyer'),
        ];
    }

    //现货四期 引入Will Call
    public static function getViewItemsNew()
    {
        return [
            static::PICK_UP => 'Will Call',
            static::DROP_SHIP => 'Drop Shipping',
        ];
    }

    public static function getDescriptionNew($value, $unKnown = 'Unknown')
    {
        $array = static::getViewItemsNew();
        return isset($array[$value]) ? $array[$value] : $unKnown;
    }
}
