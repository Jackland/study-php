<?php

namespace App\Models\SellerBill;

use Framework\Model\EloquentModel;

/**
 * App\Models\Message\SellerBillFrozenRelease
 *
 * @property int $id 自增主键ID
 * @property int $seller_id sellerId
 * @property int $bill_id 账单主键ID tb_seller_bill.id
 * @property int $bill_detail_id 账单明细主键ID tb_seller_bill_detail.id
 * @property int $frozen_detail_id 账单原始冻结明细记录ID
 * @property string $release_amount 解冻金额
 * @property string $release_freight 解冻运费
 * @property string $release_service 解冻服务费
 * @property string $release_package 解冻打包费
 * @property int $release_qty 解冻数量
 * @property int $release_type 解冻类型
 * @property \Illuminate\Support\Carbon $release_time 解冻时间
 * @property string $create_username 创建人
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property string $update_username 更新人
 * @property \Illuminate\Support\Carbon|null $update_time 更新时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\SellerBillFrozenRelease newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\SellerBillFrozenRelease newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\SellerBillFrozenRelease query()
 * @mixin \Eloquent
 */
class SellerBillFrozenRelease extends EloquentModel
{
    protected $table = 'tb_seller_bill_frozen_release';

    protected $dates = [
        'release_time',
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'seller_id',
        'bill_id',
        'bill_detail_id',
        'frozen_detail_id',
        'release_amount',
        'release_freight',
        'release_service',
        'release_package',
        'release_qty',
        'release_type',
        'release_time',
        'create_username',
        'create_time',
        'update_username',
        'update_time',
    ];

    public function orderSettlerType()
    {
        return $this->belongsTo(SellerBillOrderSettleType::class, 'release_type');
    }
}
