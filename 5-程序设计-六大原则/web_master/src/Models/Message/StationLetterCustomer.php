<?php

namespace App\Models\Message;

use Framework\Model\EloquentModel;

/**
 * App\Models\Message\StationLetterCustomer
 *
 * @property int $id 站内信用户关系
 * @property int|null $letter_id 站内信ID
 * @property int|null $customer_id buyerId or  sellerId
 * @property int|null $is_read 是否已阅 0 未阅 1 已阅
 * @property int|null $is_delete 是否删除 0 未删除  1 已删除
 * @property int|null $is_marked 是否标记 0 未标记  1 已标记
 * @property string|null $create_user_name 创建者
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property \Illuminate\Support\Carbon|null $update_time 修改时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\StationLetterCustomer newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\StationLetterCustomer newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\StationLetterCustomer query()
 * @mixin \Eloquent
 */
class StationLetterCustomer extends EloquentModel
{
    protected $table = 'tb_sys_station_letter_customer';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'letter_id',
        'customer_id',
        'is_read',
        'is_delete',
        'is_marked',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
    ];

    public function stationLetter()
    {
        return $this->belongsTo(StationLetter::class, 'letter_id');
    }
}
