<?php

namespace App\Models\Message;

use Framework\Model\EloquentModel;

/**
 * App\Models\Message\MsgCommonWordsType
 *
 * @property int $id ID
 * @property string $en_name 英文名
 * @property string $zh_name 中文名
 * @property int $customer_type 类型：1全部客户 2buyer 3seller
 * @property int $sort 排序 越大展示在最前方
 * @property int $is_deleted 0未删除,1已删除
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Message\MsgCommonWords[] $words
 * @property-read int|null $words_count
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\MsgCommonWordsType newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\MsgCommonWordsType newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\MsgCommonWordsType query()
 * @mixin \Eloquent
 */
class MsgCommonWordsType extends EloquentModel
{
    protected $table = 'oc_msg_common_words_type';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'en_name',
        'zh_name',
        'customer_type',
        'sort',
        'is_deleted',
        'create_time',
        'update_time',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function words()
    {
        return $this->belongsToMany(MsgCommonWords::class, 'oc_msg_common_words_to_type', 'type_id', 'words_id');
    }
}
