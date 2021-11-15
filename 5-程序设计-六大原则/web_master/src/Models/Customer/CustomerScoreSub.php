<?php

namespace App\Models\Customer;

use Framework\Model\EloquentModel;

/**
 * App\Models\Customer\CustomerScoreSub
 *
 * @property int $id 自增主键ID
 * @property int $score_id tb_customer_score.id
 * @property int $customer_id 帐号主键ID
 * @property int $dimension_id 评分维度ID
 * @property float $score 评分分值
 * @property string $create_username 创建人
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property string $update_username 更新人
 * @property \Illuminate\Support\Carbon $update_time 更新时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Customer\CustomerScoreSub newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Customer\CustomerScoreSub newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Customer\CustomerScoreSub query()
 * @mixin \Eloquent
 * @property-read \App\Models\Customer\CustomerScore $parentScore
 */
class CustomerScoreSub extends EloquentModel
{
    protected $table = 'tb_customer_score_sub';

    protected $dates = [
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'score_id',
        'customer_id',
        'dimension_id',
        'score',
        'create_username',
        'create_time',
        'update_username',
        'update_time',
    ];

    public function parentScore()
    {
        return $this->hasOne(CustomerScore::class, 'id', 'score_id');
    }
}
