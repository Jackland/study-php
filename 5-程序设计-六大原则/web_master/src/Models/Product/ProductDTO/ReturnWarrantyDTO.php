<?php

namespace App\Models\Product\ProductDTO;

use App\Models\Product\ProductDTO\ReturnWarranty\DeliveredDTO;
use App\Models\Product\ProductDTO\ReturnWarranty\ReturnDTO;
use App\Models\Product\ProductDTO\ReturnWarranty\WarrantyDTO;
use Illuminate\Support\Fluent;

/**
 * 退返品政策定义
 * @property-read ReturnDTO $return
 * @property-read DeliveredDTO $delivered
 * @property-read WarrantyDTO $warranty
 */
class ReturnWarrantyDTO extends Fluent
{

}
