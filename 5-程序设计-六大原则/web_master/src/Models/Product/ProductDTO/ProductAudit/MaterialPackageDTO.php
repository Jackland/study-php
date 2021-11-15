<?php

namespace App\Models\Product\ProductDTO\ProductAudit;

use App\Models\Product\ProductDTO\ProductAudit\MaterialPackage\CertificationDocumentDTO;
use App\Models\Product\ProductDTO\ProductAudit\MaterialPackage\ProductImageDTO;
use App\Models\Product\ProductDTO\ProductAudit\MaterialPackage\ProductPackageDTO;
use Illuminate\Support\Fluent;

/**
 * oc_product_audit中的material_package字段
 * @property-read ProductImageDTO[] $product_images 产品图片
 * @property-read ProductPackageDTO[] $images 打包图片
 * @property-read ProductPackageDTO[] $files 打包文件
 * @property-read ProductPackageDTO[] $videos 打包视频
 * @property-read ProductPackageDTO[] $designs 打包原创图片
 * @property-read CertificationDocumentDTO[] $certification_documents
 */
class MaterialPackageDTO extends Fluent
{

}
