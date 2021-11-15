<?php

namespace App\Models\StorageFee;

use App\Models\Customer\Customer;
use Illuminate\Database\Eloquent\Model;

/**
 * \App\Models\StorageFee\StorageFee
 *
 * @property int $id
 * @property int $buyer_id buyer
 * @property int $country_id 国家ID
 * @property int $order_id 采购单ID
 * @property int $order_product_id 采购单产品明细的id
 * @property int $product_id 产品ID
 * @property string $product_sku sku
 * @property string $product_size_json 产品尺寸JSON，单位英寸
 * @property float $volume_m 体积，单位立方米，向上保留四位小数
 * @property float $fee_total 当前仓租费用
 * @property float $fee_paid 已付仓租费
 * @property float $fee_unpaid 未付仓租费
 * @property int $days 计费天数
 * @property int $status 状态
 * @property int|null $sales_order_id 绑定销售订单ID
 * @property int|null $sales_order_line_id 销售订单明细id
 * @property int|null $end_type 完结类型
 * 1:销售出库
 * 2:采购RMA
 * @property \Illuminate\Support\Carbon $created_at 创建时间
 * @property \Illuminate\Support\Carbon $updated_at 更新时间
 * @property-read \App\Models\Customer\Customer $customer
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\StorageFee\StorageFee newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\StorageFee\StorageFee newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\StorageFee\StorageFee query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\StorageFee\StorageFee whereBuyerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\StorageFee\StorageFee whereCountryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\StorageFee\StorageFee whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\StorageFee\StorageFee whereDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\StorageFee\StorageFee whereEndType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\StorageFee\StorageFee whereFeePaid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\StorageFee\StorageFee whereFeeTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\StorageFee\StorageFee whereFeeUnpaid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\StorageFee\StorageFee whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\StorageFee\StorageFee whereOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\StorageFee\StorageFee whereOrderProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\StorageFee\StorageFee whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\StorageFee\StorageFee whereProductSizeJson($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\StorageFee\StorageFee whereProductSku($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\StorageFee\StorageFee whereSalesOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\StorageFee\StorageFee whereSalesOrderLineId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\StorageFee\StorageFee whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\StorageFee\StorageFee whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\StorageFee\StorageFee whereVolumeM($value)
 * @mixin \Eloquent
 */
class StorageFee extends Model
{
    protected $table = 'oc_storage_fee';
    public $timestamps = true;

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'buyer_id');
    }
}
