<?php

namespace App\Assets\Common;

use Framework\View\Assets\AssetBundle;

/**
 * 7Moor客服
 */
class SevenMoorAsset extends AssetBundle
{
    public $basePath = '@public/js/common/seven-moor';
    public $baseUrl = '@publicUrl/js/common/seven-moor';

    public $js = [
        'customer.js',
    ];

    public $depends = [
        //JqueryAsset::class, // 由于 jquery 暂时未全局前置加载的，因此暂时不依赖
    ];
}
