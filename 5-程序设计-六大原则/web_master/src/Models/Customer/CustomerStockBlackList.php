<?php

namespace App\Models\Customer;

use App\Models\CustomerPartner\CustomerPartnerToProduct;
use Framework\Model\EloquentModel;

/**
 * App\Models\Customer\CustomerStockBlackList
 *
 * @property int $id 主键ID
 * @property int $customer_id 用户ID
 * @property int $country_id 国籍ID
 * @property int|null $status 0:黑名单无效，1：黑名单有效
 * @property \Illuminate\Support\Carbon $created_time 创建时间
 * @property \Illuminate\Support\Carbon $updated_time 更新时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Customer\CustomerStockBlackList newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Customer\CustomerStockBlackList newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Customer\CustomerStockBlackList query()
 * @mixin \Eloquent
 */
class CustomerStockBlackList extends EloquentModel
{
    protected $table = 'tb_sys_customer_stock_blacklist';

    protected $dates = [
        'created_time',
        'updated_time',
    ];

    protected $fillable = [
        'customer_id',
        'country_id',
        'status',
        'created_time',
        'updated_time',
    ];

    public function customerpartnerToProduct()
    {
        return $this->hasOne(CustomerPartnerToProduct::class, 'customer_id', 'customer_id');
    }
}
