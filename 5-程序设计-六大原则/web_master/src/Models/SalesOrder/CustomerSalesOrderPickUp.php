<?php

namespace App\Models\SalesOrder;

use App\Components\RemoteApi;
use Eloquent;
use Framework\Model\Eloquent\Builder;
use Framework\Model\EloquentModel;
use App\Models\Warehouse\WarehouseInfo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * App\Models\SalesOrder\CustomerSalesOrderPickUp
 *
 * @property int $id
 * @property int $sales_order_id 销售单ID
 * @property string $warehouse_id 仓库ID，同 tb_warehouses 的 WarehouseID
 * @property string $apply_date 申请取货日期
 * @property string $user_name 取货人姓名
 * @property string $user_phone 取货人联系电话
 * @property int $need_tray 是否需要打托服务
 * @property int $can_adjust 是否接受取货仓库调剂
 * @property int $stock_tray_num 仓库托盘数
 * @property int $pick_up_status 自提货子状态：tb_sys_dictionary 中 DicCategory 为 CUSTOMER_ORDER_PICK_UP_STATUS
 * @property string|null $bol_num BOL提货单号：生成BOL文件时产生
 * @property int $bol_file_id BOL文件：发给仓库的文件
 * @property int $pick_up_file_id 取货凭证：取货完成后仓库给
 * @property Carbon $create_time 创建时间
 * @property Carbon $update_time 修改时间
 * @method static Builder|CustomerSalesOrderPickUp newModelQuery()
 * @method static Builder|CustomerSalesOrderPickUp newQuery()
 * @method static Builder|CustomerSalesOrderPickUp query()
 * @mixin Eloquent
 * @property-read CustomerSalesOrder $salesOrder
 * @property-read WarehouseInfo $warehouse
 * @property-read mixed $bol_files
 * @property-read mixed $pick_files
 * @property-read Collection|CustomerSalesOrderPickUpLineChange[] $pickUpLineChanges
 * @property-read int|null $pick_up_line_changes_count
 */
class CustomerSalesOrderPickUp extends EloquentModel
{
    protected $table = 'tb_sys_customer_sales_order_pick_up';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'sales_order_id',
        'warehouse_id',
        'apply_date',
        'user_name',
        'user_phone',
        'need_tray',
        'can_adjust',
        'stock_tray_num',
        'pick_up_status',
        'bol_num',
        'bol_file_id',
        'pick_up_file_id',
        'create_time',
        'update_time',
    ];

    //销售单
    public function salesOrder()
    {
        return $this->belongsTo(CustomerSalesOrder::class, 'sales_order_id');
    }

    //申请取货仓库
    public function warehouse()
    {
        return $this->belongsTo(WarehouseInfo::class, 'warehouse_id');
    }

    //仓库发货信息变更记录
    public function pickUpLineChanges()
    {
        return $this->hasMany(CustomerSalesOrderPickUpLineChange::class, 'sales_order_id', 'sales_order_id');
    }

    private $_cachedBolFiles = [];

    public function getBolFilesAttribute()
    {
        if (!isset($this->_cachedBolFiles[$this->bol_file_id])) {
            $this->_cachedBolFiles[$this->bol_file_id] = $this->bol_file_id ? RemoteApi::file()->getByMenuId($this->bol_file_id) : collect();
        }
        return $this->_cachedBolFiles[$this->bol_file_id];
    }

    private $_cachedPickFiles = [];

    public function getPickFilesAttribute()
    {
        if (!isset($this->_cachedPickFiles[$this->pick_up_file_id])) {
            $this->_cachedPickFiles[$this->pick_up_file_id] = $this->pick_up_file_id ? RemoteApi::file()->getByMenuId($this->pick_up_file_id) : collect();
        }
        return $this->_cachedPickFiles[$this->pick_up_file_id];
    }
}
