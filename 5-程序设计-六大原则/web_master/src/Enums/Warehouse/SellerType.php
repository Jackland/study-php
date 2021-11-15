<?php

namespace App\Enums\Warehouse;

use Framework\Enum\BaseEnum;

class SellerType extends BaseEnum
{
     const ALL = 'all'; //待审核
     const NORMAL = 'normal'; //已审核
     const US_NATIVE = 'usNative'; //已拒绝
     const GIGA_ON_SITE = 'GIGA Onsite'; //已购买
     const VIRTUAL_WAREHOUSE = '区域仓库'; //已购买
}
