<?php

namespace App\Widgets;

use Framework\Helper\Html;
use Framework\Widget\Widget;

class ImageToolTipWidget extends Widget
{
    public $tip;

    public $image;

    public $width = 'auto';

    public $class;

    public $options = [
        'style' => 'padding-left: 2px',
    ];

    public function run()
    {
        return Html::img('image/' . $this->image, array_merge([
            'data-toggle' => 'tooltip',
            'title' => $this->tip,
            'width' => $this->width,
            'class' => $this->class
        ], $this->options));
    }
}
