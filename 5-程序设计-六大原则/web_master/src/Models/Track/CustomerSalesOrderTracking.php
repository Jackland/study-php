<?php

namespace App\Models\Track;

use Framework\Model\EloquentModel;
use App\Models\Carrier\Carriers;

/**
 * App\Models\Track\CustomerSalesOrderTracking
 *
 * @property int $Id 自增主键
 * @property int $SourceFreightId 运费表Id(预留，值暂为1)
 * @property string $SalesOrderId 云资产平台订单ID(Order.YzcOrderId)
 * @property int $SalerOrderLineId 订单表明细Id(Order_line.id)
 * @property string $ShipmentId ShipmentId发货单号(OMD侧发货号，tblShipments)
 * @property string $ShipSku 发货Sku
 * @property int|null $ShipSkuId 商品productId
 * @property int $ShipQty 发货Sku数量(OMD侧每单发货的数量)
 * @property int $LogisticeId 物流公司Id
 * @property string $ServiceLevelId 物流服务Id
 * @property int $CWhId 发货仓库Id
 * @property string $TrackingNumber 运单号(OMD侧配送单号，tblTracking)
 * @property \Illuminate\Support\Carbon $ShipDeliveryDate 发货时间
 * @property int $status 运单号状态码。0:失效运单号; 1:有效运单号
 * @property string|null $Memo 备注
 * @property string|null $create_user_name 创建者
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $update_user_name 更新者
 * @property \Illuminate\Support\Carbon|null $update_time 更新时间
 * @property string|null $ProgramCode 程序号
 * @property string|null $parent_sku combo品的父sku
 * @property string|null $delivery_id
 * @property int|null $omd_sync_flag gigaonsite运单号是否同步omd,0:未同步，1:已同步
 * @property-read \App\Models\Carrier\Carriers $carrier
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Track\CustomerSalesOrderTracking newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Track\CustomerSalesOrderTracking newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Track\CustomerSalesOrderTracking query()
 * @mixin \Eloquent
 */
class CustomerSalesOrderTracking extends EloquentModel
{
    protected $table = 'tb_sys_customer_sales_order_tracking';
    protected $primaryKey = 'Id';

    protected $dates = [
        'ShipDeliveryDate',
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'SourceFreightId',
        'SalesOrderId',
        'SalerOrderLineId',
        'ShipmentId',
        'ShipSku',
        'ShipSkuId',
        'ShipQty',
        'LogisticeId',
        'ServiceLevelId',
        'CWhId',
        'TrackingNumber',
        'ShipDeliveryDate',
        'status',
        'Memo',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'ProgramCode',
        'parent_sku',
        'delivery_id',
    ];

    public function carrier()
    {
        return $this->belongsTo(Carriers::class, 'LogisticeId','CarrierID');
    }
}
