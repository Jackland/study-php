<?php

namespace App\Models\Buyer;

use App\Models\Customer\Customer;
use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use Framework\Model\EloquentModel;

/**
 * App\Models\Buyer\BuyerSellerRecommend
 *
 * @property int $id
 * @property int $seller_id seller Id
 * @property int $buyer_id buyer Id
 * @property int $match_score 匹配度
 * @property int $seller_view_count seller查看次数
 * @property \Carbon\Carbon $created_at 创建时间
 * @property \Carbon\Carbon $updated_at 修改时间
 * @property-read \App\Models\Buyer\Buyer $buyer
 * @property-read \App\Models\Customer\Customer $buyerCustomer
 * @property-read \App\Models\CustomerPartner\CustomerPartnerToCustomer $seller
 * @property-read \App\Models\Customer\Customer $sellerCustomer
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Buyer\BuyerSellerRecommend newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Buyer\BuyerSellerRecommend newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Buyer\BuyerSellerRecommend query()
 * @mixin \Eloquent
 */
class BuyerSellerRecommend extends EloquentModel
{
    protected $table = 'oc_buyer_seller_recommend';
    public $timestamps = true;

    protected $fillable = [
        'seller_id',
        'buyer_id',
        'match_score',
        'seller_view_count',
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
}
