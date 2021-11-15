<?php

namespace App\Models\Product\ProductDTO\ProductAudit;

use App\Models\Product\ProductDTO\ReturnWarrantyDTO;
use Illuminate\Support\Fluent;

/**
 * oc_product_audit中的description字段
 * @property-read ReturnWarrantyDTO $return_warranty
 * @property-read string $description
 * @property-read string $return_warranty_text
 */
class DescriptionDTO extends Fluent
{

}
