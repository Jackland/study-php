<?php

namespace App\Models\CWF;

use Framework\Model\EloquentModel;

/**
 * App\Models\CWF\CloudWholesaleFulfillmentMatchStock
 *
 * @property int $id 自增主键
 * @property int $cwf_line_id 云送仓明细id
 * @property int|null $product_id 产品id
 * @property int|null $quantity 购买总数
 * @property string|null $sku 产品sku
 * @property int|null $match_qty 匹配数量
 * @property string|null $run_id 云送仓导单识别字段
 * @property int|null $seller_id 店铺seller id
 * @property int|null $transaction_type 交易类型
 * @property int|null $agreement_id 协议id
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\CWF\CloudWholesaleFulfillmentMatchStock newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\CWF\CloudWholesaleFulfillmentMatchStock newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\CWF\CloudWholesaleFulfillmentMatchStock query()
 * @mixin \Eloquent
 * @property int $cwf_file_upload_id 云送仓上传批次id
 */
class CloudWholesaleFulfillmentMatchStock extends EloquentModel
{
    protected $table = 'tb_cloud_wholesale_fulfillment_match_stock';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'cwf_line_id',
        'product_id',
        'quantity',
        'sku',
        'match_qty',
        'run_id',
        'seller_id',
        'transaction_type',
        'agreement_id',
        'create_time',
        'update_time',
    ];
}
