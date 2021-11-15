<?php

namespace App\Models\Log;

use Framework\Model\EloquentModel;

/**
 * App\Models\Log\ProductLtlLog
 *
 * @property int $id 主键ID
 * @property int $product_id 产品id
 * @property int $ltl_type 1设置为ltl, 2取消ltl标记
 * @property string $length 长
 * @property string $width 宽
 * @property string $height 高
 * @property string $weight 重
 * @property string|null $remark 备注
 * @property string|null $operator 操作人员 seller名/后台运营人员/system
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Log\ProductLtlLog newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Log\ProductLtlLog newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Log\ProductLtlLog query()
 * @mixin \Eloquent
 */
class ProductLtlLog extends EloquentModel
{
    const LTL_TYPE_SET = 1;
    const LTL_TYPE_CANCEL = 2;

    protected $table = 'oc_product_ltl_log';

    protected $dates = [
        'create_time',
    ];

    protected $fillable = [
        'product_id',
        'ltl_type',
        'length',
        'width',
        'height',
        'weight',
        'remark',
        'operator',
        'create_time',
    ];
}
