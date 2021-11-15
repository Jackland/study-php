<?php

namespace App\Models\SellerBill;

use Framework\Model\EloquentModel;

/**
 * App\Models\SellerBill\SellerBill
 *
 * @property int $id 自增主键
 * @property string|null $serial_number 流水号 当前结算周期的结算日期+8位随机数字
 * @property int|null $seller_id SellerId
 * @property \Illuminate\Support\Carbon|null $start_date 开始时间
 * @property \Illuminate\Support\Carbon|null $end_date 结束时间
 * @property \Illuminate\Support\Carbon|null $settlement_date 结款时间
 * @property string|null $reserve 上期期末余额
 * @property string|null $previous_reserve 期初余额
 * @property string|null $total 总余额
 * @property string $frozen_total giga onsite账单冻结金额总额
 * @property string|null $settlement 需要结款的余额
 * @property int|null $settlement_status 结算状态 0:正在进行中 1:结算中 2:已结算
 * @property string|null $actual_settlement 实际结款金额
 * @property int|null $is_settlement 是否结款
 * @property \Illuminate\Support\Carbon|null $confirm_date 财务确认时间
 * @property int|null $receipt_file_menu_id 账单结算水单附件的OSS文件menuId
 * @property int|null $seller_account_id seller结算账户ID
 * @property string|null $remark 财务确认账单备注
 * @property string|null $memo 备注
 * @property string|null $create_user_name 创建者
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property \Illuminate\Support\Carbon|null $update_time 修改时间
 * @property string|null $program_code 程序号
 * @property int $settle_apply seller是否申请余额结款
 * @property int $settle_type 账单结算方式 0:确认结算 1:转期初余额
 * @property string $arrears_principal 本结算周期的欠款本金，默认为负数，无欠款时记0
 * @property \Illuminate\Support\Carbon|null $arrears_date 供应链金融欠款开始计算时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerBill\SellerBill newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerBill\SellerBill newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerBill\SellerBill query()
 * @mixin \Eloquent
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\SellerBill\SellerBillFrozen[] $frozen
 * @property-read int|null $frozen_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\SellerBill\SellerBillTotal[] $totals
 * @property-read int|null $totals_count
 */
class SellerBill extends EloquentModel
{
    protected $table = 'tb_seller_bill';

    protected $dates = [
        'start_date',
        'end_date',
        'settlement_date',
        'confirm_date',
        'create_time',
        'update_time',
        'arrears_date',
    ];

    protected $fillable = [
        'serial_number',
        'seller_id',
        'start_date',
        'end_date',
        'settlement_date',
        'reserve',
        'previous_reserve',
        'total',
        'frozen_total',
        'settlement',
        'settlement_status',
        'actual_settlement',
        'is_settlement',
        'confirm_date',
        'receipt_file_menu_id',
        'seller_account_id',
        'remark',
        'memo',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
        'settle_apply',
        'settle_type',
        'arrears_principal',
        'arrears_date',
    ];

    public function totals()
    {
        return $this->hasMany(SellerBillTotal::class, 'header_id');
    }

    public function frozen()
    {
        return $this->hasMany(SellerBillFrozen::class, 'bill_id');
    }
}
