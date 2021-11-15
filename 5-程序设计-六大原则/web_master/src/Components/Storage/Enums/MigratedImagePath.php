<?php

namespace App\Components\Storage\Enums;

/**
 * 已经迁移到 oss 上的 image 下的路径
 * 临时记录，后续在全部迁移完成后可以移除该类
 */
class MigratedImagePath
{
    public static function getValues()
    {
        return [
            'wkseller',
            'wkmisc',
            'ACME',
            'productPackage',
            'Amazon',
            'catalog'
        ];
    }
}
