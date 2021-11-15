<?php

namespace App\Models\Warehouse;

use Framework\Model\EloquentModel;

/**
 * App\Models\Warehouse\SellerDeliveryLine
 *
 * @property int $Id
 * @property int|null $order_id 采购订单Id
 * @property int|null $order_product_id 采购订单明细Id
 * @property int|null $product_id 产品id
 * @property int|null $batch_id seller批次库存Id
 * @property int|null $qty 出库数量
 * @property string|null $warehouse 仓库
 * @property int|null $seller_id 卖家Id
 * @property int|null $buyer_id 买家Id
 * @property string|null $Memo 对于该条记录做备注用的
 * @property string|null $CreateUserName 这条记录的创建者
 * @property \Illuminate\Support\Carbon|null $CreateTime 这条记录的创建时间
 * @property string|null $UpdateUserName 这条记录的创建者
 * @property \Illuminate\Support\Carbon|null $UpdateTime 这条记录的更新时间
 * @property string|null $ProgramCode 程序号
 * @property int|null $type 1:采购订单出库2：退返品出库 3:调货出库 4:异常情况出库(盘亏出库)5: 库存下调（orderId 库存调整头表的主键ID order_product_id 库存调整明细表） 6：系统盘亏（orderId 库存调整头表的主键ID order_product_id 库存调整明细表）
 * @property int|null $rma_id 退返品Id
 * @property int|null $rma_product_id rma_product_id
 * @property int|null $withhold 0:预扣库存1:实际库存
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Warehouse\SellerDeliveryLine newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Warehouse\SellerDeliveryLine newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Warehouse\SellerDeliveryLine query()
 * @mixin \Eloquent
 */
class SellerDeliveryLine extends EloquentModel
{
    protected $table = 'tb_sys_seller_delivery_line';
    protected $primaryKey = 'Id';

    protected $dates = [
        'CreateTime',
        'UpdateTime',
    ];

    protected $fillable = [
        'order_id',
        'order_product_id',
        'product_id',
        'batch_id',
        'qty',
        'warehouse',
        'seller_id',
        'buyer_id',
        'Memo',
        'CreateUserName',
        'CreateTime',
        'UpdateUserName',
        'UpdateTime',
        'ProgramCode',
        'type',
        'rma_id',
        'rma_product_id',
        'withhold',
    ];
}
