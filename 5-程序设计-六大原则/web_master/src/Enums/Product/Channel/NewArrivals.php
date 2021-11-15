<?php

namespace App\Enums\Product\Channel;

use Framework\Enum\BaseEnum;

class NewArrivals extends BaseEnum
{
    const NAME = 'New Arrivals';
    const PARAM_SEARCH = '搜索排序值';
    const PARAM_IN_STOCK = '入库天数参数';
    const PARAM_VIEW_14 = '近14天下载量';
}
