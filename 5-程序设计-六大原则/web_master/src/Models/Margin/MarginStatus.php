<?php

namespace App\Models\Margin;

use Framework\Model\EloquentModel;

/**
 * App\Models\Margin\MarginStatus
 *
 * @property int $margin_agreement_status_id
 * @property int|null $language_id
 * @property string|null $name
 * @property string|null $description
 * @property string $color
 * @property-read \App\Models\Margin\MarginAgreement $marginAgreement
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Margin\MarginStatus newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Margin\MarginStatus newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Margin\MarginStatus query()
 * @mixin \Eloquent
 * @property bool $sort 排序,越小越靠前
 */
class MarginStatus extends EloquentModel
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
    ];

    public function marginAgreement()
    {
        return $this->hasOne(MarginAgreement::class, 'status');
    }
}
