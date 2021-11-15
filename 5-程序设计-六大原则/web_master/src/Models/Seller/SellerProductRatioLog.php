<?php

namespace App\Models\Seller;

use Framework\Model\EloquentModel;

/**
 * App\Models\Seller\SellerProductRatioLog
 *
 * @property int $id
 * @property int $seller_product_ratio_id oc_seller_product_ratio.id
 * @property string $old_config 存json，旧的配置，默认第一条可为空,格式为{product_ratio,service_ratio,effective_time}
 * @property string $new_config 存json，新的配置，格式同上
 * @property \Illuminate\Support\Carbon $create_time
 * @property-read SellerProductRatio $sellerProductRatio
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Seller\SellerProductRatioLog newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Seller\SellerProductRatioLog newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Seller\SellerProductRatioLog query()
 * @mixin \Eloquent
 */
class SellerProductRatioLog extends EloquentModel
{
    protected $table = 'oc_seller_product_ratio_log';

    protected $dates = [
        'create_time',
    ];

    protected $fillable = [
        'seller_product_ratio_id',
        'old_config',
        'new_config',
        'create_time',
    ];

    public function sellerProductRatio()
    {
        return $this->belongsTo(SellerProductRatio::class);
    }
}
