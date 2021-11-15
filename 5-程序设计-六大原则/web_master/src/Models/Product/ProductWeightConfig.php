<?php

namespace App\Models\Product;

use Framework\Model\EloquentModel;

/**
 * App\Models\Product\ProductWeightConfig
 *
 * @property int $id 主键id
 * @property int $product_id 产品id
 * @property string|null $category_ids 分类id集合
 * @property string|null $complex_trades 交易复杂度
 * @property string $sku 产品sku
 * @property string|null $mpn 产品mpn
 * @property string|null $upc 产品upc
 * @property string|null $asin 产品asin
 * @property int|null $status 产品上架状态 0已下架,1已上架
 * @property int|null $can_buy 是否单独购买 0：是 1: 否
 * @property int|null $is_deleted 是否删除 0：否 1：是
 * @property \Illuminate\Support\Carbon|null $date_added 添加日期
 * @property string $product_name 产品名称
 * @property string|null $screenname 店铺名称
 * @property int|null $quantity 产品上架数量
 * @property string|null $price 价格
 * @property int|null $is_part 是否是配件 0：否 1：是
 * @property int|null $is_rich 产品信息完整度是否符合条件 0：否 1：是
 * @property int|null $seller_id SellerId
 * @property string|null $custom_weight B类权重 浮点数的高精度类型：scaled_float 需要指定一个精度因子，比如10或100。elasticsearch会把真实值乘以这个因子后存储，取出时再还原。
 * @property int|null $country_id 国家ID
 * @property string|null $product_data_holder 文档数据
 * @property string|null $distribution 产品分布
 * @property int|null $sales_qty 售卖数量
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property string $create_name 创建人
 * @property \Illuminate\Support\Carbon|null $update_time 修改时间
 * @property string|null $update_name 修改人
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductWeightConfig newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductWeightConfig newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductWeightConfig query()
 * @mixin \Eloquent
 * @property-read \App\Models\Product\Product $product
 * @property-read \App\Models\Product\ProductCrontab $productCrontab
 */
class ProductWeightConfig extends EloquentModel
{
    protected $table = 'tb_product_weight_config';

    protected $dates = [
        'date_added',
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'product_id',
        'category_ids',
        'complex_trades',
        'sku',
        'mpn',
        'upc',
        'asin',
        'status',
        'can_buy',
        'is_deleted',
        'date_added',
        'product_name',
        'screenname',
        'quantity',
        'price',
        'is_part',
        'is_rich',
        'seller_id',
        'custom_weight',
        'country_id',
        'product_data_holder',
        'distribution',
        'sales_qty',
        'create_time',
        'create_name',
        'update_time',
        'update_name',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }

    public function productCrontab()
    {
        return $this->belongsTo(ProductCrontab::class, 'product_id', 'product_id');
    }
}
