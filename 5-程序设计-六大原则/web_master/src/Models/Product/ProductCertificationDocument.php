<?php

namespace App\Models\Product;

use App\Components\Storage\StorageCloud;
use App\Components\Traits\RequestCachedDataTrait;
use Framework\Model\EloquentModel;

/**
 * App\Models\Product\ProductCertificationDocument
 *
 * @property int $id
 * @property int $product_id 产品ID
 * @property int $type_id 认证类型oc_product_certification_document_type
 * @property string $name 名称
 * @property string $url 路径
 * @property-read string $full_url
 * @property-read string $type_name
 */
class ProductCertificationDocument extends EloquentModel
{
    use RequestCachedDataTrait;

    protected $table = 'oc_product_certification_document';

    protected $dates = [

    ];

    protected $fillable = [
        'product_id',
        'type_id',
        'name',
        'url',
    ];

    public function getFullUrlAttribute(): string
    {
        return StorageCloud::root()->getUrl($this->url);
    }

    public function getTypeNameAttribute()
    {
        $idNameMap = $this->types()->pluck('title', 'id');
        return $idNameMap->get($this->type_id, '');
    }

    private function types()
    {
        return $this->requestCachedData([__CLASS__, __FUNCTION__], function () {
            return ProductCertificationDocumentType::queryRead()->get();
        });
    }
}
