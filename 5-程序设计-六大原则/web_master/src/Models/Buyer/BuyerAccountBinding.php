<?php

namespace App\Models\Buyer;

use Framework\Model\EloquentModel;

/**
 * App\Models\Buyer\BuyerAccountBinding
 *
 * @property int $id 主键id
 * @property \Illuminate\Support\Carbon|null $effect_time 生效时间
 * @property \Illuminate\Support\Carbon|null $expire_time 失效时间
 * @property string|null $remark 备注
 * @property string|null $file_ids tb_file_upload_menu.id(阿里云资源)
 * @property int|null $status 状态
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $create_user_name 创建人
 * @property \Illuminate\Support\Carbon|null $update_time 更新时间
 * @property string|null $update_user_name 更新人
 * @property string|null $program_code 程序号
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Buyer\BuyerAccountBinding newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Buyer\BuyerAccountBinding newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Buyer\BuyerAccountBinding query()
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Buyer\BuyerAccountBindingLine[] $relateLines
 * @property-read int|null $relate_lines_count
 * @mixin \Eloquent
 */
class BuyerAccountBinding extends EloquentModel
{
    protected $table = 'tb_sys_buyer_account_binding';

    protected $fillable = [
        'effect_time',
        'expire_time',
        'remark',
        'file_ids',
        'status',
        'create_time',
        'create_user_name',
        'update_time',
        'update_user_name',
        'program_code',
    ];

    public function relateLines()
    {
        return $this->hasMany(BuyerAccountBindingLine::class, 'head_id');
    }
}
