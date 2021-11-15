<?php

namespace App\Assets\Common;

use Framework\View\Assets\AssetBundle;
use Framework\View\Enums\ViewWebPosition;

class SummernoteAsset extends AssetBundle
{
    public $basePath = '@public/js/common';
    public $baseUrl = '@publicUrl/js/common';

    public $js = [
        ['summernote.js', 'position' => ViewWebPosition::HEAD],
    ];

    public $depends = [
        \App\Assets\ThirdPart\SummernoteAsset::class,
    ];
}
