<?php

namespace App\Models\Stock;

use App\Models\Product\Product;
use App\Models\Product\ProductDescription;
use Framework\Model\EloquentModel;

/**
 * App\Models\Stock\ReceiptsOrderDetail
 *
 * @property int $receive_order_detail_id 入库单明细表ID(主键自增)
 * @property int $receive_order_id 入库单表ID
 * @property string|null $receive_number 入库单号
 * @property string|null $sku 系统sku
 * @property string|null $mpn 客户sku(MPN)
 * @property int $product_id 产品ID oc_product.product_id
 * @property string|null $item_price 单价
 * @property float|null $length 长
 * @property float|null $width 宽
 * @property float|null $height 高
 * @property float|null $weight 重
 * @property int|null $expected_qty 预计入库数量
 * @property int|null $received_qty 仓库收到数量
 * @property string|null $receive_remark 收货备注
 * @property string|null $run_id RunId
 * @property string|null $source_receive_id 来源系统入库单头表主键ID
 * @property string|null $source_receive_detail_id 来源系统入库单明细表主键ID
 * @property string|null $create_user_name 创建者
 * @property string|null $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property string|null $update_time 修改时间
 * @property string|null $program_code 程序号
 * @property-read \App\Models\Stock\ReceiptsOrder $receiptsOrder
 * @property-read Product $product
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Stock\ReceiptsOrderDetail newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Stock\ReceiptsOrderDetail newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Stock\ReceiptsOrderDetail query()
 * @mixin \Eloquent
 * @property string|null $hscode HScode
 * @property string|null $301_hscode 301-HScode
 * @property string|null $product_name 产品名称
 * @property-read \App\Models\Product\ProductDescription $productDesc
 */
class ReceiptsOrderDetail extends EloquentModel
{
    protected $table = 'tb_sys_receipts_order_detail';
    protected $primaryKey = 'receive_order_detail_id';
//    protected $casts = ['expected_qty' => 'string'];

    protected $fillable = [
        'receive_order_id',
        'receive_number',
        'sku',
        'mpn',
        'product_id',
        'item_price',
        'length',
        'width',
        'height',
        'weight',
        'expected_qty',
        'received_qty',
        'receive_remark',
        'run_id',
        'source_receive_id',
        'source_receive_detail_id',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
    ];

    public function receiptsOrder()
    {
        return $this->hasOne(ReceiptsOrder::class, 'receive_order_id', 'receive_order_id');
    }

    public function productDesc()
    {
        return $this->hasOne(ProductDescription::class, 'product_id', 'product_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
