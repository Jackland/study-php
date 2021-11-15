<?php

namespace App\Models\Futures;

use App\Models\Customer\Customer;
use App\Models\Product\Product;
use Framework\Model\EloquentModel;

/**
 * \App\Models\Futures\FuturesMarginAgreement
 *
 * @property int $id 自增主键
 * @property int $contract_id oc_futures_contract.id合约ID
 * @property string $agreement_no 协议编号
 * @property int $product_id 产品ID
 * @property int $buyer_id BuyerId
 * @property int $seller_id SellerId
 * @property int $num 协议申请数量
 * @property float $unit_price 申请期货单价
 * @property bool $min_expected_storage_days 最小预估入库时间 最小为1
 * @property bool $max_expected_storage_days 最大预估入库时间 最大为90
 * @property float $buyer_payment_ratio buyer支付保证金比例 10.00% 填写 10.00
 * @property float $seller_payment_ratio seller支付保证金比例 10.00% 填写 10.00
 * @property bool $agreement_status 协议状态,1为Applied,2为Pending，3为Approved，4为Rejected，5为Canceled，6为Time Out，7为Sold
 * @property string|null $expected_delivery_date 预计交割日期,当前国别日期
 * @property bool $ignore 是否忽略，0未忽略，1忽略
 * @property bool $is_lock 是否锁定合约数量,0不是，１是
 * @property bool $is_bid 是否是议价协议,0不是，１是
 * @property string|null $comments 说明
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 * @property int discount 折扣
 * @property string $version 版本号
 * @property-read \App\Models\Customer\Customer $buyer
 * @property-read \App\Models\Product\Product $product
 * @property-read \App\Models\Customer\Customer $seller
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Futures\FuturesMarginAgreement newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Futures\FuturesMarginAgreement newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Futures\FuturesMarginAgreement query()
 * @mixin \Eloquent
 * @property int|null $osj_sync_flag 协议同步标志位：1-成功；2-失败
 * @property string|null $osj_sync_msg 协议同步返回信息
 * @property int|null $defaults_osj_sync_flag 协议违约同步标志位：1-成功；2-失败
 * @property string|null $defaults_osj_msg 协议违约同步返回信息
 * @property-read \App\Models\Futures\FuturesMarginDelivery $futuresMarginDelivery
 * @property-read FuturesMarginProcess $process
 */
class FuturesMarginAgreement extends EloquentModel
{
    protected $table = 'oc_futures_margin_agreement';

    public function buyer()
    {
        return $this->belongsTo(Customer::class, 'buyer_id');
    }

    public function seller()
    {
        return $this->belongsTo(Customer::class, 'seller_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function futuresMarginDelivery()
    {
        return $this->hasOne(FuturesMarginDelivery::class, 'agreement_id', 'id');
    }

    public function process()
    {
        return $this->hasOne(FuturesMarginProcess::class,'agreement_id');
    }
}
