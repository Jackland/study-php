<?php

namespace App\Enums\Product;

use Framework\Enum\BaseEnum;

class ProductImportType extends BaseEnum
{
    const BATCH_INSERT = 1; // 批量导入
    const BATCH_UPDATE = 2; // 批量修改

}
