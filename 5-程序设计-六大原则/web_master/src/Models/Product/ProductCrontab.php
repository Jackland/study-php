<?php

namespace App\Models\Product;

use App\Models\Link\CustomerPartnerToProduct;
use Framework\Model\EloquentModel;

/**
 * App\Models\Product\ProductCrontab
 *
 * @property int $id
 * @property int $product_id
 * @property string $order_money 产品90天内的采购订单总额
 * @property string $price_change_rate 14天内产品单价(货值)变化率，正数表示升价，负数表示降价，-0.671795表示降价67.1795%
 * @property \Illuminate\Support\Carbon|null $date_added
 * @property \Illuminate\Support\Carbon|null $order_money_date_modified
 * @property \Illuminate\Support\Carbon|null $price_change_rate_date_modified
 * @property int $purchase_num 采购数量
 * @property int $return_num 退返数量
 * @property string $return_rate 退返率，2.97表示2.97%
 * @property string $last_return_rate 最近一次的退返率
 * @property int $is_changed 比较退返品率是否发生变化
 * @property \Illuminate\Support\Carbon|null $return_date_modified 退返数据更新时间
 * @property string $amount_7 产品7天内的采购订单总额
 * @property string $amount_14 产品14天内的采购订单总额
 * @property \Illuminate\Support\Carbon|null $amount_modified 采购订单总额修改时间
 * @property-read \App\Models\Link\CustomerPartnerToProduct $customerPartnerToProduct
 * @property-read \App\Models\Product\Product $product
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductCrontab newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductCrontab newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductCrontab query()
 * @mixin \Eloquent
 * @property int $download_14 产品14天内素材包下载次数
 * @property string|null $download_modified 产品下载修改时间
 * @property-read \App\Models\Product\ProductWeightConfig $productWeightConfig
 */
class ProductCrontab extends EloquentModel
{
    protected $table = 'oc_product_crontab';

    protected $dates = [
        'date_added',
        'order_money_date_modified',
        'price_change_rate_date_modified',
        'return_date_modified',
        'amount_modified',
    ];

    protected $fillable = [
        'product_id',
        'order_money',
        'price_change_rate',
        'date_added',
        'order_money_date_modified',
        'price_change_rate_date_modified',
        'purchase_num',
        'return_num',
        'return_rate',
        'last_return_rate',
        'is_changed',
        'return_date_modified',
        'amount_7',
        'amount_14',
        'amount_modified',
    ];

    public function customerPartnerToProduct()
    {
        return $this->hasOne(CustomerPartnerToProduct::class, 'product_id', 'product_id');
    }

    public function product()
    {
        return $this->hasOne(Product::class, 'product_id', 'product_id');
    }

    public function productWeightConfig()
    {
        return $this->hasOne(ProductWeightConfig::class, 'product_id', 'product_id');
    }
}
