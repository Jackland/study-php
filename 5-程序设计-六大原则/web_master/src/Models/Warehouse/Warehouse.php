<?php

namespace App\Models\Warehouse;

use Framework\Model\EloquentModel;

/**
 * App\Models\Warehouse\Warehouse
 *
 * @property int $id 自增主键
 * @property int $wh_id 仓库ID
 * @property int $warehouse_id B2B系统用仓库ID
 * @property string|null $wh_code
 * @property string $wh_name 仓库名称
 * @property string $system_from 适用系统：OMD,ZK,DJY
 * @property string $memo 备注
 * @property string $create_user_name 创建人
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property string|null $update_user_name 修改人
 * @property \Illuminate\Support\Carbon|null $update_time 修改时间
 * @property string|null $program_code 程序号
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Warehouse\Warehouse newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Warehouse\Warehouse newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Warehouse\Warehouse query()
 * @mixin \Eloquent
 */
class Warehouse extends EloquentModel
{
    protected $table = 'tb_sys_warehouse';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'wh_id',
        'warehouse_id',
        'wh_code',
        'wh_name',
        'system_from',
        'memo',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
    ];


}
