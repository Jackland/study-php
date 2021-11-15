<?php

namespace App\Models\Message;

use Framework\Model\EloquentModel;

/**
 * App\Models\Message\MsgCommonWords
 *
 * @property int $id ID
 * @property string $en_content 英文
 * @property string $zh_content 中文
 * @property string|null $created_name 创建的用户
 * @property int $status 1未发布 2已发布
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Message\MsgCommonWordsType[] $types
 * @property-read int|null $types_count
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\MsgCommonWords newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\MsgCommonWords newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\MsgCommonWords query()
 * @mixin \Eloquent
 */
class MsgCommonWords extends EloquentModel
{
    protected $table = 'oc_msg_common_words';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'en_content',
        'zh_content',
        'created_name',
        'status',
        'create_time',
        'update_time',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function types()
    {
        return $this->belongsToMany(MsgCommonWordsType::class, 'oc_msg_common_words_to_type', 'words_id', 'type_id');
    }
}
