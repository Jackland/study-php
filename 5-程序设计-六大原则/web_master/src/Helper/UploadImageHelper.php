<?php

namespace App\Helper;

use App\Components\Storage\StorageCloud;

class UploadImageHelper
{
    /**
     * 通过原始地址获取相关信息，用于展示到前端 upload_input
     * @param $originUrl
     * @param null $name
     * @param string $noImage
     * @param int $width
     * @param int $height
     * @return array ['orig_url' => '原始地址，相对image/', 'thumb' => '缩略图', 'url' => '放大地址', 'name' => '名称']
     */
    public static function getInfoFromOriginUrl($originUrl, $name = null, $noImage = 'no_image.png', $width = 100, $height = 100)
    {
        $originUrl = ltrim($originUrl, 'image/');
        $result = [
            'orig_url' => $originUrl,
            'thumb' => StorageCloud::image()->getUrl($originUrl, [
                'w' => $width,
                'h' => $height,
                'no-image' => $noImage,
            ]),
            'url' => $originUrl,
            'name' => $name ?: basename($originUrl),
        ];
        if (strpos($result['thumb'], $noImage) !== false) {
            // 图片不存在
            $result['url'] = '';
        } else {
            $result['url'] = StorageCloud::image()->getUrl($originUrl, ['check-exist' => false]);
        }

        return $result;
    }
}
