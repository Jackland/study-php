<?php

namespace App\Components\TwigExtensions;

use App\Widgets\DynamicWidget;
use Framework\Widget\Widget;
use InvalidArgumentException;

class ViewExtension extends AbsTwigExtension
{
    protected $functions = [
        'widget',
        'dynamicWidget',
        'assetBundle',
    ];

    public function widget($class, $config = [])
    {
        $class = $this->guessClass($class, ['App\\Widgets\\']);
        return $class::widget($config);
    }

    public function dynamicWidget($view, $params = [])
    {
        return DynamicWidget::widget([
            'view' => $view,
            'id' => $params['_id'] ?? null,
            'params' => $params,
        ]);
    }

    public function assetBundle($classes)
    {
        $data = [];
        foreach ((array)$classes as $class) {
            $data[] = $this->guessClass($class, ['App\\Assets\\']);
        }
        view()->registerAssets($data);
    }

    /**
     * @param string $class
     * @param array $namespaces
     * @return string|Widget
     */
    protected function guessClass($class, $namespaces = [])
    {
        if (class_exists($class)) {
            return $class;
        }

        foreach ($namespaces as $namespace) {
            if (class_exists($namespace . $class)) {
                return $namespace . $class;
            }
        }

        throw new InvalidArgumentException('Class not fount: ' . $class);
    }
}
