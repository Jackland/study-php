<?php

namespace App\Models\Information;

use Framework\Model\EloquentModel;

/**
 * App\Models\Information\InformationDescription
 *
 * @property int $information_id
 * @property int $language_id
 * @property string $title
 * @property string $description
 * @property string $meta_title
 * @property string $meta_description
 * @property string $meta_keyword
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Information\InformationDescription newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Information\InformationDescription newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Information\InformationDescription query()
 * @mixin \Eloquent
 */
class InformationDescription extends EloquentModel
{
    protected $table = 'oc_information_description';
    protected $primaryKey = ''; // TODO 主键未知或大于1个

    protected $dates = [
        
    ];

    protected $fillable = [
        'title',
        'description',
        'meta_title',
        'meta_description',
        'meta_keyword',
    ];
}
