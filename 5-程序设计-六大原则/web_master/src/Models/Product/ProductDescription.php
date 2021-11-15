<?php

namespace App\Models\Product;

use App\Models\Product\ProductDTO\ReturnWarrantyDTO;
use Framework\Model\EloquentModel;

/**
 * App\Models\Product\ProductDescription
 *
 * @property int $product_id
 * @property int $language_id
 * @property string|null $name
 * @property string $description
 * @property string|null $summary_description 五点描述
 * @property string $tag
 * @property string|null $meta_title
 * @property string $meta_description
 * @property string $meta_keyword
 * @property string|null $arm_height
 * @property string|null $assembly_url
 * @property string|null $catalog_intro
 * @property string|null $collection_name
 * @property string|null $country_of_origin
 * @property string|null $depth
 * @property string|null $depth_open
 * @property string|null $desk_clearance
 * @property string|null $diameter
 * @property string|null $drop_ship
 * @property string|null $color
 * @property string|null $upholstery_color
 * @property string|null $fabric_color
 * @property string|null $finish_color
 * @property string|null $group_name
 * @property string|null $group_number
 * @property string|null $height_open
 * @property string|null $item
 * @property string|null $item_cubes
 * @property string|null $kit_type
 * @property string|null $lookup_name
 * @property string|null $next_ship_ment
 * @property string|null $next_shipment_qty
 * @property string|null $pack
 * @property string|null $piece_name
 * @property string|null $room_name
 * @property string|null $seat_depth
 * @property string|null $seat_height
 * @property string|null $seat_width
 * @property string|null $shelf_distance
 * @property string|null $style_name
 * @property string|null $sub_category
 * @property string|null $type_of_packaging
 * @property string|null $unit_stock
 * @property string|null $wood_finish
 * @property string|null $material
 * @property string|null $warehouse
 * @property string|null $frame_finish
 * @property string|null $name_ori 原始产品名
 * @property string|null $returns_and_notice
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductDescription newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductDescription newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductDescription query()
 * @mixin \Eloquent
 * @property string|null $return_warranty {"return":{"undelivered":{"days":7,"rate":25,"allow_return":1},"delivered":{"before_days":7,"after_days":0,"delivered_checked":0}},"warranty":{"month":3,"conditions":[]}}
 * @see ReturnWarrantyDTO
 * @property string|null $return_warranty_text 退返品政策文本
 * @property string $packed_zip_path 打包资源文件的路径
 * @property string|null $packed_time 打包资源文件时间
 */
class ProductDescription extends EloquentModel
{
    protected $table = 'oc_product_description';
    protected $primaryKey = '';

    protected $fillable = [
        'name',
        'description',
        'summary_description',
        'tag',
        'meta_title',
        'meta_description',
        'meta_keyword',
        'arm_height',
        'assembly_url',
        'catalog_intro',
        'collection_name',
        'country_of_origin',
        'depth',
        'depth_open',
        'desk_clearance',
        'diameter',
        'drop_ship',
        'color',
        'upholstery_color',
        'fabric_color',
        'finish_color',
        'group_name',
        'group_number',
        'height_open',
        'item',
        'item_cubes',
        'kit_type',
        'lookup_name',
        'next_ship_ment',
        'next_shipment_qty',
        'pack',
        'piece_name',
        'room_name',
        'seat_depth',
        'seat_height',
        'seat_width',
        'shelf_distance',
        'style_name',
        'sub_category',
        'type_of_packaging',
        'unit_stock',
        'wood_finish',
        'material',
        'warehouse',
        'frame_finish',
        'name_ori',
        'returns_and_notice',
        'return_warranty',
        'return_warranty_text',
    ];
}
