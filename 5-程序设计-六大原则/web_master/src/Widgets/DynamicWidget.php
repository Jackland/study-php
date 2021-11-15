<?php

namespace App\Widgets;

use Framework\Helper\Html;
use Framework\Widget\Widget;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * 动态组件，用于前端快速构建 widget
 */
class DynamicWidget extends Widget
{
    public $view;
    public $id;
    public $params = [];

    /**
     * @inheritDoc
     */
    public function run()
    {
        try {
            if (!$this->view) {
                throw new InvalidArgumentException('view 必须配置');
            }
            if (!$this->id) {
                $this->id = Str::random(6);
            }
            if (!Str::startsWith($this->view, '@')) {
                $this->view = '@widgets/' . $this->view;
            }

            if (!isset($this->params['_id'])) {
                $this->params['_id'] = $this->id;
            }
            return $this->getView()->render($this->view, $this->params);
        } catch (Throwable $e) {
            return Html::tag('div', 'TWIG ERROR: ' . $e->getMessage(), ['style' => 'color: red']);
        }
    }
}
