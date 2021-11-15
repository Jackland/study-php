<?php

namespace App\Models\Seller;

use Framework\Model\EloquentModel;

/**
 * App\Models\Seller\SellerStore
 *
 * @property int $id
 * @property int $seller_id SellerID
 * @property string|null $store_home_json 店铺首页的JSON数据
 * @property \Carbon\Carbon $store_home_json_updated_at 店铺首页信息更新时间
 * @property string|null $store_introduction_json 店铺介绍页的JSON数据
 * @property \Carbon\Carbon $store_introduction_json_updated_at 店铺介绍页信息更新时间
 * @property \Carbon\Carbon $created_at 创建时间
 * @property \Carbon\Carbon $updated_at 更新时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Seller\SellerStore newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Seller\SellerStore newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Seller\SellerStore query()
 * @mixin \Eloquent
 */
class SellerStore extends EloquentModel
{
    protected $table = 'oc_seller_store';
    public $timestamps = true;

    public $dates = [
        'store_home_json_updated_at',
        'store_introduction_json_updated_at',
    ];

    protected $fillable = [
        'seller_id',
        'store_home_json',
        'store_introduction_json',
    ];
}
