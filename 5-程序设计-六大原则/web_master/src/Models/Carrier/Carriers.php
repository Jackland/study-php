<?php

namespace App\Models\Carrier;

use Framework\Model\EloquentModel;

/**
 * App\Models\Carrier\Carriers
 *
 * @property int $CarrierID
 * @property string $CarrierCode
 * @property string $CarrierName
 * @property int $truck_flag 是否是卡车标志位： 1 卡车 0 非卡车
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Carrier\Carriers newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Carrier\Carriers newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Carrier\Carriers query()
 * @mixin \Eloquent
 */
class Carriers extends EloquentModel
{
    protected $table = 'tb_sys_carriers';
    protected $primaryKey = 'CarrierID';

    protected $dates = [
        
    ];

    protected $fillable = [
        'CarrierCode',
        'CarrierName',
        'truck_flag',
    ];
}
