<?php

namespace App\Models\Product;

use App\Models\Customer\Country;
use App\Models\Link\CustomerPartnerToProduct;
use App\Models\Product\Option\OptionValueDescription;
use Framework\Model\EloquentModel;

/**
 * App\Models\Product\ProductExts
 *
 * @property int $id 主键
 * @property int|null $product_id 产品id
 * @property string|null $sku sku
 * @property \Illuminate\Support\Carbon|null $receive_date 收货日期
 * @property string|null $create_user_name 创建人
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $update_user_name 更新人
 * @property \Illuminate\Support\Carbon|null $update_time 更新时间
 * @property string|null $program_code 程序号
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductExts newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductExts newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductExts query()
 * @mixin \Eloquent
 * @property bool $is_original_design 产品专利标识 0：无，1：有
 * @property int $is_customize 定制化标识 0否 1是
 * @property string $origin_place_code 原产地code,oc_country的iso_code_3
 * @property string $filler 填充物
 * @property string|null $assemble_length 组装长度 -1.00 代表不适用
 * @property string|null $assemble_width 组装宽度 -1.00 代表不适用
 * @property string|null $assemble_height 组装高度 -1.00 代表不适用
 * @property string|null $assemble_weight 组装重量 -1.00 代表不适用
 * @property string $assemble_length_show 组装长度 不适用返回:Not Applicable,适用返回准确数值
 * @property string $assemble_width_show 组装宽度 不适用返回:Not Applicable,适用返回准确数值
 * @property string $assemble_height_show 组装高度 不适用返回:Not Applicable,适用返回准确数值
 * @property string $assemble_weight_show 组装重量 不适用返回:Not Applicable,适用返回准确数值
 * @property-read \App\Models\Link\CustomerPartnerToProduct $customerPartnerToProduct
 * @property-read \App\Models\Product\Product $product
 * @property-read OptionValueDescription $fillerOptionValue
 * @property-read Country $originPlaceCountry 原产地国家
 */
class ProductExts extends EloquentModel
{
    protected $table = 'oc_product_exts';

    protected $dates = [
        'receive_date',
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'product_id',
        'sku',
        'receive_date',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
        'is_original_design',
        'is_customize',
        'origin_place_code',
        'filler',
        'assemble_length',
        'assemble_width',
        'assemble_height',
        'assemble_weight',
    ];

    public function customerPartnerToProduct()
    {
        return $this->hasOne(CustomerPartnerToProduct::class, 'product_id', 'product_id');
    }

    public function product()
    {
        return $this->hasOne(Product::class, 'product_id', 'product_id');
    }

    public function fillerOptionValue()
    {
        return $this->belongsTo(OptionValueDescription::class, 'filler', 'option_value_id');
    }

    public function originPlaceCountry()
    {
        return $this->hasOne(Country::class, 'iso_code_3', 'origin_place_code');
    }

    public function getAssembleLengthShowAttribute()
    {
        return $this->formatAssembleField($this->assemble_length);
    }

    public function getAssembleWidthShowAttribute()
    {
        return $this->formatAssembleField($this->assemble_width);
    }

    public function getAssembleHeightShowAttribute()
    {
        return $this->formatAssembleField($this->assemble_height);
    }

    public function getAssembleWeightShowAttribute()
    {
        return $this->formatAssembleField($this->assemble_weight);
    }

    /**
     * 格式化assemble相关字段数据
     *
     * @param $assembleField
     * @return string
     */
    private function formatAssembleField($assembleField): string
    {
        if (is_null($assembleField)) {
            return 'Seller maintenance in progress';
        }
        if ($assembleField == -1.00) {
            return 'Not Applicable';
        }

        return (string)$assembleField;
    }
}
