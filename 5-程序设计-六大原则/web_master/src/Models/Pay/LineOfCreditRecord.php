<?php

namespace App\Models\Pay;

use App\Models\Customer\Customer;
use Framework\Model\EloquentModel;

/**
 * \App\Models\Pay\LineOfCreditRecord
 *
 * @property int $id
 * @property string $serial_number Serial Number
 * @property int $customer_id
 * @property float|null $old_line_of_credit 修改前信用额度
 * @property float|null $new_line_of_credit 修改后信用额度
 * @property string $date_added
 * @property int $operator_id 修改人ID
 * @property bool $type_id 1-信用额度充值; 2-信用额度扣减(支付订单); 3-退返品充值; 4-返点返金; 5-现货返金 Margin Refund; 6-信用额度减值; 7-Airwallex支付方式充值; 8-电汇；9-P卡
 * @property int|null $header_id 信用额度扣减--oc_order.order_id；退返品充值--amf.id
 * @property string|null $memo
 * @property int|null $company_account_id 公司收款账户表ID
 * @property string|null $platform_date 平台收款/扣款日期
 * @property int|null $platform_get_type_id 0 平台返点、1 首单充值、2 广告费补贴、3 发货错漏发、4 物流超时、5 账户资金转入 6 理赔服务 7 仲裁 8 直接付款
 * @property int|null $platform_pay_type_id 0 账户资金转出、
 * @property-read \App\Models\Customer\Customer $customer
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Pay\LineOfCreditRecord newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Pay\LineOfCreditRecord newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Pay\LineOfCreditRecord query()
 * @mixin \Eloquent
 * @property string|null $pay_faild_third_trade_number 支付失败的第三方交易流水号
 * @property string|null $account_transfer_number 账户转出编号 或 费用编号
 * @property float|null $exchange_us_rate 币种兑换美元的汇率
 * @property int|null $platform_second_type_id tb_sys_credit_line_platform_type.id
 */
class LineOfCreditRecord extends EloquentModel
{
    public $timestamps = false;
    protected $table = 'tb_sys_credit_line_amendment_record';

    protected $fillable = [
        'serial_number',
        'customer_id',
        'old_line_of_credit',
        'new_line_of_credit',
        'date_added',
        'operator_id',
        'type_id',
        'header_id',
        'memo',
        'company_account_id',
        'platform_date',
        'platform_get_type_id',
        'platform_pay_type_id',
        'platform_second_type_id',
        'pay_faild_third_trade_number',
        'account_transfer_number',
        'exchange_us_rate',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
