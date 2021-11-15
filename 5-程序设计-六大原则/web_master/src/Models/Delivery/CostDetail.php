<?php

namespace App\Models\Delivery;

use App\Models\Customer\Customer;
use App\Models\Product\Product;
use Framework\Model\EloquentModel;

/**
 * \App\Models\Delivery\CostDetail
 *
 * @property int $id 自增主键
 * @property int $buyer_id BuyerID
 * @property int $source_line_id 来源ID
 * @property string|null $source_code 来源说明
 * @property int $sku_id SkuId
 * @property int $onhand_qty 当前在库数量
 * @property float|null $unit_cost 会计成本(预留字段)
 * @property float|null $unit_cost_store 店铺成本(预留字段)
 * @property int $original_qty 初始入库数量
 * @property float|null $freight 运费(预留字段)
 * @property float|null $other_charge 其他成本(预留字段)
 * @property int $seller_id sellerId
 * @property int|null $whId 仓库ID(预留字段)
 * @property int|null $last_cost_id 调出批次ID(预留字段)
 * @property int|null $start_cost_id 源批次ID(预留字段)
 * @property string|null $memo 备注
 * @property string|null $create_user_name 创建者
 * @property string|null $create_time 创建时间
 * @property string|null $update_user_name 更新者
 * @property string|null $update_time 更新时间
 * @property string|null $program_code 程序号
 * @property bool|null $osj_sync_flag
 * @property bool|null $type 1:采购订单2：退返品订单
 * @property int|null $rma_id 退返品Id
 * @property-read \App\Models\Customer\Customer $buyer
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Delivery\DeliveryLine[] $deliveryLines
 * @property-read int|null $delivery_lines_count
 * @property-read \App\Models\Product\Product $product
 * @property-read \App\Models\Delivery\ReceiveLine $receiveLine
 * @property-read \App\Models\Customer\Customer $seller
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Delivery\CostDetail newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Delivery\CostDetail newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Delivery\CostDetail query()
 * @mixin \Eloquent
 */
class CostDetail extends EloquentModel
{
    protected $table = 'tb_sys_cost_detail';

    public function buyer()
    {
        return $this->belongsTo(Customer::class, 'buyer_id');
    }

    public function seller()
    {
        return $this->belongsTo(Customer::class, 'seller_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'sku_id');
    }

    public function deliveryLines()
    {
        return $this->hasMany(DeliveryLine::class, 'CostId');
    }

    public function receiveLine()
    {
        return $this->belongsTo(ReceiveLine::class, 'source_line_id');
    }
}
