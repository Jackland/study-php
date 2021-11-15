<?php

namespace App\Models\Product\ProductDTO\ProductAudit;

use App\Models\Product\ProductDTO\CustomFieldDTO;
use App\Models\Product\ProductDTO\ProductAudit\Information\ProductTypeDTO;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;

/**
 * oc_product_audit中的information字段
 * @property-read int $color_option_id 颜色属性id
 * @property-read int $material_option_id 材质属性id
 * @property-read string $product_size 尺寸             33309已弃用
 * @property-read int $sold_separately 是否独立售卖
 * @property-read string $title 产品标题
 * @property-read int|string $need_install 是否需要安装  33309已弃用
 * @property-read float|string $current_price 当前价格
 * @property-read bool|string $display_price 价格是否可见
 * @property-read int[]|string[] $group_id 产品分组
 * @property-read string $image 主图
 * @property-read ProductTypeDTO $product_type 产品类型
 * @property-read int[]|string[] $associated_product_ids 关联产品 productIds
 * @property-read string $non_sellable_on 不可售卖平台，多个逗号分隔
 * @property-read string $upc
 * @property-read bool|string $is_customize 是否可定制
 * @property-read string $origin_place_code 原产地code
 * @property-read int|string $filler 填充物，材质option对应的id
 * @property-read Collection|CustomFieldDTO[] $custom_field 自定义字段
 */
class InformationDTO extends Fluent
{

}
