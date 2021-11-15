<?php

namespace App\Models\Product;

use App\Models\Warehouse\WarehouseInfo;
use App\Models\Warehouse\WarehouseToAttribute;
use Framework\Model\EloquentModel;

/**
 * App\Models\Product\WarehouseProductDistribution
 *
 * @property int $id 自增主键
 * @property int $product_id 产品ID
 * @property int $warehouse_id 仓库ID
 * @property int $stock_qty 库存量
 * @property string|null $memo 备注
 * @property string|null $create_user_name 创建者
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property string|null $update_user_name 更新者
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @property string|null $program_code 程序号
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Warehouse\WarehouseToAttribute[] $attribute
 * @property-read int|null $attribute_count
 * @property-read \App\Models\Product\Product $product
 * @property-read \App\Models\Warehouse\WarehouseInfo $warehouse
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\WarehouseProductDistribution newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\WarehouseProductDistribution newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\WarehouseProductDistribution query()
 * @mixin \Eloquent
 */
class WarehouseProductDistribution extends EloquentModel
{
    protected $table = 'tb_sys_warehouse_product_distribution';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'stock_qty',
        'memo',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(WarehouseInfo::class, 'warehouse_id', 'WarehouseID');
    }

    public function attribute()
    {
        return $this->hasMany(WarehouseToAttribute::class, 'warehouse_id');
    }
}
