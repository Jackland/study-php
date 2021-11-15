<?php

namespace App\Models\StorageFee;

use Framework\Model\EloquentModel;

/**
 * App\Models\StorageFee\StorageFeeMode
 *
 * @property int $id
 * @property int $mode_version 模式版本
 * @property int $country_id 国家ID
 * @property string $storage_fee 单日仓租金额
 * @property string $consume_fee_percent 消费税
 * @property int $fee_max_day 最大在库天数
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\StorageFee\StorageFeeMode newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\StorageFee\StorageFeeMode newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\StorageFee\StorageFeeMode query()
 * @mixin \Eloquent
 */
class StorageFeeMode extends EloquentModel
{
    protected $table = 'oc_storage_fee_mode';

    protected $fillable = [
        'mode_version',
        'country_id',
        'storage_fee',
        'consume_fee_percent',
        'fee_max_day',
    ];
}
