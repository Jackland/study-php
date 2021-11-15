<?php

namespace App\Models\Customer;

use Framework\Model\EloquentModel;

/**
 * App\Models\Customer\CustomerScoreDimension
 *
 * @property int $dimension_id 评分维度ID
 * @property string $code 编码维度
 * @property string $title 评分维度的标题
 * @property string $description 评分维度的解释和描述
 * @property int $parent_dimension_id 父维度ID 为0的时候表示是顶级维度
 * @property int $seller_dimension 是否是seller维度 0:否；1:是
 * @property int $status 是否启用 0:否；1:是
 * @property string $create_username 创建人
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property string $update_username 更新人
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Customer\CustomerScoreDimension newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Customer\CustomerScoreDimension newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Customer\CustomerScoreDimension query()
 * @mixin \Eloquent
 */
class CustomerScoreDimension extends EloquentModel
{
    protected $table = 'tb_customer_score_dimension';
    protected $primaryKey = 'dimension_id';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'code',
        'title',
        'description',
        'parent_dimension_id',
        'seller_dimension',
        'status',
        'create_username',
        'create_time',
        'update_username',
        'update_time',
    ];
}
