<?php

namespace App\Models\FeeOrder;

use App\Enums\Pay\PayCode;
use App\Models\Customer\Customer;
use Framework\Model\EloquentModel;

/**
 * App\Models\FeeOrder\FeeOrder
 *
 * @property int $id
 * @property string $order_no 费用单订单编号
 * @property int $order_type 订单类型
 * 1-销售订单
 * 2-rma订单...
 * @property string $order_type_alias 订单类型别名
 * 1-order_sales
 * 2-order_rma
 * @property int $order_id 订单id，根据不同的订单类型，关联不同的订单
 * 1-销售订单 tb_sys_customer_sales_order
 * 2-RMA oc_yzc_rma_order
 * @property int|null $buyer_id 费用单创建buyer
 * @property int $fee_type 费用类型
 * 1-仓租费
 * .......
 * @property float $fee_total 总费用
 * @property float $refund_amount 退款金额
 * @property string|null $payment_method 支付方式
 * @property string|null $payment_code 支付方式code
 * @property string|null $refund_code 退款方式code
 * @property float $balance 使用余额金额
 * @property float $poundage 该笔费用单的手续费
 * @property string|null $comment 购买备注
 * @property bool $is_show 是否显示
 * 1-是
 * 0-否
 * @property int $status 状态
 * @property \Illuminate\Support\Carbon|null $paid_at 支付时间
 * @property \Illuminate\Support\Carbon|null $refunded_at 退款时间
 * @property string|null $purchase_run_id tb_purchase_pay_record表的run id，用于二次支付使用
 * @property string|null $fee_order_run_id 费用单run id，用于标识同一次提交的费用单
 * @property \Illuminate\Support\Carbon $created_at 创建时间
 * @property \Illuminate\Support\Carbon $updated_at 更新时间
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $orderInfo
 * @property-read Customer|null $buyer
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\FeeOrder\FeeOrderStorageDetail[] $storageDetails
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\FeeOrder\FeeOrderSafeguardDetail[] $safeguardDetails
 * @property-read int|null $storage_details_count
 * @property-read bool $has_union_line_of_payment
 * @property-read float $actual_paid
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\FeeOrder\FeeOrder newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\FeeOrder\FeeOrder newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\FeeOrder\FeeOrder query()
 * @mixin \Eloquent
 * @property string|null $apInvoice_num 发票编号，用于财务系统同步
 */
class FeeOrder extends EloquentModel
{
    protected $table = 'oc_fee_order';
    public $timestamps = true;

    protected $fillable = [
        'order_no',
        'order_type',
        'order_type_alias',
        'order_id',
        'fee_type',
        'fee_total',
        'refund_amount',
        'payment_method',
        'payment_code',
        'refund_code',
        'balance',
        'poundage',
        'comment',
        'status',
        'paid_at',
        'refunded_at',
        'purchase_run_id',
        'fee_order_run_id',
    ];

    protected $dates = ['paid_at', 'refunded_at'];

    /**
     * 仓租明细
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function storageDetails()
    {
        return $this->hasMany(FeeOrderStorageDetail::class);
    }

    /**
     * 保障服务明细
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function safeguardDetails()
    {
        return $this->hasMany(FeeOrderSafeguardDetail::class);
    }

    //模型对应关系参照 MorphMapProvider
    public function orderInfo()
    {
        return $this->morphTo(null, 'order_type_alias', 'order_id');
    }

    public function buyer()
    {
        return $this->belongsTo(Customer::class, 'buyer_id');
    }

    /**
     * 获取实际支付的价格
     */
    public function getActualPaidAttribute(): float
    {
        if ($this->payment_code === PayCode::PAY_LINE_OF_CREDIT) {
            return (float)$this->balance;
        }
        return (float)$this->fee_total;
    }

    /**
     * 获取是否存在额外使用信用额度支付的情况
     * @return bool
     */
    public function getHasUnionLineOfPaymentAttribute(): bool
    {
        // 如果本身就是余额支付，则不考虑这种情况
        if ($this->payment_code === PayCode::PAY_LINE_OF_CREDIT) {
            return false;
        }
        if (!empty($this->balance) && $this->balance > 0) {
            return true;
        }
        return false;
    }
}
