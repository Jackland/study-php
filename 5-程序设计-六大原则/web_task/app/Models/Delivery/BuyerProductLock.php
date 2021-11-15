<?php

namespace App\Models\Delivery;

use Illuminate\Database\Eloquent\Model;

/**
 * \App\Models\Delivery\BuyerProductLock
 *
 * @property int $id
 * @property int $buyer_id buyer id
 * @property int $product_id product id
 * @property int $qty buyer的锁定库存数量
 * @property int $type 锁定库存类型：1-库存下调 2-库存盘亏 3-囤货预锁
 * @property bool $is_processed 是否已经被处理
 * @property string|null $process_date 处理时间
 * @property string $create_time 创建时间
 * @property string|null $create_user 创建用户
 * @property string|null $update_time 更新时间
 * @property int $cost_id cost detail 表
 * @property int $foreign_key 外键
 * @mixin \Eloquent
 */
class BuyerProductLock extends Model
{
    protected $table = 'oc_buyer_product_lock';

    public $timestamps = false;
}
