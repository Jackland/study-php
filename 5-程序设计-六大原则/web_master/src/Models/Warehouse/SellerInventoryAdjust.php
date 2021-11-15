<?php

namespace App\Models\Warehouse;

use App\Components\RemoteApi;
use Framework\Model\EloquentModel;
use Illuminate\Support\Collection;

/**
 * App\Models\Warehouse\SellerInventoryAdjust
 *
 * @property int $inventory_id 库存调整ID
 * @property int|null $wh_id 仓库
 * @property int $customer_id 客户Id
 * @property int|null $transaction_type 处理类型, 1 库存上调  2 库存下调
 * @property int|null $status 状态： 1：待审核，2：审核通过，3：审核不通过，4：取消，5：待确认；6：结算中；7：已结算
 * @property string|null $remark 备注
 * @property int|null $create_user_name 创建人
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property int|null $update_user_name 最后更新人
 * @property \Illuminate\Support\Carbon|null $update_time 最后更新时间
 * @property string|null $batch_number 批次号
 * @property string|null $program_code 程序号
 * @property int|null $apply_file_menu_id 申请凭证附件menuId
 * @property int|null $confirm_file_menu_id seller确认附件menuId
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Warehouse\SellerInventoryAdjust newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Warehouse\SellerInventoryAdjust newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Warehouse\SellerInventoryAdjust query()
 * @mixin \Eloquent
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Warehouse\SellerInventoryAdjustLine[] $adjustDetail
 * @property-read int|null $adjust_detail_count
 * @property-read Collection|RemoteApi\B2BManager\DTO\FileDTO[] $seller_files
 * @property-read Collection|RemoteApi\B2BManager\DTO\FileDTO[] $warehouse_files
 * @property int|null $version 版本号
 */
class SellerInventoryAdjust extends EloquentModel
{
    protected $table = 'tb_sys_seller_inventory_adjust';
    protected $primaryKey = 'inventory_id';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'wh_id',
        'customer_id',
        'transaction_type',
        'status',
        'remark',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'version',
        'program_code',
    ];

    public function adjustDetail()
    {
        return $this->hasMany(SellerInventoryAdjustLine::class, 'inventory_id');
    }

    private $_cachedWarehouseFiles = [];

    public function getWarehouseFilesAttribute()
    {
        if (!isset($this->_cachedWarehouseFiles[$this->apply_file_menu_id])) {
            $this->_cachedWarehouseFiles[$this->apply_file_menu_id] = $this->apply_file_menu_id ? RemoteApi::file()->getByMenuId($this->apply_file_menu_id) : collect();
        }
        return $this->_cachedWarehouseFiles[$this->apply_file_menu_id];
    }

    private $_cachedSellerFiles = [];

    public function getSellerFilesAttribute()
    {
        if (!isset($this->_cachedSellerFiles[$this->confirm_file_menu_id])) {
            $this->_cachedSellerFiles[$this->confirm_file_menu_id] = $this->confirm_file_menu_id ? RemoteApi::file()->getByMenuId($this->confirm_file_menu_id) : collect();
        }
        return $this->_cachedSellerFiles[$this->confirm_file_menu_id];
    }
}
