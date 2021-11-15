<?php

namespace App\Enums\Product\Channel;

use Framework\Enum\BaseEnum;

class DropPrice extends BaseEnum
{
    const NAME = 'Price Drop';
    const PARAM_SEARCH = '搜索排序值';
    const PARAM_DROP_PRICE_DAYS = '降价天数';
    const PARAM_DROP_PRICE_PRICE = '降价幅度';

}
