<?php

namespace App\Models\Warehouse;

use App\Models\Product\Product;
use Framework\Model\EloquentModel;

/**
 * App\Models\Warehouse\SellerInventoryAdjustLine
 *
 * @property int $inventory_line_id 库存调整明细ID
 * @property int $inventory_id 库存调整ID
 * @property string $sku 产品Code
 * @property int|null $product_id 产品id
 * @property int $qty 数量
 * @property string|null $create_user_name
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $update_user_name
 * @property \Illuminate\Support\Carbon|null $update_time 最后更新时间
 * @property string|null $program_code 程序号
 * @property string $mpn 产品mpn
 * @property string|null $seller_declaration_amount seller申报金额(单个产品)
 * @property string|null $platform_declaration_amount 平台申报金额(单个产品)
 * @property string|null $damages 最终赔偿金额(单个产品)
 * @property string|null $compensate_amount 历史平均成交货值单价*70%
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Warehouse\SellerInventoryAdjustLine newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Warehouse\SellerInventoryAdjustLine newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Warehouse\SellerInventoryAdjustLine query()
 * @mixin \Eloquent
 * @property-read \App\Models\Product\Product|null $product
 */
class SellerInventoryAdjustLine extends EloquentModel
{
    protected $table = 'tb_sys_seller_inventory_adjust_line';
    protected $primaryKey = 'inventory_line_id';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'inventory_id',
        'sku',
        'product_id',
        'qty',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
        'mpn',
        'seller_declaration_amount',
        'platform_declaration_amount',
        'damages',
        'compensate_amount',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
