<?php

namespace App\Helper;

class ImageHelper
{
    /**
     * 获取图片 base64 地址
     * @param string $path
     * @param string $type
     * @return string
     */
    public static function getImgSrcBase64Data(string $path, $type = 'png'): string
    {
        $path = aliases($path);
        $fileTs = @filemtime($path);
        return cache()->getOrSet([__CLASS__, __FUNCTION__, $fileTs, 'v1', func_get_args()], function () use ($path, $type) {
            return "data:image/{$type};base64," . base64_encode(file_get_contents($path));
        });
    }
}
