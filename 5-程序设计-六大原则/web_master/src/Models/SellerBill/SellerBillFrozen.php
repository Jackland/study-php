<?php

namespace App\Models\SellerBill;

use Framework\Model\EloquentModel;

/**
 * App\Models\SellerBill\SellerBillFrozen
 *
 * @property int $id 自增主键ID
 * @property int $seller_id sellerId
 * @property int $bill_id 账单主键ID tb_seller_bill.id
 * @property int $bill_detail_id 账单明细主键ID tb_seller_bill_detail.id
 * @property string $frozen_amount_origin 初始冻结金额
 * @property string $frozen_amount 剩余冻结金额
 * @property string $frozen_freight_origin 初始冻结运费
 * @property string $frozen_freight 剩余冻结运费
 * @property string $frozen_service_origin 初始冻结服务费
 * @property string $frozen_service 剩余冻结服务费
 * @property string $frozen_package_origin 初始冻结打包费
 * @property string $frozen_package 剩余冻结打包费
 * @property int $frozen_qty_origin 初始冻结数量
 * @property int $frozen_qty 剩余冻结数量
 * @property \Illuminate\Support\Carbon $frozen_time 冻结时间
 * @property int $frozen_type 冻结类型 tb_seller_bill_order_settle_type.id
 * @property string $create_username 创建人
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property string $update_username 更新人
 * @property \Illuminate\Support\Carbon|null $update_time 更新时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerBill\SellerBillFrozen newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerBill\SellerBillFrozen newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerBill\SellerBillFrozen query()
 * @mixin \Eloquent
 */
class SellerBillFrozen extends EloquentModel
{
    protected $table = 'tb_seller_bill_frozen';

    protected $dates = [
        'frozen_time',
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'seller_id',
        'bill_id',
        'bill_detail_id',
        'frozen_amount_origin',
        'frozen_amount',
        'frozen_freight_origin',
        'frozen_freight',
        'frozen_service_origin',
        'frozen_service',
        'frozen_package_origin',
        'frozen_package',
        'frozen_qty_origin',
        'frozen_qty',
        'frozen_time',
        'frozen_type',
        'create_username',
        'create_time',
        'update_username',
        'update_time',
    ];
}
