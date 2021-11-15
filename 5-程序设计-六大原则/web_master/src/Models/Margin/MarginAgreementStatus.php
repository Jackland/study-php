<?php

namespace App\Models\Margin;

use Framework\Model\EloquentModel;

/**
 * App\Models\Margin\MarginAgreementStatus
 *
 * @property int $margin_agreement_status_id
 * @property int|null $language_id
 * @property string|null $name
 * @property string|null $description
 * @property string $color
 * @property int $sort 排序,越小越靠前
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Margin\MarginAgreementStatus newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Margin\MarginAgreementStatus newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Margin\MarginAgreementStatus query()
 * @mixin \Eloquent
 */
class MarginAgreementStatus extends EloquentModel
{
    protected $table = 'tb_sys_margin_agreement_status';
    protected $primaryKey = 'margin_agreement_status_id';

    protected $dates = [
        
    ];

    protected $fillable = [
        'language_id',
        'name',
        'description',
        'color',
        'sort',
    ];
}
