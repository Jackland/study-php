<?php

namespace App\Models\Message;

use App\Models\Setting\Dictionary;
use Framework\Model\EloquentModel;

/**
 * App\Models\Message\StationLetter
 *
 * @property int $id 主键ID
 * @property int|null $is_delete 是否删除 0 未删除 1 已删除
 * @property int|null $status 发送状态 0 未发送 1 已发送
 * @property string|null $title 通知主题
 * @property int|null $type 通知类型 \r\n1 系统更新 2 费用调整 3 节假日安排 4 政策调整 5 其他
 * @property string|null $content 通知内容
 * @property int|null $is_send_all 是否全部发送  0 否 1 是
 * @property string|null $send_object 发送对象，字符串拼接 @tb_sys_station_letter_object
 * @property int|null $is_send_immediately 是否立即发送  0 否 1 是
 * @property \Illuminate\Support\Carbon|null $send_time 发送时间
 * @property int $is_popup 是否弹窗 0:不弹窗 1:弹窗
 * @property string|null $create_user_name 创建者
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property \Illuminate\Support\Carbon|null $update_time 修改时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\StationLetter newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\StationLetter newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\StationLetter query()
 * @mixin \Eloquent
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Message\StationLetterCustomer[] $stationLetterCustomer
 * @property-read int|null $station_letter_customer_count
 */
class StationLetter extends EloquentModel
{
    protected $table = 'tb_sys_station_letter';

    protected $dates = [
        'send_time',
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'is_delete',
        'status',
        'title',
        'type',
        'content',
        'is_send_all',
        'send_object',
        'is_send_immediately',
        'send_time',
        'is_popup',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
    ];

    public function stationLetterCustomer()
    {
        return $this->hasMany(StationLetterCustomer::class, 'letter_id');
    }
}
