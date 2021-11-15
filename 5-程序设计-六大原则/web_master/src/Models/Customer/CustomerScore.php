<?php

namespace App\Models\Customer;

use Framework\Model\EloquentModel;

/**
 * App\Models\Customer\CustomerScore
 *
 * @property int $id 自增主键ID
 * @property int $customer_id 帐号主键ID
 * @property string $task_number 评分任务执行编号 评分任务执行的批次编号，任务执行日期的yyyyMMdd格式
 * @property \Illuminate\Support\Carbon $range_start 评分周期的开始时间
 * @property int $country_id 国别ID
 * @property int $dimension_id 评分维度ID
 * @property float $score 评分分值
 * @property string $create_username 创建人
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property string $update_username 更新人
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Customer\CustomerScore newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Customer\CustomerScore newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Customer\CustomerScore query()
 * @mixin \Eloquent
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Customer\CustomerScoreSub[] $subs
 * @property-read int|null $subs_count
 */
class CustomerScore extends EloquentModel
{
    protected $table = 'tb_customer_score';

    protected $dates = [
        'range_start',
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'customer_id',
        'task_number',
        'range_start',
        'country_id',
        'dimension_id',
        'score',
        'create_username',
        'create_time',
        'update_username',
        'update_time',
    ];

    public function subs()
    {
        return $this->hasMany(CustomerScoreSub::class, 'score_id', 'id');
    }
}
