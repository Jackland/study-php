<?php

namespace App\Models\CustomerPartner;

use App\Enums\Common\YesNoEnum;
use App\Models\Customer\CustomerStockBlackList;
use Framework\Model\EloquentModel;

/**
 * App\Models\CustomerPartner\CustomerPartnerToProduct
 *
 * @property int $id
 * @property int $customer_id
 * @property int $product_id
 * @property string $price
 * @property string $seller_price
 * @property string $currency_code
 * @property int $quantity
 * @property string $pickup_price
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToProduct newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToProduct newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\CustomerPartner\CustomerPartnerToProduct query()
 * @mixin \Eloquent
 */
class CustomerPartnerToProduct extends EloquentModel
{
    protected $table = 'oc_customerpartner_to_product';

    protected $dates = [

    ];

    protected $fillable = [
        'customer_id',
        'product_id',
        'price',
        'seller_price',
        'currency_code',
        'quantity',
        'pickup_price',
    ];

    public function stockBlackList()
    {
        return $this->hasOne(CustomerStockBlackList::class, 'customer_id', 'customer_id');
    }
}
