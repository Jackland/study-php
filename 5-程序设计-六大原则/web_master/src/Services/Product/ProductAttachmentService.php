<?php

namespace App\Services\Product;

use App\Components\Storage\StorageCloud;
use App\Models\Product\Option\ProductImage;
use App\Models\Product\Option\ProductPackageFile;
use App\Models\Product\Option\ProductPackageImage;
use App\Models\Product\Option\ProductPackageVideo;

class ProductAttachmentService
{
    /**
     * 写入页面展示图片 如果展示图中包含主图，还得从展示图中去掉同real_url的数据
     * @param int $productId
     * @param array $images
     * @param string $mainImage
     * @return bool
     */
    public function addProductImages(int $productId, array $images, string $mainImage = '')
    {
        $keyIdx = 1;
        $addArray = [];
        $mainImageUrl = $mainImage ? $this->handleFilePath($mainImage) : '';
        foreach ($images as $image) {
            $imageRealPath = $this->handleFilePath($image['file_real_path']);
            if ($mainImageUrl && $mainImageUrl == $imageRealPath) {
                continue;
            }
            $addArray[] = [
                'product_id' => $productId,
                'image' => $imageRealPath,
                'sort_order' => $keyIdx,
            ];
            $keyIdx++;
        }
        if ($mainImageUrl) {
            $mainImageArr = [
                'product_id' => $productId,
                'image' => $mainImageUrl,
                'sort_order' => 0,
            ];
            array_unshift($addArray, $mainImageArr);
        }

        if ($addArray) {
            ProductImage::query()->insert($addArray);
        }
        return true;
    }

    /**
     * 写入其他图片
     * @param int $productId
     * @param array $materialImages
     * @return bool
     */
    public function addProductMaterialPackageImages(int $productId, array $materialImages)
    {
        if (empty($productId) || empty($materialImages)) {
            return false;
        }
        foreach ($materialImages as $materialImage) {
            $originName = substr($materialImage['file_origin_path'], (strrpos($materialImage['file_origin_path'], '/') ?: -1) + 1);
            $addArrays[] = [
                'product_id' => $productId,
                'image_name' => substr($materialImage['file_real_path'], strrpos($materialImage['file_real_path'], '/') + 1),
                'origin_image_name' => $originName,
                'image' => $this->handleFilePath($materialImage['file_real_path']),
            ];
        }

        ProductPackageImage::query()->insert($addArrays);
        return true;
    }

    /**
     * 写入手册素材
     * @param int $productId
     * @param array $materialManuals
     * @return bool
     */
    public function addProductMaterialManualsFile(int $productId, array $materialManuals)
    {
        if (empty($productId) || empty($materialManuals)) {
            return false;
        }
        foreach ($materialManuals as $materialManual) {
            $originName = substr($materialManual['file_origin_path'], (strrpos($materialManual['file_origin_path'], '/') ?: -1) + 1);
            $addArrays[] = [
                'product_id' => $productId,
                'file_name' => substr($materialManual['file_real_path'], strrpos($materialManual['file_real_path'], '/') + 1),
                'origin_file_name' => $originName,
                'file' => $this->handleFilePath($materialManual['file_real_path']),
            ];
        }

        ProductPackageFile::query()->insert($addArrays);
        return true;
    }

    /**
     * 写入视频txt文件
     * @param int $productId
     * @param array $materialVideos
     * @return bool
     */
    public function addProductMaterialPackageVideo(int $productId, array $materialVideos)
    {
        if (empty($productId) || empty($materialVideos)) {
            return false;
        }
        foreach ($materialVideos as $materialVideo) {
            $originName = substr($materialVideo['file_origin_path'], (strrpos($materialVideo['file_origin_path'], '/') ?: -1) + 1);
            $addArrays[] = [
                'product_id' => $productId,
                'video_name' => substr($materialVideo['file_real_path'], strrpos($materialVideo['file_real_path'], '/') + 1),
                'origin_video_name' => $originName,
                'video' => $this->handleFilePath($materialVideo['file_real_path']),
            ];
        }

        ProductPackageVideo::query()->insert($addArrays);
        return true;
    }

    /**
     * 写入专利图片
     * @param int $productId
     * @param array $supportFiles
     * @return bool
     */
    public function addProductSupportFiles(int $productId, array $supportFiles)
    {
        if (empty($productId) || empty($supportFiles)) {
            return false;
        }
        foreach ($supportFiles as $supportFile) {
            $originName = substr($supportFile['file_origin_path'], (strrpos($supportFile['file_origin_path'], '/') ?: -1) + 1);
            $addArrays[] = [
                'product_id' => $productId,
                'image_name' => substr($supportFile['file_real_path'], strrpos($supportFile['file_real_path'], '/') + 1),
                'origin_image_name' => $originName,
                'image' => $this->handleFilePath($supportFile['file_real_path']),
            ];
        }

        db('oc_product_package_original_design_image')->insert($addArrays);
        return true;
    }

    /**
     * 文件管理里面的文件都是image/wkseller打头，商品相关的附件信息都是代码拼接的，所以得去掉image/
     * @param string $filePath
     * @return string
     */
    public function handleFilePath($filePath = '')
    {
        if (empty($filePath)) {
            return '';
        }
        if (substr($filePath, 0, 6) == 'image/') {
            return StorageCloud::image()->getRelativePath($filePath);
        }

        return $filePath;
    }

}
