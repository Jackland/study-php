<?php

namespace App\Models\Track;

use Framework\Model\EloquentModel;

/**
 * App\Models\Track\TrackingTravelRecord
 *
 * @property int $id
 * @property int $header_id 物流信息主表 tb_tracking_facts主键id
 * @property int $carrier_status 当前商品最新物流状态 1： Label Created 2：Completed Prep 3：出库 4：Picked Up 5：In Transit 6： Delivered  7： Exception
 * @property string $carrier_time 最新状态时间
 * @property int $status 是否有效 0：无效 1：有效
 * @property string $create_time 创建时间
 * @property string $create_user_name 创建人
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Track\TrackingTravelRecord newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Track\TrackingTravelRecord newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Track\TrackingTravelRecord query()
 * @mixin \Eloquent
 * @property string $country 地址-国家
 * @property string $city 地址-城市
 * @property string $state 地址-州
 * @property string $event_description 物流公司节点描述
 * @property string $event_code 物流公司节点code
 * @property bool|null $from_type 同步路径 0：OMD 1:GIGA
 */
class TrackingTravelRecord extends EloquentModel
{
    protected $table = 'tb_tracking_travel_record';

    protected $fillable = [
        'header_id',
        'carrier_status',
        'carrier_time',
        'status',
        'create_time',
        'create_user_name',
    ];

}
