<?php

namespace App\Models\Setup;

use Framework\Model\EloquentModel;

/**
 * App\Models\Setup\Setup
 *
 * @property int $id
 * @property string $parameter_key
 * @property string $parameter_value
 * @property string $parameter_desc
 * @property string|null $memo
 * @property string $create_user_name
 * @property \Illuminate\Support\Carbon $create_time
 * @property string|null $update_user_name
 * @property \Illuminate\Support\Carbon|null $update_time
 * @property string|null $program_code
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Setup\Setup newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Setup\Setup newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Setup\Setup query()
 * @mixin \Eloquent
 */
class Setup extends EloquentModel
{
    protected $table = 'tb_sys_setup';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'parameter_key',
        'parameter_value',
        'parameter_desc',
        'memo',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
    ];
}
