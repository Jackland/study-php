<?php

namespace App\Models\StorageFee;

use App\Models\FeeOrder\FeeOrder;
use Framework\Model\EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\StorageFee\StorageFeeDetail
 *
 * @property int $id
 * @property int $storage_fee_id oc_storage_fee 主键id
 * @property int $fee_mode_id 计费方式ID
 * @property int $fee_mode_version 计费方式的版本
 * @property string $fee_date 费用日期，为对应国别时区的日期
 * @property int $day 在库天数，按照对应国别时区计算的天数
 * @property string $fee_today 当日费用
 * @property \Carbon\Carbon $created_at
 * @property-read \App\Models\StorageFee\StorageFeeMode $feeMode
 * @property-read \App\Models\StorageFee\StorageFee $storageFee
 * @property-read \App\Models\FeeOrder\FeeOrder $feeOrder
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\StorageFee\StorageFeeDetail newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\StorageFee\StorageFeeDetail newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\StorageFee\StorageFeeDetail query()
 * @mixin \Eloquent
 */
class StorageFeeDetail extends EloquentModel
{
    protected $table = 'oc_storage_fee_detail';

    protected $fillable = [
        'storage_fee_id',
        'fee_mode_id',
        'fee_mode_version',
        'fee_date',
        'day',
        'fee_today',
    ];

    /**
     * @return BelongsTo
     */
    public function storageFee()
    {
        return $this->belongsTo(StorageFee::class);
    }

    /**
     * @return BelongsTo
     */
    public function feeMode()
    {
        return $this->belongsTo(StorageFeeMode::class);
    }

    public function feeOrder()
    {
        return $this->belongsTo(FeeOrder::class, 'fee_order_id');
    }
}
