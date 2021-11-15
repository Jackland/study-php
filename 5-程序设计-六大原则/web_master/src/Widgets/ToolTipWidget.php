<?php

namespace App\Widgets;

use Framework\Helper\Html;
use Framework\Widget\Widget;

class ToolTipWidget extends Widget
{
    public $tip;

    public $content = '';

    public $tag = 'span';

    public $options = [];

    public function run()
    {
        return Html::tag($this->tag, $this->content, array_merge([
            'data-toggle' => 'tooltip',
            'title' => $this->tip,
        ], $this->options));
    }
}
