<?php

namespace App\Models\Product;

use Framework\Model\EloquentModel;

/**
 * App\Models\Product\CategoryDescription
 *
 * @property int $category_id
 * @property int $language_id
 * @property string $name
 * @property string $description
 * @property string $meta_title
 * @property string $meta_description
 * @property string $meta_keyword
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\CategoryDescription newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\CategoryDescription newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\CategoryDescription query()
 * @mixin \Eloquent
 * @property string|null $product_service_code 产品服务CODE
 */
class CategoryDescription extends EloquentModel
{
    protected $table = 'oc_category_description';
    protected $primaryKey = 'category_id';

    protected $fillable = [
        'name',
        'description',
        'meta_title',
        'meta_description',
        'meta_keyword',
    ];
}
