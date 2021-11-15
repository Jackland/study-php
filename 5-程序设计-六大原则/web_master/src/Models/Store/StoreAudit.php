<?php

namespace App\Models\Store;

use Framework\Model\EloquentModel;

/**
 * App\Models\Store\StoreAudit
 *
 * @property int $id 主键ID
 * @property int $customer_id seller的id
 * @property int $status 1待审核,2审核通过,3审核不通过
 * @property int $is_delete 0未删，1已删
 * @property string|null $remark 备注
 * @property string|null $operator 操作人员
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon|null $update_time 更新时间
 * @property string $store_name 店铺名称
 * @property string $logo_url logo的存储地址
 * @property string $banner_url banner的存储地址
 * @property string|null $description 产品描述
 * @property string|null $return_warranty 退货政策{"return":{"undelivered":{"days":7,"rate":25,"allow_return":1},"delivered":{"before_days":7,"after_days":0,"delivered_checked":0}},"warranty":{"month":3,"conditions":[]}}
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Store\StoreAudit newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Store\StoreAudit newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Store\StoreAudit query()
 * @mixin \Eloquent
 */
class StoreAudit extends EloquentModel
{
    protected $table = 'oc_store_audit';

    public const CREATED_AT = 'create_time';
    public const UPDATED_AT = 'update_time';
    public $timestamps = true;

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'customer_id',
        'status',
        'is_delete',
        'remark',
        'operator',
        'create_time',
        'update_time',
        'store_name',
        'logo_url',
        'banner_url',
        'description',
        'return_warranty',
    ];
}
