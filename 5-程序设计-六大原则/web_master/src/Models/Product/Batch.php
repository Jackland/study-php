<?php

namespace App\Models\Product;

use App\Models\Customer\Customer;
use App\Models\Stock\ReceiptsOrder;
use Framework\Model\EloquentModel;


/**
 * App\Models\Product\Batch
 *
 * @property int $batch_id 批次表ID
 * @property string|null $batch_number 批次号
 * @property int|null $receipts_order_id 入出库明细表ID
 * @property int|null $receipts_order_line_id 入出库头表ID
 * @property int|null $transaction_type 入库类型：1-入库单收货；2-库存上调；3-盘盈；4-调货入库；5-RMA退货；6-其他
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
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property \Illuminate\Support\Carbon|null $update_time 修改时间
 * @property string|null $program_code 程序号
 * @property int|null $rma_id 退返品Id
 * @property bool $c_fee_flag 是否首次计算仓租标志位
 * @property int|null $to_transfer 保证金商品同步到在库:null-非保证金;0-未同步;1-已同步;2-同步失败;3-未同步调回;4-同步调回成功;5-同步调回失败
 * @property int|null $remaining_qty 未达成保证金协议剩余数量
 * @property float $unit_price 收货估算货值
 * @property-read \App\Models\Customer\Customer $customer
 * @property-read ReceiptsOrder $receiptsOrder
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Batch newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Batch newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Batch query()
 * @mixin \Eloquent
 */
class Batch extends EloquentModel
{
    public const CREATED_AT = 'create_time';
    public const UPDATED_AT = 'update_time';

    protected $table = 'tb_sys_batch';
    protected $primaryKey = 'batch_id';
    public $timestamps = true;

    protected $fillable = [
        'batch_number',
        'receipts_order_id',
        'receipts_order_line_id',
        'transaction_type',
        'source_code',
        'sku',
        'mpn',
        'product_id',
        'original_qty',
        'onhand_qty',
        'warehouse',
        'remark',
        'customer_id',
        'receive_date',
        'source_batch_id',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
        'rma_id',
        'c_fee_flag',
        'to_transfer',
        'remaining_qty',
        'unit_price',
    ];

    public function customer()
    {
        return $this->hasOne(Customer::class, 'customer_id', 'customer_id');
    }

    public function receiptsOrder()
    {
        return $this->belongsTo(ReceiptsOrder::class, 'receipts_order_id');
    }
}
