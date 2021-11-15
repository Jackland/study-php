<?php

namespace App\Models\Delivery;

use App\Models\Customer\Customer;
use App\Models\Product\Product;
use Framework\Model\EloquentModel;

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
 * @property int $foreign_key 外键 type为3时，使用的是tb_sys_order_associated_pre的ID
 * @property-read \App\Models\Customer\Customer $buyer
 * @property-read \App\Models\Delivery\CostDetail $costDetail
 * @property-read \App\Models\Product\Product $product
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Delivery\BuyerProductLock newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Delivery\BuyerProductLock newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Delivery\BuyerProductLock query()
 * @mixin \Eloquent
 */
class BuyerProductLock extends EloquentModel
{
    protected $table = 'oc_buyer_product_lock';

    public function buyer()
    {
        return $this->belongsTo(Customer::class, 'buyer_id', 'customer_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }

    public function costDetail()
    {
        return $this->belongsTo(CostDetail::class, 'cost_id', 'id');
    }
}
