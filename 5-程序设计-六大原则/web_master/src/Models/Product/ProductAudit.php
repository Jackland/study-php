<?php

namespace App\Models\Product;

use App\Models\Product\ProductDTO\ProductAudit\AssembleInfoDTO;
use App\Models\Product\ProductDTO\ProductAudit\DescriptionDTO;
use App\Models\Product\ProductDTO\ProductAudit\InformationDTO;
use App\Models\Product\ProductDTO\ProductAudit\MaterialPackageDTO;
use Framework\Model\EloquentModel;

/**
 * App\Models\Product\ProductAudit
 *
 * @property int $id 主键ID
 * @property int $product_id 产品id
 * @property int $customer_id seller的id
 * @property int $status 1待审核,2审核通过,3审核不通过,4取消
 * @property int $is_delete 0未删，1已删
 * @property string|null $remark 备注
 * @property string|null $operator 操作人员
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @property int $audit_type 1产品信息审核,2价格调整审核
 * @property string $price 价格
 * @property int $display_price 是否可见  1可见;0不可见
 * @property \Illuminate\Support\Carbon|null $price_effect_time 价格生效时间，只针对于价格审核
 * @property int $category_id 类目id
 * @property string|null $information 基础信息{"color_option_id":0,"material_option_id":0,"product_size":"","sold_separately":0,"title":"","need_install":0,"current_price":0.00,"display_price":1,"group_id":[],"image":"","product_type":{"type_id":1,"combo":[{"product_id":1,"quantity":1}],"no_combo":{"length":0,"width":0,"height":0,"weight":0}},"associated_product_ids":[]}
 * @see InformationDTO
 * @property string|null $description 产品描述和退货政策{"return_warranty":{"return":{"undelivered":{"days":7,"rate":25,"allow_return":1},"delivered":{"before_days":7,"after_days":0,"delivered_checked":0}},"warranty":{"month":3,"conditions":[]}}, "description":"","return_warranty_text":""}
 * @see DescriptionDTO
 * @property string|null $material_package 素材包管理地址{"product_images":[{"url":"","sort":1}],"images":[{"name":"","file_id":0,"url":"","m_id":0}],"files":[{"name":"","file_id":0,"url":"","m_id":0}],"videos":[{"name":"","file_id":0,"url":"","m_id":0}]}
 * @see MaterialPackageDTO
 * @property int $is_original_design 产品专利标识 0：无，1：有
 * @property string|null $assemble_info 包装信息{"assemble_length":"-1.00", "assemble_width":"-1.00", "assemble_height":"-1.00", "assemble_weight":"-1.00", "custom_field": [{"name":"","value":"","sort":1}]}
 * @see AssembleInfoDTO
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductAudit newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductAudit newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductAudit query()
 * @mixin \Eloquent
 * @property-read array $format_information
 */
class ProductAudit extends EloquentModel
{
    protected $table = 'oc_product_audit';

    protected $dates = [
        'create_time',
        'update_time',
        'price_effect_time',
    ];

    protected $fillable = [
        'product_id',
        'customer_id',
        'status',
        'is_delete',
        'remark',
        'operator',
        'create_time',
        'update_time',
        'audit_type',
        'price',
        'display_price',
        'price_effect_time',
        'category_id',
        'information',
        'description',
        'material_package',
        'is_original_design',
        'assemble_info',
    ];

    /**
     * @return array
     */
    public function getFormatInformationAttribute()
    {
        return json_decode($this->information, true);
    }
}
