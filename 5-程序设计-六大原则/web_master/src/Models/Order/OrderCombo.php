<?php

namespace App\Models\Order;

use Framework\Model\EloquentModel;

/**
 * App\Models\Order\OrderCombo
 *
 * @property int $id
 * @property int|null $product_id combo品主产品Id
 * @property string|null $item_code 主产品Item_code
 * @property int|null $order_id 采购订单ID
 * @property int|null $order_product_id oc_order_product表ID
 * @property int|null $set_product_id 子产品Id
 * @property string|null $set_item_code 子产品Item_code
 * @property int|null $qty 子产品数量
 * @property string|null $memo 对于该条记录做备注用的
 * @property string|null $create_user_name 这条记录的创建者
 * @property \Illuminate\Support\Carbon|null $create_time 这条记录的创建时间
 * @property string|null $update_user_name 这条记录的创建者
 * @property \Illuminate\Support\Carbon|null $update_time 这条记录的更新时间
 * @property string|null $program_code 程序号
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Order\OrderCombo newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Order\OrderCombo newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Order\OrderCombo query()
 * @mixin \Eloquent
 */
class OrderCombo extends EloquentModel
{
    protected $table = 'tb_sys_order_combo';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'product_id',
        'item_code',
        'order_id',
        'order_product_id',
        'set_product_id',
        'set_item_code',
        'qty',
        'memo',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
    ];
}
