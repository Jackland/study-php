<?php

namespace App\Models\Buyer;

use Framework\Model\EloquentModel;

/**
 * App\Models\Buyer\BuyerAccountManager
 *
 * @property int $Id 自增主键
 * @property int $AccountId 账户经理的ID
 * @property int $BuyerId Buyer的ID
 * @property int|null $UserId
 * @property string|null $Memo 备注
 * @property string|null $CreateUserName 创建者
 * @property \Illuminate\Support\Carbon|null $CreateTime 创建时间
 * @property string|null $UpdateUserName 更新者
 * @property \Illuminate\Support\Carbon|null $UpdateTime 更新时间
 * @property string|null $ProgramCode 程序号
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Buyer\BuyerAccountManager newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Buyer\BuyerAccountManager newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Buyer\BuyerAccountManager query()
 * @mixin \Eloquent
 */
class BuyerAccountManager extends EloquentModel
{
    protected $table = 'tb_sys_buyer_account_manager';
    protected $primaryKey = 'Id';

    protected $dates = [
        'CreateTime',
        'UpdateTime',
    ];

    protected $fillable = [
        'AccountId',
        'BuyerId',
        'UserId',
        'Memo',
        'CreateUserName',
        'CreateTime',
        'UpdateUserName',
        'UpdateTime',
        'ProgramCode',
    ];
}
