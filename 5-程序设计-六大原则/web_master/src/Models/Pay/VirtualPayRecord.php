<?php

namespace App\Models\Pay;

use App\Models\Customer\Customer;
use Framework\Model\EloquentModel;

/**
 * \App\Models\Pay\VirtualPayRecord
 *
 * @property int $id
 * @property string $serial_number 序列号,Ymd+6位随机数
 * @property int $relation_id
 * type为1时,订单ID
 * type为2时,RMA ID
 * type为3时,返金协议ID
 * type为4时,fee order ID
 * @property int $customer_id
 * @property bool $type
 * 1-采购订单支付
 * 2-RMA退款
 * 3-返金
 * 4-收仓租
 * @property float $amount 交易额
 * @property string|null $memo
 * @property string $create_time
 * @property-read \App\Models\Customer\Customer $customer
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Pay\VirtualPayRecord newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Pay\VirtualPayRecord newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Pay\VirtualPayRecord query()
 * @mixin \Eloquent
 */
class VirtualPayRecord extends EloquentModel
{
    public $timestamps = false;
    protected $table = 'oc_virtual_pay_record';

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
