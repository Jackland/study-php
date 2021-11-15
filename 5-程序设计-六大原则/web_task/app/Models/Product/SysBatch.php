<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Product\SysBatch
 *
 * @property int $batch_id 批次表ID
 * @property string|null $batch_number 批次号
 * @property int|null $receipts_order_id 入出库明细表ID
 * @property int|null $receipts_order_line_id 入出库头表ID
 * @property string|null $source_code 来源
 * @property string|null $sku 系统sku
 * @property string|null $mpn 客户sku(MPN)
 * @property int|null $product_id 产品ID oc_product.product_id
 * @property int|null $original_qty 初始在库数量(批次入库数量)
 * @property int|null $onhand_qty 当前在库数量
 * @property string|null $warehouse 仓库  默认为云仓库Cloud Warehouse
 * @property string|null $remark 收货备注
 * @property int|null $customer_id 客户id
 * @property string|null $receive_date 收货日期
 * @property int $source_batch_id 大建云批次ID
 * @property string|null $create_user_name 创建者
 * @property string|null $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property string|null $update_time 修改时间
 * @property string|null $program_code 程序号
 * @property int|null $rma_id 退返品Id
 * @property int $c_fee_flag 是否首次计算仓租标志位
 * @property int|null $to_transfer 保证金商品同步到在库:null-非保证金;0-未同步;1-已同步;2-同步失败;3-未同步调回;4-同步调回成功;5-同步调回失败
 * @property int|null $remaining_qty 未达成保证金协议剩余数量
 * @property float $unit_price 收货估算货值
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\SysBatch newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\SysBatch newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\SysBatch query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\SysBatch whereBatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\SysBatch whereBatchNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\SysBatch whereCFeeFlag($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\SysBatch whereCreateTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\SysBatch whereCreateUserName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\SysBatch whereCustomerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\SysBatch whereMpn($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\SysBatch whereOnhandQty($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\SysBatch whereOriginalQty($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\SysBatch whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\SysBatch whereProgramCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\SysBatch whereReceiptsOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\SysBatch whereReceiptsOrderLineId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\SysBatch whereReceiveDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\SysBatch whereRemainingQty($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\SysBatch whereRemark($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\SysBatch whereRmaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\SysBatch whereSku($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\SysBatch whereSourceBatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\SysBatch whereSourceCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\SysBatch whereToTransfer($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\SysBatch whereUnitPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\SysBatch whereUpdateTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\SysBatch whereUpdateUserName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\SysBatch whereWarehouse($value)
 * @mixin \Eloquent
 */
class SysBatch extends Model
{
    public $timestamps = false;
    protected $table = 'tb_sys_batch';
    protected $primaryKey = 'batch_id';
}