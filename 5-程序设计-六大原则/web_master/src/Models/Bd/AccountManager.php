<?php

namespace App\Models\Bd;

use App\Models\Customer\Customer;
use Framework\Model\EloquentModel;

/**
 * App\Models\Bd\AccountManager
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
 * @property-read \App\Models\Bd\LeasingManager $account
 * @property-read \App\Models\Customer\Customer $buyer
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Bd\AccountManager newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Bd\AccountManager newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Bd\AccountManager query()
 * @mixin \Eloquent
 */
class AccountManager extends EloquentModel
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

    public function account()
    {
        return $this->belongsTo(LeasingManager::class, 'AccountId', 'customer_id');
    }

    public function buyer()
    {
        return $this->belongsTo(Customer::class, 'BuyerId', 'customer_id');
    }
}
