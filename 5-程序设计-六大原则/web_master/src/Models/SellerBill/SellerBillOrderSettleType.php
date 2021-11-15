<?php

namespace App\Models\SellerBill;

use Framework\Model\EloquentModel;

/**
 * App\Models\SellerBill\SellerBillOrderSettleType
 *
 * @property int $id 订单分类的交易结算及解冻类型主键ID
 * @property string $cn_name 中文名称
 * @property string $en_name 英文名称
 * @property string $create_username 创建人
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerBill\SellerBillOrderSettleType newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerBill\SellerBillOrderSettleType newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerBill\SellerBillOrderSettleType query()
 * @mixin \Eloquent
 */
class SellerBillOrderSettleType extends EloquentModel
{
    protected $table = 'tb_seller_bill_order_settle_type';

    protected $dates = [
        'create_time',
    ];

    protected $fillable = [
        'cn_name',
        'en_name',
        'create_username',
        'create_time',
    ];
}
