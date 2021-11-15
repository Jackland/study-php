<?php

namespace App\Models\Marketing;

use App\Models\Customer\Customer;
use Framework\Model\EloquentModel;

/**
 * App\Models\Marketing\MarketingDiscountBuyer
 *
 * @property int $id ID
 * @property int $discount_id oc_marketing_discount.id
 * @property int $buyer_id buyerid
 * @property-read \App\Models\Buyer\Buyer $buyer
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\MarketingDiscountBuyer newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\MarketingDiscountBuyer newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Marketing\MarketingDiscountBuyer query()
 * @mixin \Eloquent
 */
class MarketingDiscountBuyer extends EloquentModel
{
    protected $table = 'oc_marketing_discount_buyer';

    protected $dates = [

    ];

    protected $fillable = [
        'discount_id',
        'buyer_id',
    ];

    public function buyer()
    {
        return $this->hasOne(Customer::class, 'customer_id', 'buyer_id');
    }

}
