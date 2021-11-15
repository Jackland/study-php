<?php

namespace App\Enums\Product;

class ProductFeeType
{
    const PACKAGE_FEE_DROP_SHIP = 1; // 一件代发打包费
    const PACKAGE_FEE_WILL_CALL = 2; // 上门取货打包费
    const BASIC_FREIGHT = 3; // 基础运费
    const SURCHARGE = 4; // 附加费
}
