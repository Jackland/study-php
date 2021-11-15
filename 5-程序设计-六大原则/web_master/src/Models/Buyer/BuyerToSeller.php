<?php

namespace App\Models\Buyer;

use App\Models\Customer\Customer;
use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use App\Models\Message\MsgCustomerExt;
use Framework\Model\EloquentModel;

/**
 * App\Models\Buyer\BuyerToSeller
 *
 * @property int $id 自增主键
 * @property int $buyer_id Buyer ID
 * @property int $seller_id Seller ID
 * @property string|null $account Seller网站登录名
 * @property string|null $pwd Seller网站密码
 * @property int|null $buy_status Buyer是否可以购买
 * @property int|null $price_status Buyer是否可见价格
 * @property int|null $buyer_control_status Buyer控制合作状态
 * @property int|null $seller_control_status Seller控制合作状态
 * @property float|null $discount 折扣率
 * @property int|null $discount_method 折扣方式 默认为0：GIGA Method，1：Self Method
 * @property string $add_time 添加时间
 * @property string $remark 备注
 * @property int $auto_buy_sort 自动购买的店铺优先级排序，数值越大，越优先
 * @property \Illuminate\Support\Carbon $last_transaction_time 上一次交易的时间
 * @property int $number_of_transaction 交易的总次数
 * @property float $money_of_transaction 交易的总金额
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Buyer\BuyerToSeller newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Buyer\BuyerToSeller newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Buyer\BuyerToSeller query()
 * @mixin \Eloquent
 * @property-read \App\Models\Buyer\Buyer $buyer
 * @property-read \App\Models\Customer\Customer $buyerCustomer
 * @property-read \App\Models\CustomerPartner\CustomerPartnerToCustomer $seller
 * @property-read \App\Models\Customer\Customer $sellerCustomer
 * @property bool $is_product_subscribed 是否订阅seller新品上架
 */
class BuyerToSeller extends EloquentModel
{
    protected $table = 'oc_buyer_to_seller';

    protected $dates = [
        'last_transaction_time',
    ];

    protected $fillable = [
        'buyer_id',
        'seller_id',
        'account',
        'pwd',
        'buy_status',
        'price_status',
        'buyer_control_status',
        'seller_control_status',
        'discount',
        'discount_method',
        'add_time',
        'remark',
        'auto_buy_sort',
        'last_transaction_time',
        'number_of_transaction',
        'money_of_transaction',
    ];

    public function buyer()
    {
        return $this->hasOne(Buyer::class, 'buyer_id', 'buyer_id');
    }

    public function buyerCustomer()
    {
        return $this->hasOne(Customer::class, 'customer_id', 'buyer_id');
    }

    public function seller()
    {
        return $this->hasOne(CustomerPartnerToCustomer::class, 'customer_id', 'seller_id');
    }

    public function sellerCustomer()
    {
        return $this->hasOne(Customer::class, 'customer_id', 'seller_id');
    }

    public function sellerMsgExt()
    {
        return $this->hasOne(MsgCustomerExt::class, 'customer_id', 'seller_id');
    }
}
