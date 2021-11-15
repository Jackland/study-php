<?php

namespace App\Assets\ThirdPart;

use Framework\View\Assets\AssetBundle;

class JqueryAsset extends AssetBundle
{
    // 后续需要迁移
    public $sourcePath = '@root/catalog/view/javascript/jquery';

    public $css = [];

    public $js = [
        'jquery-2.1.1.min.js',
    ];
}
