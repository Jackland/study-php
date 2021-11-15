<?php

namespace App\Models\SellerBill;

use Framework\Model\EloquentModel;

/**
 * App\Models\SellerBill\SellerBillInterestDetail
 *
 * @property int $id 自增主键
 * @property int $bill_id tb_seller_bill的主键ID
 * @property int $source_bill_id 利息欠款来源的tb_seller_bill的主键ID
 * @property int $seller_id seller_id
 * @property string $arrears_principal 供应链利息的本金
 * @property int $arrears_days 欠款持续天数
 * @property string $arrears_interest 当天供应链利息
 * @property string|null $memo 备注信息
 * @property string $create_username 创建人
 * @property \Illuminate\Support\Carbon $create_time 本条利息对应的那天日期
 * @property string|null $update_username 更新人
 * @property \Illuminate\Support\Carbon|null $update_time 更新时间
 * @property string|null $program_code 版本号
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerBill\SellerBillInterestDetail newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerBill\SellerBillInterestDetail newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerBill\SellerBillInterestDetail query()
 * @mixin \Eloquent
 */
class SellerBillInterestDetail extends EloquentModel
{
    protected $table = 'tb_seller_bill_interest_detail';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'bill_id',
        'source_bill_id',
        'seller_id',
        'arrears_principal',
        'arrears_days',
        'arrears_interest',
        'memo',
        'create_username',
        'create_time',
        'update_username',
        'update_time',
        'program_code',
    ];
}
