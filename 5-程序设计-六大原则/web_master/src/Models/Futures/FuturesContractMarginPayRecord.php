<?php

namespace App\Models\Futures;

use Framework\Model\EloquentModel;

/**
 * App\Models\Futures\FuturesContractMarginPayRecord
 *
 * @property int $id 自增主键
 * @property int $contract_id oc_futures_contract.id合约ID
 * @property int $customer_id customer_id
 * @property int $type 1为授信额度,3应收款,4抵押物
 * @property string $amount 合约的保证金金额
 * @property int $bill_type 类型1为支出，2为收入
 * @property int $bill_status 0,未计入账单;1已计入账
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @property string|null $operator 操作人
 * @property string|null $remark 备注
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Futures\FuturesContractMarginPayRecord newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Futures\FuturesContractMarginPayRecord newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Futures\FuturesContractMarginPayRecord query()
 * @mixin \Eloquent
 */
class FuturesContractMarginPayRecord extends EloquentModel
{
    protected $table = 'oc_futures_contract_margin_pay_record';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'contract_id',
        'customer_id',
        'type',
        'amount',
        'bill_type',
        'bill_status',
        'create_time',
        'update_time',
        'operator',
        'remark',
    ];
}
