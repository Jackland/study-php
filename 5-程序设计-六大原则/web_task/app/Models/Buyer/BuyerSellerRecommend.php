<?php

namespace App\Models\Buyer;

use App\Models\Customer\Customer;
use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Buyer\BuyerSellerRecommend
 *
 * @property int $id
 * @property int $seller_id seller Id
 * @property int $buyer_id buyer Id
 * @property int $match_score 匹配度
 * @property int $seller_view_count seller查看次数
 * @property \Illuminate\Support\Carbon $created_at 创建时间
 * @property \Illuminate\Support\Carbon $updated_at 修改时间
 * @property-read \App\Models\Customer\Customer $buyerCustomer
 * @property-read \App\Models\CustomerPartner\CustomerPartnerToCustomer $seller
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Buyer\BuyerSellerRecommend newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Buyer\BuyerSellerRecommend newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Buyer\BuyerSellerRecommend query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Buyer\BuyerSellerRecommend whereBuyerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Buyer\BuyerSellerRecommend whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Buyer\BuyerSellerRecommend whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Buyer\BuyerSellerRecommend whereMatchScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Buyer\BuyerSellerRecommend whereSellerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Buyer\BuyerSellerRecommend whereSellerViewCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Buyer\BuyerSellerRecommend whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class BuyerSellerRecommend extends Model
{
    protected $table = 'oc_buyer_seller_recommend';

    public function seller()
    {
        return $this->hasOne(CustomerPartnerToCustomer::class, 'customer_id', 'seller_id');
    }

    public function buyerCustomer()
    {
        return $this->hasOne(Customer::class, 'customer_id', 'buyer_id');
    }
}
