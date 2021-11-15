<?php

namespace App\Models\Product;

use Framework\Model\EloquentModel;

/**
 * App\Models\Product\Category
 *
 * @property int $category_id
 * @property string|null $image
 * @property int $parent_id
 * @property int $top
 * @property int $column
 * @property int $sort_order
 * @property int $status
 * @property string $date_added
 * @property string $date_modified
 * @property int|null $category_level
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Category newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Category newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\Category query()
 * @mixin \Eloquent
 * @property-read \App\Models\Product\CategoryDescription $description
 */
class Category extends EloquentModel
{
    protected $table = 'oc_category';
    protected $primaryKey = 'category_id';

    protected $fillable = [
        'image',
        'parent_id',
        'top',
        'column',
        'sort_order',
        'status',
        'date_added',
        'date_modified',
        'category_level',
    ];

    public function description()
    {
        return $this->hasOne(CategoryDescription::class, 'category_id', 'category_id');
    }
}
