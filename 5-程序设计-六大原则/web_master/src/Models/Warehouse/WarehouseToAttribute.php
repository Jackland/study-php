<?php

namespace App\Models\Warehouse;

use Framework\Model\EloquentModel;

/**
 * App\Models\Warehouse\WarehouseToAttribute
 *
 * @property int $id 自增主键
 * @property int $warehouse_id 仓库ID
 * @property string $seller_type 以仓库适用的用户核算类型为划分标准.所有核算类型：all；普通（即除去美国本土核算类型）：normal；美国本土（核算类型为5，美国本土类型）：usNative
 * @property int $seller_assign 是否指定seller
 * @property string|null $create_username 创建人名称
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Warehouse\WarehouseToAttribute newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Warehouse\WarehouseToAttribute newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Warehouse\WarehouseToAttribute query()
 * @mixin \Eloquent
 */
class WarehouseToAttribute extends EloquentModel
{
    protected $table = 'tb_warehouses_to_attribute';

    protected $dates = [
        'create_time',
    ];

    protected $fillable = [
        'warehouse_id',
        'seller_type',
        'seller_assign',
        'create_username',
        'create_time',
    ];
}
