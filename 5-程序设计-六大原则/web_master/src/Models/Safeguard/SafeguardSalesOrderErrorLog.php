<?php

namespace App\Models\Safeguard;

use App\Enums\Safeguard\SafeguardSalesOrderErrorLogType;
use App\Models\SalesOrder\CustomerSalesOrder;
use Framework\Model\EloquentModel;

/**
 * App\Models\Safeguard\SafeguardSalesOrderErrorLog
 *
 * @property int $id
 * @property int $sales_order_id 销售单id
 * @property int $type_id 类型
 * @property string|null $remark 备注
 * @property-read CustomerSalesOrder $salesOrder 销售订单
 * @property-read string $type_srt 类型文案-附加属性，不存在数据库，根据type_id获取
 * @property \Illuminate\Support\Carbon $create_time
 * @property-read mixed $type_str
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardSalesOrderErrorLog newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardSalesOrderErrorLog newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Safeguard\SafeguardSalesOrderErrorLog query()
 * @mixin \Eloquent
 */
class SafeguardSalesOrderErrorLog extends EloquentModel
{
    protected $table = 'oc_safeguard_sales_order_error_log';

    protected $dates = [
        'create_time',
    ];

    protected $fillable = [
        'sales_order_id',
        'type_id',
        'remark',
        'create_time',
    ];

    protected $appends = ['type_str'];

    public function getTypeStrAttribute()
    {
        if (!isset($this->attributes['type_id'])) {
            return '';
        }
        return SafeguardSalesOrderErrorLogType::getDescription($this->attributes['type_id']);
    }

    public function salesOrder()
    {
        return $this->belongsTo(CustomerSalesOrder::class);
    }
}
