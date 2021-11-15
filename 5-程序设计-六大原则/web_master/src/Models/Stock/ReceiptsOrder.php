<?php

namespace App\Models\Stock;

use App\Models\Warehouse\ReceiptsOrderShippingOrderBook;
use App\Models\Warehouse\Receive;
use App\Models\Warehouse\SellerReceive;
use Framework\Model\EloquentModel;

/**
 * App\Models\ReceiptsOrder
 *
 * @property int $receive_order_id 入库单ID（主键自增）
 * @property string $receive_number 入库单号
 * @property int|null $shipping_way 海运头程：1 非自发 2 客户自发
 * @property \Illuminate\Support\Carbon|null $apply_date 申请日期
 * @property \Illuminate\Support\Carbon|null $etd_date_start 预计发船起始时间
 * @property \Illuminate\Support\Carbon|null $etd_date_end 预计发船截止时间
 * @property string|null $shipping_company 船公司
 * @property \Illuminate\Support\Carbon|null $expected_shipping_date_start 期望船期起始时间
 * @property \Illuminate\Support\Carbon|null $expected_shipping_date_end 期望船期截止时间
 * @property \Illuminate\Support\Carbon|null $expected_arrival_date_start 预计到库时间起始时间
 * @property \Illuminate\Support\Carbon|null $expected_arrival_date_end 预计到库时间截止时间
 * @property \Illuminate\Support\Carbon|null $etd_date ETD-预计发船日
 * @property \Illuminate\Support\Carbon|null $atd_date ATD-实际发船日
 * @property \Illuminate\Support\Carbon|null $eta_date ETA-预计到港日
 * @property \Illuminate\Support\Carbon|null $expected_date 预计入库日期
 * @property \Illuminate\Support\Carbon|null $receive_date 收货日期
 * @property string|null $container_code 集装箱号
 * @property string|null $container_size 集装箱尺寸
 * @property int $last_status 变更前状态：已废弃--0;待提交申请--1;已申请--2;已分仓--3;已订舱--5;待收货--6;已收货--7;已取消--9
 * @property int $status 状态：已废弃--0;待提交申请--1;已申请--2;已分仓--3;已订舱--5;待收货--6;已收货--7;已取消--9
 * @property string|null $port_start 起运港
 * @property int|null $currency 1 日元,2 英镑,3 美元,4 人民币 5 欧元
 * @property string|null $warehouse 仓库
 * @property string|null $ocean_freight 海运费(垫付)
 * @property string|null $port_of_destination_fee 目的港费(垫付)
 * @property string|null $tariff 关税(垫付)
 * @property string|null $company_code 货代公司
 * @property string|null $rate_number 税单号
 * @property string|null $unloading_charges 卸货费
 * @property string|null $additional_unloading_charges 额外卸货费
 * @property string|null $other_expenses 其他费用
 * @property int|null $customer_id seller id
 * @property int|null $sku_count SKU 数量
 * @property string|null $volume_sum 产品体积总和【预留字段】
 * @property string|null $remark 备注
 * @property string|null $create_user_name 创建者
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property \Illuminate\Support\Carbon|null $update_time 修改时间
 * @property string|null $program_code 程序号
 * @property int|null $package_flag 是否打托 0,否，1是
 * @property string|null $package_fee 打托费
 * @property string|null $run_id RunId
 * @property string|null $source_receive_id 来源系统入库单头表主键ID
 * @property int|null $warehousing_mode 入库方式 1:运营手工录入
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Stock\ReceiptsOrder newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Stock\ReceiptsOrder newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Stock\ReceiptsOrder query()
 * @mixin \Eloquent
 * @property string|null $shelf_time_of_warehouse 仓库上架时间
 * @property string|null $area_warehouse 收货区域仓库
 * @property bool|null $ocean_freight_flag 海运费计算标志 0：未计算 1：已计算
 * @property bool|null $tariff_flag 关税计算标志 0：未计算 1：已计算
 * @property bool|null $unloading_charges_flag 卸货费计算标志 0：未计算 1：已计算
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Stock\ReceiptsOrderDetail[] $receiptDetails
 * @property-read int|null $receipt_details_count
 */
class ReceiptsOrder extends EloquentModel
{
    protected $table = 'tb_sys_receipts_order';
    protected $primaryKey = 'receive_order_id';
    public $timestamps = false;
    const CREATED_AT = 'create_time';
    const UPDATED_AT = 'update_time';

    protected $dates = [
        'apply_date',
        'etd_date_start',
        'etd_date_end',
        'expected_shipping_date_start',
        'expected_shipping_date_end',
        'expected_arrival_date_start',
        'expected_arrival_date_end',
        'etd_date',
        'atd_date',
        'eta_date',
        'expected_date',
        'receive_date',
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'receive_number',
        'shipping_way',
        'apply_date',
        'etd_date_start',
        'etd_date_end',
        'shipping_company',
        'expected_shipping_date_start',
        'expected_shipping_date_end',
        'expected_arrival_date_start',
        'expected_arrival_date_end',
        'etd_date',
        'atd_date',
        'eta_date',
        'expected_date',
        'receive_date',
        'container_code',
        'container_size',
        'last_status',
        'status',
        'port_start',
        'currency',
        'warehouse',
        'ocean_freight',
        'port_of_destination_fee',
        'tariff',
        'company_code',
        'rate_number',
        'unloading_charges',
        'additional_unloading_charges',
        'other_expenses',
        'customer_id',
        'sku_count',
        'volume_sum',
        'remark',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
        'package_flag',
        'package_fee',
        'run_id',
        'source_receive_id',
        'warehousing_mode',
    ];

    public function receiptDetails()
    {
        return $this->hasMany(ReceiptsOrderDetail::class, 'receive_order_id');
    }

    // 托书
    public function shippingOrderBook()
    {
        return $this->hasOne(ReceiptsOrderShippingOrderBook::class, 'receive_order_id');
    }
}
