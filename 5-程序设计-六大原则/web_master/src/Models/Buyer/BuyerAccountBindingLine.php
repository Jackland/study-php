<?php

namespace App\Models\Buyer;

use Framework\Model\EloquentModel;

/**
 * App\Models\Buyer\BuyerAccountBindingLine
 *
 * @property int $id 主键id
 * @property int|null $head_id tb_sys_buyer_account_binding.id
 * @property int|null $customer_id buyer账号ID
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $create_user_name 创建人
 * @property \Illuminate\Support\Carbon|null $update_time 更新时间
 * @property string|null $update_user_name 更新人
 * @property string|null $program_code 程序号
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Buyer\BuyerAccountBindingLine newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Buyer\BuyerAccountBindingLine newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Buyer\BuyerAccountBindingLine query()
 * @mixin \Eloquent
 */
class BuyerAccountBindingLine extends EloquentModel
{
    protected $table = 'tb_sys_buyer_account_binding_line';

    protected $fillable = [
        'head_id',
        'customer_id',
        'create_time',
        'create_user_name',
        'update_time',
        'update_user_name',
        'program_code',
    ];
}
