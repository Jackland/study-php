<?php

namespace App\Models\Link;

use App\Models\Customer\Customer;
use App\Models\Product\Product;
use App\Models\Product\ProductExts;
use Framework\Model\EloquentModel;

/**
 * App\Models\Link\CustomerPartnerToProduct
 *
 * @property int $id
 * @property int $customer_id
 * @property int $product_id
 * @property string $price
 * @property string $seller_price
 * @property string $currency_code
 * @property int $quantity
 * @property string $pickup_price
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Link\CustomerPartnerToProduct newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Link\CustomerPartnerToProduct newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Link\CustomerPartnerToProduct query()
 * @mixin \Eloquent
 * @property-read \App\Models\Customer\Customer $customer
 * @property-read \App\Models\Product\Product $product
 * @property-read \App\Models\Product\ProductExts $productExt
 */
class CustomerPartnerToProduct extends EloquentModel
{
    protected $table = 'oc_customerpartner_to_product';

    protected $fillable = [
        'customer_id',
        'product_id',
        'price',
        'seller_price',
        'currency_code',
        'quantity',
        'pickup_price',
    ];

    public function customer()
    {
        return $this->hasOne(Customer::class, 'customer_id', 'customer_id');
    }

    public function product()
    {
        return $this->hasOne(Product::class, 'product_id', 'product_id');
    }

    public function productExt()
    {
        return $this->hasOne(ProductExts::class, 'product_id', 'product_id');
    }
}
