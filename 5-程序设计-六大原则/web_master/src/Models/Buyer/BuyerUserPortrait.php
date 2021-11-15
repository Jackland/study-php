<?php

namespace App\Models\Buyer;

use Framework\Model\EloquentModel;

/**
 * App\Models\Buyer\BuyerUserPortrait
 *
 * @property int $id 主键自增
 * @property int $buyer_id BuyerId
 * @property int|null $monthly_sales_count 近30天销售笔数
 * @property string|null $total_amount_platform 平台总成交的金额
 * @property string|null $total_amount_returned 退货总金额
 * @property string|null $total_amount_refund 返金总金额
 * @property string|null $return_rate_value Return Rate(退返品率数值)
 * @property int $return_rate Return Rate(退返品率) (0:N/A,1：高，2：中，3：低)
 * @property int|null $order_count_platform 平台总成交笔数
 * @property int|null $order_count_rebate 返点协议参加笔数
 * @property string|null $total_amount_margin 保证金协议达成总金额\
 * @property float|null $complex_complete_rate_value 复杂交易参与度(数值)
 * @property int|null $complex_complete_rate 复杂交易参与度 (0:N/A,1：高，2：中，3：低)
 * @property \Illuminate\Support\Carbon|null $first_order_date 首单成交日期
 * @property \Illuminate\Support\Carbon $registration_date 用户注册日期
 * @property string|null $memo 备注
 * @property string|null $create_user_name 创建者
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property \Illuminate\Support\Carbon|null $update_time 修改时间
 * @property string|null $program_code 程序号
 * @property int|null $main_category_id 主营品类ID (取Buyer complete采购单中产品数量最高的分类,Furniture分类取到二级,其他分类取到一级)
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Buyer\BuyerUserPortrait newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Buyer\BuyerUserPortrait newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Buyer\BuyerUserPortrait query()
 * @mixin \Eloquent
 */
class BuyerUserPortrait extends EloquentModel
{
    protected $table = 'oc_buyer_user_portrait';

    protected $dates = [
        'first_order_date',
        'registration_date',
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'buyer_id',
        'monthly_sales_count',
        'total_amount_platform',
        'total_amount_returned',
        'total_amount_refund',
        'return_rate_value',
        'return_rate',
        'order_count_platform',
        'order_count_rebate',
        'total_amount_margin',
        'complex_complete_rate_value',
        'complex_complete_rate',
        'first_order_date',
        'registration_date',
        'memo',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
        'main_category_id',
    ];
}
