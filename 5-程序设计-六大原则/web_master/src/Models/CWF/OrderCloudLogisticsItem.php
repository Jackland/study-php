<?php

namespace App\Models\CWF;

use Framework\Model\EloquentModel;

/**
 * App\Models\CWF\OrderCloudLogisticsItem
 *
 * @property int $id 主键ID 自增主键ID
 * @property int $cloud_logistics_id 云送仓订单头表ID
 * @property int $product_id 产品ID
 * @property string $item_code ItemCode
 * @property string|null $merchant_sku MerchantSku (Amazon Merchant SKU)
 * @property string|null $fn_sku FN_SKU (Amazon FN SKU)
 * @property int|null $seller_id SellerId
 * @property int|null $qty 产品数量
 * @property int $package_label_file_id PackageLabel文件ID
 * @property int $product_label_file_id ProductLabel文件ID
 * @property int|null $team_lift_status 超重标识（有无Team Lift Label文件）
 * @property string|null $memo 备注
 * @property string $create_user_name 创建人
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property string|null $update_user_name 更新人
 * @property \Illuminate\Support\Carbon|null $update_time 更新时间
 * @property string|null $program_code 程序号
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\CWF\OrderCloudLogisticsItem newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\CWF\OrderCloudLogisticsItem newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\CWF\OrderCloudLogisticsItem query()
 * @mixin \Eloquent
 * @property-read \App\Models\CWF\OrderCloudLogistics $orderCloudLogistics
 */
class OrderCloudLogisticsItem extends EloquentModel
{
    protected $table = 'oc_order_cloud_logistics_item';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'cloud_logistics_id',
        'product_id',
        'item_code',
        'merchant_sku',
        'fn_sku',
        'seller_id',
        'qty',
        'package_label_file_id',
        'product_label_file_id',
        'team_lift_status',
        'memo',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
    ];

    public function orderCloudLogistics()
    {
        return $this->belongsTo(OrderCloudLogistics::class, 'cloud_logistics_id');
    }
}
