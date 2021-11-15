<?php

namespace App\Models\CWF;

use Framework\Model\EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * \App\Models\CWF\CloudWholesaleFulfillmentFileExplain
 *
 * @property int $id
 * @property int $cwf_file_upload_id tb_cloud_wholesale_fulfillment_file_upload对应的主键id
 * @property string|null $sales_platform 销售平台
 * @property string|null $order_date 订单日期
 * @property string $b2b_item_code b2b对应的sku
 * @property int $ship_to_qty 数量
 * @property string $ship_to_name 名称
 * @property string $ship_to_email 邮箱
 * @property string $ship_to_phone 号码
 * @property string $ship_to_postal_code 邮编
 * @property string $ship_to_address_detail 地址
 * @property string $ship_to_city 城市
 * @property string $ship_to_state 州/区
 * @property string $ship_to_country 国家
 * @property bool $loading_dock_provided
 * @property string|null $order_comments 评价
 * @property string|null $ship_to_attachment_url 附件地址
 * @property int $flag_id 用于组合sku
 * @property int|null $cwf_order_id
 * @property int|null $order_id 采购单id
 * @property int $row_index excel第几行
 * @property-read \App\Models\CWF\CloudWholesaleFulfillmentFileUpload $cwfFileUpload
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\CWF\CloudWholesaleFulfillmentFileExplain newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\CWF\CloudWholesaleFulfillmentFileExplain newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\CWF\CloudWholesaleFulfillmentFileExplain query()
 * @mixin \Eloquent
 */
class CloudWholesaleFulfillmentFileExplain extends EloquentModel
{
    protected $table = 'tb_cloud_wholesale_fulfillment_file_explain';

    public function cwfFileUpload(): BelongsTo
    {
        return $this->belongsTo(CloudWholesaleFulfillmentFileUpload::class, 'cwf_file_upload_id');
    }
}
