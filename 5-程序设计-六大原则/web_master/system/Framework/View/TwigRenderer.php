<?php

namespace Framework\View;

use Framework\Debug\Collector\twig\TwigDataNotUse;
use Throwable;
use Twig_Environment;

class TwigRenderer implements ViewRendererInterface
{
    const TWIG_DATA_NOT_EXIST_CHECK_KEY = '___TWIG_DATA_NOT_EXIST_CHECK_KEY';

    /**
     * @var Twig_Environment
     */
    private $environment;
    /**
     * @var TwigDataNotUse|null
     */
    private $twigDataNotUse;

    public function __construct($environment, $twigDataNotUse = null)
    {
        $this->environment = $environment;
        $this->twigDataNotUse = $twigDataNotUse;
    }

    /**
     * @return Twig_Environment
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * @param $environment
     * @return $this
     */
    public function setEnvironment($environment)
    {
        $this->environment = $environment;

        return $this;
    }

    /**
     * @param $twigDataNotUse
     * @return $this
     */
    public function setTwigDataNotUse($twigDataNotUse)
    {
        $this->twigDataNotUse = $twigDataNotUse;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function render(ViewFactory $view, string $fullPath, string $viewPath, array $data): string
    {
        $environment = $this->environment;

        $this->collectDataNotUsed($viewPath, $data);
        unset($data[self::TWIG_DATA_NOT_EXIST_CHECK_KEY]);

        return $environment->render($viewPath, $data);
    }

    /**
     * 收集 twig 中的未使用的变量
     * @param string $viewPath
     * @param array $data
     * @throws \Twig_Error_Loader
     */
    private function collectDataNotUsed(string $viewPath, array $data)
    {
        if (!$this->twigDataNotUse) {
            return;
        }
        if (!isset($data[self::TWIG_DATA_NOT_EXIST_CHECK_KEY])) {
            return;
        }

        $needCheck = $data[self::TWIG_DATA_NOT_EXIST_CHECK_KEY];
        $data = array_filter($data, function ($key) use ($needCheck) {
            return in_array($key, $needCheck);
        }, ARRAY_FILTER_USE_KEY);
        if (!$data) {
            return;
        }

        $source = $this->getViewSource($viewPath);
        $keys = array_keys($data);
        $noUsedKeys = [];
        foreach ($keys as $key) {
            $key = str_replace('/', '\/', $key); // 防止 key 中存在 / 导致正则匹配错误的问题
            if (!preg_match($this->getAttributeUsePattern($key), $source)) {
                $noUsedKeys[] = $key;
            }
        }
        // 递归检查 include 模版
        $noUsedKeys = $this->collectDataNotUseCheckInclude($source, $noUsedKeys);
        if ($noUsedKeys) {
            $this->twigDataNotUse->addNotUse($viewPath, $noUsedKeys);
        }
    }

    private function collectDataNotUseCheckInclude(string $source, array $noUsedKeys)
    {
        if (!$noUsedKeys) {
            return [];
        }

        $noUsedKeysLeft = [];
        preg_match_all('/\{%\s*include\s*\'(.*)\'\s*%\}/i', $source, $matches);
        if (count($matches[1]) > 0) {
            foreach ($matches[1] as $viewFile) {
                $source = $this->getViewSource($viewFile);
                foreach ($noUsedKeys as $key) {
                    try {
                        if (!preg_match($this->getAttributeUsePattern($key), $source)) {
                            $noUsedKeysLeft[] = $key;
                        }
                    } catch (Throwable $e) {
                        dd('正则提取错误：' . $key);
                    }
                }
                $noUsedKeys = $this->collectDataNotUseCheckInclude($source, array_unique($noUsedKeysLeft));
            }
        }
        return $noUsedKeys;
    }

    /**
     * @param $attribute
     * @return string
     */
    private function getAttributeUsePattern($attribute)
    {
        // 匹配以下情况
        // {{ attribute }} 或 {{attribute}}
        // {% if attribute %}
        // {{ attribute.name }}} 或 {{ attribute['name'] }}} 或 {% if attribute==1 %} 或 {% if 1==attribute %}
        // 目前会匹配以下非预期的内容
        // title 会匹配：{{ logo }}" title="{{ name }}"
        return '/\{.*[\s|=]' . $attribute . '[\s|\.|\[|\=].*\}/';
    }

    /**
     * @param $viewFile
     * @return string
     * @throws \Twig_Error_Loader
     */
    private function getViewSource($viewFile)
    {
        $source = $this->environment->getLoader()->getSource($viewFile);
        // 移除所有注释代码，防止注释代码中存在变量和inclue
        return preg_replace('/\{#.*#\}/', '', $source);
    }
}
