<?php

namespace App\Models\Buyer;

use App\Models\Customer\Customer;
use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use Framework\Model\EloquentModel;

/**
 * App\Models\Buyer\BuyerSellerRecommendNoInterest
 *
 * @property int $id
 * @property int $seller_id seller Id
 * @property int $buyer_id buyer Id
 * @property int $recommend_id 推荐表的ID
 * @property string $reason 原因
 * @property \Carbon\Carbon $created_at 创建时间
 * @property \Carbon\Carbon $updated_at 修改时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Buyer\BuyerSellerRecommendNoInterest newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Buyer\BuyerSellerRecommendNoInterest newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Buyer\BuyerSellerRecommendNoInterest query()
 * @mixin \Eloquent
 * @property-read \App\Models\Buyer\Buyer $buyer
 * @property-read \App\Models\Customer\Customer $buyerCustomer
 * @property-read \App\Models\Buyer\BuyerSellerRecommend $recommend
 * @property-read \App\Models\CustomerPartner\CustomerPartnerToCustomer $seller
 * @property-read \App\Models\Customer\Customer $sellerCustomer
 */
class BuyerSellerRecommendNoInterest extends EloquentModel
{
    protected $table = 'oc_buyer_seller_recommend_no_interest';
    public $timestamps = true;

    protected $fillable = [
        'seller_id',
        'buyer_id',
        'recommend_id',
        'reason',
    ];

    public function seller()
    {
        return $this->hasOne(CustomerPartnerToCustomer::class, 'seller_id', 'seller_id');
    }

    public function sellerCustomer()
    {
        return $this->hasOne(Customer::class, 'customer_id', 'seller_id');
    }

    public function buyer()
    {
        return $this->hasOne(Buyer::class, 'buyer_id', 'buyer_id');
    }

    public function buyerCustomer()
    {
        return $this->hasOne(Customer::class, 'customer_id', 'buyer_id');
    }

    public function recommend()
    {
        return $this->hasOne(BuyerSellerRecommend::class, 'id', 'recommend_id');
    }
}
