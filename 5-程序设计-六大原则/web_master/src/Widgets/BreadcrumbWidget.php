<?php

namespace App\Widgets;

use Framework\Helper\Html;
use Framework\Widget\Widget;

class BreadcrumbWidget extends Widget
{
    public $items = [];

    public function run()
    {
        $li = [];
        foreach ($this->items as $item) {
            $li[] = Html::tag('li', Html::a($item['text'], $item['href']));
        }
        $liStr = implode("\n", $li);

        return "<ul class='breadcrumb'>{$liStr}</ul>";
    }
}
