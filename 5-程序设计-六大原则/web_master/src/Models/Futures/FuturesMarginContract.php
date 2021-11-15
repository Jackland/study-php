<?php

namespace App\Models\Futures;

use Framework\Model\EloquentModel;

/**
 * App\Models\Futures\FuturesMarginContract
 *
 * @property int $id 自增主键
 * @property string $contract_no 合约编号
 * @property int $seller_id SellerId
 * @property int $product_id 产品ID
 * @property string $payment_ratio 支付保证金比例 10.00% 填写 10.00
 * @property \Illuminate\Support\Carbon|null $delivery_date seller合约交付日期,当前国别日期
 * @property int $num 合约数量
 * @property int $min_num 最低购买数量,最小协议数量
 * @property int $purchased_num 已完成的数量
 * @property int $delivery_type 合约的交割方式 （1.支付期货协议尾款交割；2.转现货保证金进行交割；3.转现货保证金和支付尾款混合交割模式）
 * @property string $margin_unit_price 转现货单价
 * @property string $last_unit_price 期货尾款单价
 * @property string $available_balance seller可用的保证金余额
 * @property int $is_bid 是否可以议价,0不可以，１可以
 * @property int $status 合约状态，１售卖中，２禁用，３已售卖完成，４合约终止
 * @property int $is_deleted 是否删除
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @property float $collateral_balance 抵押物金额
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Futures\FuturesMarginContract newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Futures\FuturesMarginContract newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Futures\FuturesMarginContract query()
 * @mixin \Eloquent
 */
class FuturesMarginContract extends EloquentModel
{
    protected $table = 'oc_futures_contract';

    protected $dates = [
        'delivery_date',
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'contract_no',
        'seller_id',
        'product_id',
        'payment_ratio',
        'delivery_date',
        'num',
        'min_num',
        'purchased_num',
        'delivery_type',
        'margin_unit_price',
        'last_unit_price',
        'available_balance',
        'is_bid',
        'status',
        'is_deleted',
        'create_time',
        'update_time',
    ];
}
