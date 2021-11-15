<?php

namespace App\Models\Product;

use App\Widgets\ImageToolTipWidget;
use Framework\Model\EloquentModel;

/**
 * App\Models\Product\Tag
 *
 * @property int $tag_id 自增主键
 * @property string|null $description
 * @property string|null $icon 标签图标
 * @property int $sort_order 排序
 * @property int $status 是否启用标签(0:禁用;1:启用)
 * @property string|null $create_time 创建时间
 * @property string|null $create_user_name 创建人
 * @property string|null $update_time 更新时间
 * @property string|null $update_user_name 更新人
 * @property string|null $program_code 操作码
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Tag newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Tag newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Tag query()
 * @mixin \Eloquent
 * @property string $class_style 图片样式
 * @property-read string $tag_widget
 */
class Tag extends EloquentModel
{
    protected $table = 'oc_tag';
    protected $primaryKey = 'tag_id';

    protected $fillable = [
        'description',
        'icon',
        'sort_order',
        'status',
        'create_time',
        'create_user_name',
        'update_time',
        'update_user_name',
        'program_code',
    ];

    /**
     * 返回tag的标签
     * @return string
     */
    public function getTagWidgetAttribute()
    {
        return ImageToolTipWidget::widget([
            'tip' => $this->description,
            'image' => $this->icon,
            'class' => $this->class_style
        ])->render();
    }
}
