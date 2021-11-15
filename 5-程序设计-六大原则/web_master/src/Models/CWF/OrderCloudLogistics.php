<?php

namespace App\Models\CWF;

use Framework\Model\EloquentModel;

/**
 * App\Models\CWF\OrderCloudLogistics
 *
 * @property int $id 主键ID 自增主键ID
 * @property int $buyer_id BuyerID
 * @property int $order_id 订单ID
 * @property int $sales_order_id 生成的销售订单头表ID
 * @property bool $service_type 服务类型
 * @property bool $has_dock 收货地址是否有卸货口
 * @property string $recipient 收货人
 * @property string $phone 电话
 * @property string $email 邮箱
 * @property string $address 地址
 * @property string $country 国家
 * @property string $city 城市
 * @property string $state 州省
 * @property string $zip_code 邮编
 * @property string|null $comments 订单备注
 * @property string|null $fba_shipment_code ShipmentCode (Amazon FBA BOL号)
 * @property string|null $fba_reference_code ReferenceCode (Amazon FBA Reference ID)
 * @property string|null $fba_po_code PO_Code (Amazon FBA PO ID)
 * @property string|null $fba_warehouse_code Amazon FBA Warehouse Code
 * @property string|null $fba_amazon_reference_number Amazon FBA Reference Number
 * @property int|null $team_lift_file_id 超重标志Label文件ID
 * @property bool $sync_file_status FBA 同步Label文件标志状态
 * @property bool $cwf_status 云送仓订单状态
 * @property string|null $memo 备注
 * @property string $create_user_name 创建人
 * @property string $create_time 创建时间
 * @property string|null $update_user_name 更新人
 * @property string|null $update_time 更新时间
 * @property string|null $program_code 程序号
 * @property int|null $pallet_label_file_id 托盘Label文件ID
 * @property bool $sync_status 同步至云送仓系统状态
 * @property-read OrderCloudLogisticsTracking $trackingNumbers tracking list
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\CWF\OrderCloudLogistics newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\CWF\OrderCloudLogistics newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\CWF\OrderCloudLogistics query()
 * @mixin \Eloquent
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\CWF\OrderCloudLogisticsItem[] $items
 * @property-read int|null $items_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\CWF\CloudWholesaleFulfillmentFileExplain[] $fileExplains
 * @property-read int|null $file_explains_count
 */
class OrderCloudLogistics extends EloquentModel
{
    protected $table = 'oc_order_cloud_logistics';

    /**
     * tracking list
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function trackingNumbers()
    {
        return $this->hasMany(OrderCloudLogisticsTracking::class, 'cloud_logistics_id', 'id');
    }

    public function getRecipientAttribute()
    {
        return app('db-aes')->decrypt($this->attributes['recipient'] ?? null);
    }

    public function getEmailAttribute()
    {
        return app('db-aes')->decrypt($this->attributes['email'] ?? null);
    }

    public function getPhoneAttribute()
    {
        return app('db-aes')->decrypt($this->attributes['phone'] ?? null);
    }

    public function getAddressAttribute()
    {
        return app('db-aes')->decrypt($this->attributes['address'] ?? null);
    }

    public function getCityAttribute()
    {
        return app('db-aes')->decrypt($this->attributes['city'] ?? null);
    }

    public function items()
    {
        return $this->hasMany(OrderCloudLogisticsItem::class, 'cloud_logistics_id');
    }

    public function fileExplains()
    {
        return $this->hasMany(CloudWholesaleFulfillmentFileExplain::class, 'cwf_order_id');
    }
}
