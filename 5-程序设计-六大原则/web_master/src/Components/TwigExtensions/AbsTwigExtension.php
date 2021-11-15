<?php

namespace App\Components\TwigExtensions;

use Twig_Extension;

abstract class AbsTwigExtension extends Twig_Extension
{
    /**
     * 扩展的名字
     * @see getName()
     * @var string
     */
    protected $name;
    /**
     * 过滤器配置
     * @see getFilters()
     * @var array
     */
    protected $filters = [];
    /**
     * 方法配置
     * @see getFunctions()
     * @var array
     */
    protected $functions = [];

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return static::class;
    }

    /**
     * @inheritDoc
     */
    public function getFilters()
    {
        return $this->createWithSimpleConfig(\Twig_SimpleFilter::class, $this->filters);
    }

    /**
     * @inheritDoc
     */
    public function getFunctions()
    {
        return $this->createWithSimpleConfig(\Twig_SimpleFunction::class, $this->functions);
    }

    /**
     * @param string $class
     * @param array $config
     * @return array
     */
    protected function createWithSimpleConfig(string $class, array $config)
    {
        $result = [];

        foreach ($config as $name => $callable) {
            if (is_int($name)) {
                $name = $callable;
            }
            $result[] = new $class($name, [$this, $callable]);
        }

        return $result;
    }
}
