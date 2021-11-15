<?php

namespace App\Models\DelicacyManagement;

use Framework\Model\EloquentModel;

/**
 * App\Models\DelicacyManagement\DelicacyManagement
 *
 * @property int $id
 * @property int $seller_id
 * @property int $buyer_id
 * @property int $product_id oc_product.product_id
 * @property string $current_price 当前生效的价格
 * @property int $product_display 是否显示价格（默认显示）
 * @property string $price 精细化管理的价格
 * @property string $pickup_price 用于记录的上门取货价格
 * @property \Illuminate\Support\Carbon $effective_time 生效时间
 * @property \Illuminate\Support\Carbon $expiration_time 失效时间
 * @property int $is_update 用于标识是否 更新了价格( price->current_price )
 * @property \Illuminate\Support\Carbon $add_time 添加时间
 * @property \Illuminate\Support\Carbon $update_time 修改时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\DelicacyManagement\DelicacyManagement newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\DelicacyManagement\DelicacyManagement newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\DelicacyManagement\DelicacyManagement query()
 * @mixin \Eloquent
 */
class DelicacyManagement extends EloquentModel
{
    protected $table = 'oc_delicacy_management';

    protected $dates = [
        'effective_time',
        'expiration_time',
        'add_time',
        'update_time',
    ];

    protected $fillable = [
        'seller_id',
        'buyer_id',
        'product_id',
        'current_price',
        'product_display',
        'price',
        'pickup_price',
        'effective_time',
        'expiration_time',
        'is_update',
        'add_time',
        'update_time',
    ];
}
