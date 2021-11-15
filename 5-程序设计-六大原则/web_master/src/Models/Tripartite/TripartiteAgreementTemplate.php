<?php

namespace App\Models\Tripartite;

use Framework\Model\EloquentModel;

/**
 * App\Models\Tripartite\TripartiteAgreementTemplate
 *
 * @property int $id ID
 * @property string $customer_ids 用户ID逗号分隔， 0为所有用户
 * @property string $content 内容
 * @property string|null $replace_value 模板替换值 json
 * @property int $is_deleted
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property \Illuminate\Support\Carbon $delete_time 删除时间
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Tripartite\TripartiteAgreementTemplate newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Tripartite\TripartiteAgreementTemplate newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Tripartite\TripartiteAgreementTemplate query()
 * @mixin \Eloquent
 * @property-read array $template_replaces
 */
class TripartiteAgreementTemplate extends EloquentModel
{
    protected $table = 'oc_tripartite_agreement_template';

    protected $dates = [
        'create_time',
        'delete_time',
    ];

    protected $fillable = [
        'customer_ids',
        'content',
        'replace_value',
        'is_deleted',
        'create_time',
        'delete_time',
    ];


    /**
     * 获取模板替换的值
     * @return array
     */
    public function getTemplateReplacesAttribute(): array
    {
        return $this->replace_value ? json_decode($this->replace_value, true) : [];
    }
}
