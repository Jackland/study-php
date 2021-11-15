<?php

namespace App\Models\Rma;

use App\Models\Customer\Customer;
use Illuminate\Database\Eloquent\Model;

/**
 * \App\Models\Rma\YzcRmaOrder
 *
 * @property int $id rma_id 自增主键
 * @property string|null $rma_order_id RMA 订单ID 日期+四位序列号，不足补0
 * @property int $order_id oc_order.order_id
 * @property string|null $from_customer_order_id RMA来自销售订单
 * @property int $seller_id seller_id
 * @property int $buyer_id buyer_id
 * @property int|null $admin_status Admin 更改状态值
 * @property int|null $seller_status Seller 更改状态值
 * @property int|null $cancel_rma 取消RMA
 * @property int|null $solve_rma 解决RMA
 * @property string|null $memo 备注
 * @property string|null $create_user_name 创建者
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property \Illuminate\Support\Carbon|null $update_time 修改时间
 * @property string|null $program_code 程序号
 * @property string|null $processed_date 退返品完成时间
 * @property int|null $order_type 1:销售订单退货2：采购订单退货
 * @property int $is_timeout seller是否超时未处理rma
 * @property-read \App\Models\Customer\Customer $buyer
 * @property-read \App\Models\Customer\Customer $seller
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Rma\YzcRmaOrder newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Rma\YzcRmaOrder newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Rma\YzcRmaOrder query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Rma\YzcRmaOrder whereAdminStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Rma\YzcRmaOrder whereBuyerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Rma\YzcRmaOrder whereCancelRma($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Rma\YzcRmaOrder whereCreateTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Rma\YzcRmaOrder whereCreateUserName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Rma\YzcRmaOrder whereFromCustomerOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Rma\YzcRmaOrder whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Rma\YzcRmaOrder whereIsTimeout($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Rma\YzcRmaOrder whereMemo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Rma\YzcRmaOrder whereOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Rma\YzcRmaOrder whereOrderType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Rma\YzcRmaOrder whereProcessedDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Rma\YzcRmaOrder whereProgramCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Rma\YzcRmaOrder whereRmaOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Rma\YzcRmaOrder whereSellerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Rma\YzcRmaOrder whereSellerStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Rma\YzcRmaOrder whereSolveRma($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Rma\YzcRmaOrder whereUpdateTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Rma\YzcRmaOrder whereUpdateUserName($value)
 * @mixin \Eloquent
 */
class YzcRmaOrder extends Model
{
    public const CREATED_AT = 'create_time';
    public const UPDATED_AT = 'update_time';

    protected $table = 'oc_yzc_rma_order';
    public $timestamps = true;

    public function buyer()
    {
        return $this->belongsTo(Customer::class, 'buyer_id');
    }

    public function seller()
    {
        return $this->belongsTo(Customer::class, 'seller_id');
    }

}
