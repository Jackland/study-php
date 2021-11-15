<?php

namespace App\Models\Track;

use Framework\Model\EloquentModel;

/**
 * App\Models\Track\CountryState
 *
 * @property int $id
 * @property string|null $county_e ship state的英文名称
 * @property string|null $county_id ship state 对应县的 id
 * @property string|null $county ship state
 * @property int|null $country_id 国家
 * @property string|null $abbr ship state的简称
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Track\CountryState newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Track\CountryState newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Track\CountryState query()
 * @mixin \Eloquent
 */
class CountryState extends EloquentModel
{
    protected $table = 'tb_sys_country_state';

    protected $dates = [
        
    ];

    protected $fillable = [
        'county_e',
        'county_id',
        'county',
        'country_id',
        'abbr',
    ];
}
