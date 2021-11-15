<?php

namespace App\Models\Setting;

use Framework\Model\EloquentModel;

/**
 * App\Models\Setting\Dictionary
 *
 * @property int $Id 主键自增
 * @property string $DicCategory 字典分类
 * @property string $DicName 分类名称
 * @property string $DicKey 字典KEY值
 * @property string $DicValue 字典Value值
 * @property string|null $Description 字典内容描述
 * @property string|null $CreateUserName 创建者
 * @property \Illuminate\Support\Carbon|null $CreateTime 创建时间
 * @property string|null $UpdateUserName 修改者
 * @property \Illuminate\Support\Carbon|null $UpdateTime 修改时间
 * @property int|null $status 有效标志：1-有效；0-无效
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Setting\Dictionary newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Setting\Dictionary newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Setting\Dictionary query()
 * @mixin \Eloquent
 */
class Dictionary extends EloquentModel
{
    protected $table = 'tb_sys_dictionary';
    protected $primaryKey = 'Id';

    protected $dates = [
        'CreateTime',
        'UpdateTime',
    ];

    protected $fillable = [
        'DicCategory',
        'DicName',
        'DicKey',
        'DicValue',
        'Description',
        'CreateUserName',
        'CreateTime',
        'UpdateUserName',
        'UpdateTime',
        'status',
    ];
}
