<?php

namespace App\Assets\Common;

use Framework\View\Assets\AssetBundle;

/**
 * CNZZ 统计
 */
class CnzzAnalysisAsset extends AssetBundle
{
    public $basePath = '@public/js/common';
    public $baseUrl = '@publicUrl/js/common';

    public function __construct()
    {
        // CNZZ 统计的 js 代码，如：'https://s4.cnzz.com/z_stat.php?id=1279194549&web_id=1279194549'
        $cnzzUrl = defined('CNZZ_ANALYSIS_URL') ? CNZZ_ANALYSIS_URL : '';
        if ($cnzzUrl) {
            $this->js[] = 'cnzz-analysis.js';
            $this->js[] = $cnzzUrl;
        }
    }

    public $depends = [
        //JqueryAsset::class, // 由于 jquery 暂时未全局前置加载的，因此暂时不依赖
    ];
}
