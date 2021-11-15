<?php

namespace App\Components\TwigExtensions;

use App\Models\Product\Product;
use App\Widgets\ImageToolTipWidget;
use ModelToolImage;

/**
 * 图片相关
 */
class ImageExtension extends AbsTwigExtension
{
    protected $filters = [
        'resize',
        'productTags', // 此方法不建议使用
    ];

    public function resize($imagePath, $width = null, $height = null)
    {
        /** @var ModelToolImage $model */
        $model = load()->model('tool/image');
        return $model->resize($imagePath, $width, $height);
    }

    public function productTags($product, $glue = '')
    {
        $product = Product::find($product);
        if (!$product) return '';
        $tags = $product->tags->map(function ($tag) {
            return ImageToolTipWidget::widget([
                'tip' => $tag->description,
                'image' => $tag->icon,
            ])->render();
        })->toArray();
        return join($glue, $tags);
    }
}
