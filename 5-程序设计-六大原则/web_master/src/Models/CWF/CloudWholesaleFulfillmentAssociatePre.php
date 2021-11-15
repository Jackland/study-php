<?php

namespace App\Models\CWF;

use Framework\Model\EloquentModel;

/**
 * App\Models\CWF\CloudWholesaleFulfillmentAssociatePre
 *
 * @property int $id 自增主键
 * @property int|null $cwf_match_stock_id 库存匹配id
 * @property string|null $sales_order_string 销售单
 * @property int|null $buyer_id buyer_id
 * @property string|null $sku 销售单sku
 * @property int|null $quantity 匹配采购单采购数量
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\CWF\CloudWholesaleFulfillmentAssociatePre newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\CWF\CloudWholesaleFulfillmentAssociatePre newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\CWF\CloudWholesaleFulfillmentAssociatePre query()
 * @mixin \Eloquent
 * @property int|null $file_explain_id 上传文件明细id
 * @property int|null $cwf_file_upload_id tb_cloud_wholesale_fulfillment_file_upload对应的主键id
 * @property int|null $match_qty 匹配库存需要采购数量
 * @property-read \App\Models\CWF\CloudWholesaleFulfillmentFileExplain|null $fileExplain
 * @property-read \App\Models\CWF\CloudWholesaleFulfillmentMatchStock|null $matchStock
 */
class CloudWholesaleFulfillmentAssociatePre extends EloquentModel
{
    protected $table = 'tb_cloud_wholesale_fulfillment_associate_pre';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'cwf_match_stock_id',
        'file_explain_id',
        'cwf_file_upload_id',
        'buyer_id',
        'sku',
        'quantity',
        'create_time',
        'update_time',
    ];

    public function matchStock()
    {
        return $this->belongsTo(CloudWholesaleFulfillmentMatchStock::class, 'cwf_match_stock_id');
    }

    public function fileExplain()
    {
        return $this->belongsTo(CloudWholesaleFulfillmentFileExplain::class, 'file_explain_id');
    }


}
