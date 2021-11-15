<?php

namespace App\Models\Warehouse;

use Framework\Model\EloquentModel;

/**
 * App\Models\Warehouses\WarehousesToAttribute
 *
 * @property int $id 自增主键
 * @property string $warehouse_id 仓库ID
 * @property string $seller_type 以仓库适用的用户核算类型为划分标准.所有核算类型：all；普通（即除去美国本土核算类型）：normal；美国本土（核算类型为5，美国本土类型）：usNative
 * @property int $seller_assign 是否指定seller  1不用看tb_warehouses_to_seller表
 * @property string|null $create_username 创建人名称
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Warehouse\WarehousesToAttribute newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Warehouse\WarehousesToAttribute newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Warehouse\WarehousesToAttribute query()
 * @mixin \Eloquent
 */
class WarehousesToAttribute extends EloquentModel
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
