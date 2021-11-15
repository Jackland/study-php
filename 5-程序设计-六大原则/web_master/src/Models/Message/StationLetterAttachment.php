<?php

namespace App\Models\Message;

use Framework\Model\EloquentModel;

/**
 * App\Models\Message\StationLetterAttachment
 *
 * @property int $id 站内信绑定附件关系表
 * @property int|null $letter_id 站内信ID @tb_sys_station_letter
 * @property int|null $attachment_id 附件ID @tb_upload_file
 * @property string|null $create_user_name 创建者
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property \Illuminate\Support\Carbon|null $update_time 修改时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\StationLetterAttachment newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\StationLetterAttachment newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\StationLetterAttachment query()
 * @mixin \Eloquent
 */
class StationLetterAttachment extends EloquentModel
{
    protected $table = 'tb_sys_station_letter_attachment';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'letter_id',
        'attachment_id',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
    ];
}
