<?php

namespace App\Models\Futures;

use Framework\Model\EloquentModel;

/**
 * App\Models\FuturesAgreementFile
 *
 * @property int $id ID
 * @property int $apply_id oc_futures_agreement_apply.id
 * @property int $message_id oc_futures_margin_message.id
 * @property string|null $file_name 文件名称
 * @property string|null $file_path 文件路径
 * @property int|null $size 文件大小
 * @property int $is_deleted
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Futures\FuturesAgreementFile newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Futures\FuturesAgreementFile newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Futures\FuturesAgreementFile query()
 * @mixin \Eloquent
 */
class FuturesAgreementFile extends EloquentModel
{
    protected $table = 'oc_futures_agreement_file';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'apply_id',
        'message_id',
        'file_name',
        'file_path',
        'size',
        'is_deleted',
        'create_time',
        'update_time',
    ];
}
