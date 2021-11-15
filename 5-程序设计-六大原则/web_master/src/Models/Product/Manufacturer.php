<?php

namespace App\Models\Product;

use Framework\Model\EloquentModel;

/**
 * App\Models\Product\Manufacturer
 *
 * @property int $manufacturer_id
 * @property string $name
 * @property string|null $image
 * @property string $image_id
 * @property int $sort_order
 * @property int|null $customer_id 客户id
 * @property int|null $is_partner 是否为卖家
 * @property int|null $can_brand 能否被贴牌 0 ：不能1：能 默认为不能
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Manufacturer newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Manufacturer newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Manufacturer query()
 * @mixin \Eloquent
 */
class Manufacturer extends EloquentModel
{
    protected $table = 'oc_manufacturer';
    protected $primaryKey = 'manufacturer_id';

    protected $fillable = [
        'name',
        'image',
        'image_id',
        'sort_order',
        'customer_id',
        'is_partner',
        'can_brand',
    ];
}
