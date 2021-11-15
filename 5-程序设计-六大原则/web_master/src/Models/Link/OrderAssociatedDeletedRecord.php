<?php

namespace App\Models\Link;

use Framework\Model\EloquentModel;

/**
 * App\Models\Link\OrderAssociatedDeletedRecord
 *
 * @property int $id tb_sys_order_associated的id
 * @property int|null $sales_order_id 销售订单ID
 * @property int|null $sales_order_line_id 销售订单明细ID
 * @property int|null $order_id 采购订单ID
 * @property int|null $order_product_id 采购订单产品ID
 * @property int|null $qty 采购数量
 * @property int|null $product_id 产品Id
 * @property int|null $seller_id 卖家id
 * @property int|null $buyer_id 买家ID
 * @property int|null $pre_id 预绑定的id
 * @property int|null $image_id 品牌Id
 * @property string|null $Memo 对于该条记录做备注用的
 * @property string|null $CreateUserName 这条记录的创建者
 * @property \Illuminate\Support\Carbon|null $CreateTime 这条记录的创建时间
 * @property string|null $UpdateUserName 这条记录的创建者
 * @property \Illuminate\Support\Carbon|null $UpdateTime 这条记录的更新时间
 * @property \Illuminate\Support\Carbon $created_time 创建时间
 * @property string|null $created_user_name 创建人
 * @property string|null $ProgramCode 程序号
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Link\OrderAssociatedDeletedRecord newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Link\OrderAssociatedDeletedRecord newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Link\OrderAssociatedDeletedRecord query()
 * @mixin \Eloquent
 */
class OrderAssociatedDeletedRecord extends EloquentModel
{
    protected $table = 'tb_sys_order_associated_deleted_record';

    protected $dates = [
        'CreateTime',
        'UpdateTime',
        'created_time',
    ];

    protected $fillable = [
        'sales_order_id',
        'sales_order_line_id',
        'order_id',
        'order_product_id',
        'qty',
        'product_id',
        'seller_id',
        'buyer_id',
        'pre_id',
        'image_id',
        'Memo',
        'CreateUserName',
        'CreateTime',
        'UpdateUserName',
        'UpdateTime',
        'created_time',
        'created_user_name',
        'ProgramCode',
    ];
}
