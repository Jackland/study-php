<?php

namespace App\Models\Product;

use App\Enums\Common\YesNoEnum;
use Framework\Model\Eloquent\Builder;
use Framework\Model\EloquentModel;

/**
 * App\Models\Product\ProductCertificationDocumentType
 *
 * @property int $id
 * @property string|null $title 类型标题
 * @property string $create_user_name 创建人
 * @property \Illuminate\Support\Carbon $create_time 创建时间
 * @property bool $status 1正常 0停用
 * @property bool $is_deleted 0未删 1已删
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductCertificationDocumentType newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductCertificationDocumentType newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Product\ProductCertificationDocumentType query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Product\ProductCertificationDocumentType valid()
 * @mixin \Eloquent
 */
class ProductCertificationDocumentType extends EloquentModel
{
    protected $table = 'oc_product_certification_document_type';

    protected $dates = [
        'create_time',
    ];

    protected $fillable = [
        'title',
        'create_user_name',
        'create_time',
    ];

    /**
     * 有效的
     * @param Builder $builder
     * @return Builder
     */
    public function scopeValid(Builder $builder) :Builder
    {
        return $builder->where('status', YesNoEnum::YES)->where('is_deleted', YesNoEnum::NO);
    }
}
