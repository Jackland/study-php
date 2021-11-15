<?php

namespace App\Models\Track;

use App\Models\Product\Product;
use Framework\Model\EloquentModel;

/**
 * App\Models\Track\TrackingFacts
 *
 * @property int $id
 * @property string $sales_order_id 订单orderId
 * @property string $recipient 收货人姓名
 * @property string $address 收货地址
 * @property string $sku 商品编号sku， 卡车类型一个tracking_number多个sku以逗号拼接
 * @property string $carrier 运输方式(物流）
 * @property string $tracking_number 订单运单号
 * @property int $carrier_status 当前订单最新物流状态 1： Label Created 2：Completed Prep 3：出库 4：Picked Up 5：In Transit 6： Delivered  7： Exception
 * @property string $carrier_time 最新状态时间
 * @property int $status 是否有效 0：无效 1：有效
 * @property string|null $remark 备注说明
 * @property string $create_time 创建时间
 * @property string $create_user_name 创建人
 * @property string|null $update_time 更新时间
 * @property string|null $update_user_name 更新人
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Track\TrackingTravelRecord[] $records
 * @property-read int|null $records_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Track\TrackingTravelRecord[] $recordsDesc
 * @property-read int|null $records_desc_count
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Track\TrackingFacts newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Track\TrackingFacts newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Track\TrackingFacts query()
 * @mixin \Eloquent
 * @property string $shipment_id wos侧 发货号
 */
class TrackingFacts extends EloquentModel
{
    protected $table = 'tb_tracking_facts';

    protected $appends = [
        'address',
        'recipient',
    ];

    protected $fillable = [
        'sales_order_id',
        'recipient',
        'address',
        'sku',
        'carrier',
        'tracking_number',
        'carrier_status',
        'carrier_time',
        'status',
        'remark',
        'create_time',
        'create_user_name',
        'update_time',
        'update_user_name',
    ];

    public function records()
    {
        return $this->hasMany(TrackingTravelRecord::class, 'header_id');
    }

    public function recordsDesc()
    {
        return $this->hasMany(TrackingTravelRecord::class)->orderBy('id', 'desc');
    }

    public function getAddressAttribute()
    {
        return app('db-aes')->decrypt($this->attributes['address'] ?? null);
    }

    public function getRecipientAttribute()
    {
        return app('db-aes')->decrypt($this->attributes['recipient'] ?? null);
    }
}
