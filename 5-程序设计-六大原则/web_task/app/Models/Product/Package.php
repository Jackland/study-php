<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Class Package
 * @package App\Models\Product
 */
class Package extends Model
{

    /**
     * @param int $product_id
     * @return \Illuminate\Support\Collection
     */
    public function getFile($product_id)
    {
        return DB::table('oc_product_package_file')
            ->select(['file', 'origin_file_name'])
            ->where('product_id', $product_id)
            ->get();
    }

    /**
     * @param int $product_id
     * @return \Illuminate\Support\Collection
     */
    public function getImage($product_id)
    {
        return DB::table('oc_product_package_image')
            ->select(['image', 'origin_image_name'])
            ->where('product_id', $product_id)
            ->get();
    }

    /**
     * @param int $product_id
     * @return \Illuminate\Support\Collection
     */
    public function getVideo($product_id)
    {
        return DB::table('oc_product_package_video')
            ->select(['video', 'origin_video_name'])
            ->where('product_id', $product_id)
            ->get();
    }
}
