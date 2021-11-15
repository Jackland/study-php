<?php

namespace App\Enums\SalesOrder;

use Framework\Enum\BaseEnum;

class HomePickImportMode extends BaseEnum
{
    // import mode
    const IMPORT_MODE_NORMAL = 0;
    const IMPORT_MODE_AMAZON = 4;
    const IMPORT_MODE_WAYFAIR = 5;
    //美国上门取货other导单 import_mode
    const US_OTHER  = 6;
    const IMPORT_MODE_WALMART = 7;
    const IMPORT_MODE_BUYER_PICK_UP = 8; //自提货
    const IMPORT_MODE_EBAY = 9;//eBay

    public static function getViewItems()
    {
        return [
            self::IMPORT_MODE_NORMAL => 'Other External Platform',
            self::IMPORT_MODE_AMAZON => 'Amazon',
            self::IMPORT_MODE_WAYFAIR => 'Wayfair',
            self::US_OTHER => 'Other External Platform',
            self::IMPORT_MODE_WALMART => 'Walmart',
            self::IMPORT_MODE_BUYER_PICK_UP => 'Buyer Pick-up',
            self::IMPORT_MODE_EBAY => 'eBay',
        ];
    }
}
