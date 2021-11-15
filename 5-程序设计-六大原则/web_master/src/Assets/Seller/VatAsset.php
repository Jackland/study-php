<?php

namespace App\Assets\Seller;

use Framework\View\Assets\AssetBundle;

class VatAsset extends AssetBundle
{
    public $basePath = '@root/catalog/view/javascript';
    public $baseUrl = 'catalog/view/javascript';

    public $css = ['user_vaticon/vat-tool-tip.css'];
    public $js = ['user_vaticon/vatTooltip.js'];
}
