<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Product\ProductCertificationDocument
 *
 * @property int $id
 * @property int $product_id 产品ID
 * @property int $type_id 认证类型oc_product_certification_document_type
 * @property string $name 名称
 * @property string $url 路径
 * @property-read ProductCertificationDocumentType $type
 */
class ProductCertificationDocument extends Model
{
    public $timestamps = false;

    protected $table = 'oc_product_certification_document';

    protected $dates = [

    ];
    protected $fillable = [
        'product_id',
        'type_id',
        'name',
        'url',
    ];

    public function type()
    {
        return $this->belongsTo(ProductCertificationDocumentType::class, 'type_id');
    }
}
