<?php

namespace App\Models\Product\ProductDTO\ProductAudit;

use App\Models\Product\ProductDTO\CustomFieldDTO;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;

/**
 * oc_product_audit中的assemble_info字段
 * @property-read float|string $assemble_length
 * @property-read float|string $assemble_width
 * @property-read float|string $assemble_height
 * @property-read float|string $assemble_weight
 * @property-read Collection|CustomFieldDTO[] $custom_field 自定义字段
 */
class AssembleInfoDTO extends Fluent
{

}
