<?php

namespace App\Enums\SalesOrder;

use Framework\Enum\BaseEnum;

class HomePickPlatformType extends BaseEnum
{
    const DEFAULT  = 0;
    const AMAZON  = 1;
    const WAYFAIR  = 2;
    const WALMART  = 3;
    const OTHER_OVERSTOCK  = 4;
    const OTHER_HOME_DEPOT = 5;
    const OTHER_LOWES  = 6;
    const OTHER_OTHER = 7;
    const BUYER_PICK_UP = 8;

    public static function getALLPlatformTypeViewItems()
    {
        return [
            //self::DEFAULT=>'',
            self::AMAZON => 'Amazon',
            self::WAYFAIR =>'Wayfair',
            self::WALMART=>'Walmart',
            self::OTHER_OVERSTOCK=>'Overstock',
            self::OTHER_HOME_DEPOT=>'Home Depot',
            self::OTHER_LOWES=>"Lowe's",
            self::OTHER_OTHER=> 'Other',
            self::BUYER_PICK_UP => 'Buyer Pick-up',
        ];
    }

    public static function getOtherPlatformTypeViewItems()
    {
        return [
            self::OTHER_OVERSTOCK=>'Overstock',
            self::OTHER_HOME_DEPOT=>'Home Depot',
            self::OTHER_LOWES=>"Lowe's",
            self::OTHER_OTHER=> 'Other',
        ];
    }
}
