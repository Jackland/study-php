<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * App\Models\Product\ProductCertificationDocumentType
 * 
 * @property int $id
 * @property string|null $title 类型标题
 * @property string $create_user_name 创建人
 * @property Carbon $create_time 创建时间
 */
class ProductCertificationDocumentType extends Model
{
    public $timestamps = false;

    protected $table = 'oc_product_certification_document_type';

    protected $dates = [
        'create_time',
    ];

    protected $fillable = [
        'title',
        'create_user_name',
        'create_time',
    ];
}
