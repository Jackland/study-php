<?php

namespace App\Assets\ThirdPart;

use Framework\View\Assets\AssetBundle;
use Framework\View\Enums\ViewWebPosition;

class SummernoteAsset extends AssetBundle
{
    public $sourcePath = '@npm/summernote-0.8.2-dist/dist/';
    public $basePath = '@assets';
    public $baseUrl = '@assetsUrl';

    public $css = [
        'summernote.css',
    ];

    public $js = [
        ['summernote.min.js', 'position' => ViewWebPosition::HEAD],
    ];

    public $depends = [
        //JqueryAsset::class,
    ];
}
