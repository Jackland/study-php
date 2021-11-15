<?php

namespace App\Models\SellerBill;

use Framework\Model\EloquentModel;

/**
 * App\Models\SellerBill\SellerBillStorage
 *
 * @property int $id 自增主键
 * @property int $bill_id tb_seller_bill的主键ID
 * @property int $seller_id seller_id
 * @property string|null $screenname 店铺名
 * @property int|null $batch_id tb_sys_batch批次表ID
 * @property string|null $batch_number 批次号
 * @property \Illuminate\Support\Carbon $receive_date 入库日期
 * @property string $sku itemCode
 * @property string $length 长
 * @property string $width 宽
 * @property string $height 高
 * @property string $volume 体积
 * @property int $onhand_days 当前在库天数
 * @property string $storage_type 仓租类型
 * @property string $cost_per_day 仓租费/m3 每天
 * @property \Illuminate\Support\Carbon $storage_time 仓租费收费日期
 * @property int $onhand_qty 当天ItemCode在库数
 * @property string $volume_total ItemCode总体积
 * @property string $storage_fee 当天ItemCode仓租费合计
 * @property string|null $memo 备注信息
 * @property string $create_username 创建人
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property string|null $update_username 更新人
 * @property \Illuminate\Support\Carbon|null $update_time 更新时间
 * @property string|null $program_code 版本号
 * @property string|null $apInvoice_num 发票编号，用于财务系统同步
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerBill\SellerBillStorage newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerBill\SellerBillStorage newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerBill\SellerBillStorage query()
 * @mixin \Eloquent
 */
class SellerBillStorage extends EloquentModel
{
    protected $table = 'tb_seller_bill_storage';

    protected $dates = [
        'receive_date',
        'storage_time',
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'bill_id',
        'seller_id',
        'screenname',
        'batch_id',
        'batch_number',
        'receive_date',
        'sku',
        'length',
        'width',
        'height',
        'volume',
        'onhand_days',
        'storage_type',
        'cost_per_day',
        'storage_time',
        'onhand_qty',
        'volume_total',
        'storage_fee',
        'memo',
        'create_username',
        'create_time',
        'update_username',
        'update_time',
        'program_code',
        'apInvoice_num',
    ];
}
