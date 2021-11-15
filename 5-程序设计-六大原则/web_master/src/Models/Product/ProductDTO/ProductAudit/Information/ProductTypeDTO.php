<?php

namespace App\Models\Product\ProductDTO\ProductAudit\Information;

use Illuminate\Support\Fluent;

/**
 * oc_product_audit中的information字段中的product_type
 * @property-read int $type_id 1普通,2combo,3配件
 * @property-read ProductTypeComboDTO[] $combo 1普通,2combo,3配件
 * @property-read ProductTypeNoComboDTO $no_combo 1普通,2combo,3配件
 */
class ProductTypeDTO extends Fluent
{

}
