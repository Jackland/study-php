<?php

namespace App\Models\CWF;

use App\Components\RemoteApi;
use App\Components\RemoteApi\B2BManager\DTO\FileDTO;
use Framework\Model\EloquentModel;

/**
 * App\Models\CWF\OrderCloudLogisticsTracking
 *
 * @property int $id 主键ID 自增主键ID
 * @property int $cloud_logistics_batch_id 云送仓托盘批次ID
 * @property int $cloud_logistics_id 云送仓订单ID
 * @property int $pallet_qty 托盘数
 * @property string $carrier 配送物流
 * @property string $tracking_number 运单号
 * @property int $shipping_status 配送状态
 * @property string|null $freight 运费
 * @property \Illuminate\Support\Carbon|null $delivery_date 发货日期
 * @property \Illuminate\Support\Carbon|null $estimated_pickup_time 预计取货时间
 * @property int $is_deleted 是否删除
 * @property int|null $bol_signed_file_id BOL-signed文件oss key
 * @property int|null $pod_file_id BOL文件oss key
 * @property string|null $memo 备注
 * @property string $create_user_name 创建人
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property string|null $update_user_name 更新人
 * @property \Illuminate\Support\Carbon|null $update_time 更新时间
 * @property string|null $program_code 程序号
 * @property-read FileDTO|null $bolSignedFile bol signed 文件
 * @property-read FileDTO|null $podFile pod文件
 * @property-read OrderCloudLogistics $cloudLogistic 云送仓订单
 * @property-read \FileDTO|null $bol_signed_file
 * @property-read \FileDTO|null $pod_file
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\CWF\OrderCloudLogisticsTracking newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\CWF\OrderCloudLogisticsTracking newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\CWF\OrderCloudLogisticsTracking query()
 * @mixin \Eloquent
 */
class OrderCloudLogisticsTracking extends EloquentModel
{
    protected $table = 'oc_order_cloud_logistics_tracking';

    protected $dates = [
        'delivery_date',
        'estimated_pickup_time',
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'cloud_logistics_batch_id',
        'cloud_logistics_id',
        'pallet_qty',
        'carrier',
        'tracking_number',
        'shipping_status',
        'freight',
        'delivery_date',
        'estimated_pickup_time',
        'is_deleted',
        'bol_signed_file_id',
        'pod_file_id',
        'memo',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
    ];

    /**
     * 云送仓订单
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function cloudLogistic()
    {
        return $this->belongsTo(OrderCloudLogistics::class, 'cloud_logistics_id');
    }

    /**
     * 获取bol-signed文件对象，有可能为null，可以使用optional($model->podFile)->attribute
     *
     * @return FileDTO|null
     */
    public function getBolSignedFileAttribute()
    {
        return $this->getFileDTO('bol_signed_file_id');
    }

    /**
     * 获取pod文件对象,有可能为null，可以使用optional($model->podFile)->attribute
     *
     * @return FileDTO|null
     */
    public function getPodFileAttribute()
    {

        return $this->getFileDTO('pod_file_id');
    }

    // 临时存放变量，防止重复获取
    private $_files = [];

    /**
     * 获取对应key的FileDTO对象
     *
     * @param $key
     * @return FileDTO|null
     */
    private function getFileDTO($key)
    {
        // 假如就没有select对应字段，直接返回null
        if (!isset($this->attributes[$key]) || empty($this->attributes[$key])) {
            return null;
        }
        if (isset($this->_files[$key]) && $this->_files[$key] instanceof FileDTO) {
            return $this->_files[$key];
        }
        $files = RemoteApi::file()->getByMenuId($this->attributes[$key]);
        return $this->_files[$key] = $files->first();
    }
}
