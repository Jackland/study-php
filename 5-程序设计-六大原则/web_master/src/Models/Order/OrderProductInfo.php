<?php

namespace App\Models\Order;

use Framework\Model\EloquentModel;

/**
 * App\Models\Order\OrderProductInfo
 *
 * @property int $id 主键ID 自增主键ID
 * @property int $order_id 采购订单ID
 * @property int $order_product_id 采购订单商品明细记录ID
 * @property int $product_id 产品ID
 * @property string|null $item_code 单品SKU或combo品父SKU
 * @property int $qty 采购数量
 * @property string|null $length_inch 长(英寸)
 * @property string|null $width_inch 宽(英寸)
 * @property string|null $height_inch 高(英寸)
 * @property string|null $weight_lbs 重(磅)
 * @property string|null $length_cm 长(厘米)
 * @property string|null $width_cm 宽(厘米)
 * @property string|null $height_cm 高(厘米)
 * @property string|null $weight_kg 重(千克)
 * @property int $combo_flag 是否是combo商品
 * @property int $ltl_flag 是否是超大件商品
 * @property string|null $volume 产品体积 单位m³（单个产品体积，如果产品为Combo体积为子产品体积*组成数量 之和）
 * @property string|null $volume_inch 产品体积 单位ft³（单个产品体积，如果产品为Combo体积为子产品体积*组成数量 之和）
 * @property string|null $memo 备注
 * @property string $create_user_name 创建人
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property string|null $update_user_name 更新人
 * @property \Illuminate\Support\Carbon|null $update_time 更新时间
 * @property string|null $program_code 程序号
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Order\OrderProductInfo newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Order\OrderProductInfo newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Order\OrderProductInfo query()
 * @mixin \Eloquent
 * @property float $freight 运费(单位为当前币种)
 */
class OrderProductInfo extends EloquentModel
{
    protected $table = 'oc_order_product_info';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'order_id',
        'order_product_id',
        'product_id',
        'item_code',
        'qty',
        'length_inch',
        'width_inch',
        'height_inch',
        'weight_lbs',
        'length_cm',
        'width_cm',
        'height_cm',
        'weight_kg',
        'combo_flag',
        'ltl_flag',
        'volume',
        'volume_inch',
        'memo',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
    ];
}
