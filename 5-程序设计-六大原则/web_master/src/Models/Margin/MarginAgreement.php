<?php

namespace App\Models\Margin;

use App\Enums\Common\YesNoEnum;
use App\Enums\Margin\MarginAgreementStatus;
use App\Models\Customer\Customer;
use App\Models\Futures\FuturesMarginDelivery;
use App\Models\Product\Product;
use App\Models\SalesOrder\CustomerSalesOrderLine;
use Framework\Model\Eloquent\Builder;
use Framework\Model\EloquentModel;

/**
 * App\Models\Margin\MarginAgreement
 *
 * @property int $id
 * @property string $agreement_id Ymd+6位随机数组成的合同协议ID识别编号
 * @property int $seller_id 卖方seller用户ID
 * @property int $buyer_id 买方buyer用户ID
 * @property int $product_id 商品ID
 * @property int $clauses_id 关联的条款id oc_information.information_id
 * @property float $price 合同单价
 * @property float $payment_ratio 保证金支付比例，20.99%记作20.99
 * @property int $day 合同天数
 * @property int $num 合同数量
 * @property float $money 保证金金额
 * @property bool $status 协议状态，1:Applied,2:Pending,3:Approved,4:Rejected,5:TimeOut
 * @property int $period_of_application tb_bond_template.period_of_application
 * @property string|null $effect_time 协议生效时间
 * @property string|null $expire_time 协议到期时间
 * @property bool $buyer_ignore Buyer ignore忽略待处理状态,0:默认，1:已忽略
 * @property bool|null $termination_request 0初始或拒绝，1：申请
 * @property string|null $termination_request_from seller, buyer  协议终止请求来源
 * @property string|null $memo 备注
 * @property int $create_user 创建者
 * @property string $create_time 创建时间
 * @property int|null $update_user 最新修改者
 * @property string|null $update_time 更新时间
 * @property string|null $program_code 版本号
 * @property float $deposit_per 单个商品的订金价格，已做了舍入计算
 * @property float $rest_price 合同单价减去定金用于排序优化
 * @property int|null $discount 折扣,0-100
 * @property-read \App\Models\Customer\Customer $buyer
 * @property-read \App\Models\Margin\MarginProcess $process 协议进度
 * @property-read \App\Models\Product\Product $product 协议产品
 * @property-read \App\Models\Customer\Customer $seller
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Margin\MarginAgreement newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Margin\MarginAgreement newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Margin\MarginAgreement query()
 * @mixin \Eloquent
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Margin\MarginMessage[] $messages
 * @property-read int|null $messages_count
 * @property bool $is_bid bid产生的协议:1 ,quickView产生的协议:0
 * @property-read \App\Models\Margin\MarginStatus $marginStatus
 * @property bool|null $last_sync_status 与GIGA ONSITE系统最后一次交互时协议状态
 * @property int|null $osj_sync_flag 协议同步标志位：1-成功；2-失败
 * @property string|null $osj_sync_msg 协议同步返回信息
 * @property int|null $defaults_osj_sync_flag 协议违约同步标志位：1-成功；2-失败
 * @property string|null $defaults_osj_msg 协议违约同步返回信息
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Margin\MarginAgreement visible()
 * @property-read \App\Models\Futures\FuturesMarginDelivery $futureDelivery
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Margin\MarginPerformerApply[] $performerApplies
 * @property-read int|null $performer_applies_count
 */
class MarginAgreement extends EloquentModel
{
    const PROGRAM_CODE_V4 = 'v4';

    protected $table = 'tb_sys_margin_agreement';

    protected $fillable = [
        'agreement_id',
        'seller_id',
        'buyer_id',
        'product_id',
        'clauses_id',
        'price',
        'payment_ratio',
        'day',
        'num',
        'money',
        'status',
        'period_of_application',
        'effect_time',
        'expire_time',
        'buyer_ignore',
        'is_bid',
        'termination_request',
        'termination_request_from',
        'memo',
        'create_user',
        'create_time',
        'update_user',
        'update_time',
        'program_code',
        'deposit_per',
        'rest_price',
        'last_sync_status',
        'osj_sync_flag',
        'osj_sync_msg',
        'defaults_osj_sync_flag',
        'defaults_osj_msg',
    ];

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

    public function process()
    {
        return $this->hasOne(MarginProcess::class, 'margin_id');
    }

    public function messages()
    {
        return $this->hasMany(MarginMessage::class, 'margin_agreement_id');
    }

    public function marginStatus()
    {
        return $this->belongsTo(MarginStatus::class, 'status');
    }

    public function performerApplies()
    {
        return $this->hasMany(MarginPerformerApply::class, 'agreement_id', 'id');
    }

    public function futureDelivery()
    {
        return $this->belongsTo(FuturesMarginDelivery::class, 'id', 'margin_agreement_id');
    }

    /**
     * 可见的(在协议列表中)
     * @param Builder $query
     * @return Builder
     */
    public function scopeVisible(Builder $query)
    {
        $alias = $query->getModel()->getAlias();
        $prefix = $alias ? $alias . '.' : '';

        return $query->where(function ($query) use ($prefix) {
            $query->whereNotIn($prefix . 'status', MarginAgreementStatus::getFrontNeedStatus())
                ->orWhere((function ($q) use ($prefix) {
                    $q->whereIn($prefix . 'status', MarginAgreementStatus::getFrontNeedStatus())->where($prefix . 'is_bid', YesNoEnum::YES);
                }));
        });

    }
}
