<?php

namespace App\Models\Platform;

use Eloquent;
use Framework\Model\Eloquent\Builder;
use Framework\Model\EloquentModel;
use Illuminate\Support\Carbon;

/**
 * App\Models\Mapping\MappingSku
 *
 * @property int $id
 * @property int $customer_id
 * @property string|null $store_id 销售平台店铺id
 * @property int $platform_id oc_platform表主键
 * @property string $platform_sku 销售平台SKU
 * @property string|null $platform_sku_store platform_sku来自的店铺名称
 * @property string $sku oc_product表sku
 * @property int $product_id
 * @property int $status 1启用
 * @property Carbon|null $date_add
 * @property Carbon|null $date_modified
 * @property string|null $asin Amazon ASIN
 * @property int|null $sales_id 当前销售人员id(tb_sys_user表角色为销售经理的用户id)
 * @property Carbon|null $effective_time 当前生效时间
 * @property int|null $pre_sales_id 预选销售人员id
 * @property Carbon|null $pre_effective_time 预生效时间
 * @property string|null $create_user_name 创建人
 * @property string|null $update_user_name 更新人
 * @method static Builder|PlatformMappingSku newModelQuery()
 * @method static Builder|PlatformMappingSku newQuery()
 * @method static Builder|PlatformMappingSku query()
 * @mixin Eloquent
 */
class PlatformMappingSku extends EloquentModel
{
    protected $table = 'oc_mapping_sku';

    protected $dates = [
        'date_add',
        'date_modified',
        'effective_time',
        'pre_effective_time',
    ];

    protected $fillable = [
        'customer_id',
        'store_id',
        'platform_id',
        'platform_sku',
        'platform_sku_store',
        'sku',
        'product_id',
        'status',
        'date_add',
        'date_modified',
        'asin',
        'sales_id',
        'effective_time',
        'pre_sales_id',
        'pre_effective_time',
        'create_user_name',
        'update_user_name',
    ];
}
