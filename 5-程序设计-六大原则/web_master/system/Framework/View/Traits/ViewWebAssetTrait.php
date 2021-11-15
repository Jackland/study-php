<?php

namespace Framework\View\Traits;

use Framework\Exception\InvalidConfigException;
use Framework\Helper\Html;
use Framework\View\Assets\AssetManager;
use Framework\View\Enums\ViewWebPosition;

/**
 * Web 视图资源相关的功能
 */
trait ViewWebAssetTrait
{
    private $scripts = [];
    private $styles = [];
    private $jsFiles = [];
    private $cssFiles = [];

    /**
     * 设置 script
     * @param string|array $js
     * @param int $position
     */
    public function script($js, int $position = ViewWebPosition::BODY_END): void
    {
        foreach ((array)$js as $item) {
            $key = md5($item);
            $this->scripts[$position][$key] = $item;
        }
    }

    /**
     * 渲染 script
     * @param int $position
     * @return string
     */
    public function renderScript(int $position): string
    {
        if (!isset($this->scripts[$position])) {
            return '';
        }
        return Html::tag('script', implode("\n", $this->scripts[$position]));
    }

    /**
     * 设置 style
     * @param string|array $css
     * @param int $position
     */
    public function style($css, int $position = ViewWebPosition::HEAD): void
    {
        foreach ((array)$css as $item) {
            $key = md5($item);
            $this->styles[$position][$key] = $item;
        }
    }

    /**
     * 渲染 style
     * @param int $position
     * @return string
     */
    public function renderStyle(int $position): string
    {
        if (!isset($this->styles[$position])) {
            return '';
        }
        return Html::tag('style', implode("\n", $this->styles[$position]));
    }

    /**
     * 设置 js url
     * @param string|array $url
     * @param array $options
     * @param int $position
     */
    public function js($url, array $options = [], int $position = ViewWebPosition::BODY_END)
    {
        if (isset($options['position'])) {
            $position = $options['position'];
            unset($options['position']);
        }
        foreach ((array)$url as $item) {
            $key = $item;
            $this->jsFiles[$position][$key] = [$item, $options];
        }
    }

    /**
     * 渲染 js
     * @param int $position
     * @return string
     */
    public function renderJs(int $position): string
    {
        if (!isset($this->jsFiles[$position])) {
            return '';
        }
        $contents = [];
        foreach ($this->jsFiles[$position] as $item) {
            $contents[] = Html::jsFile($item[0], $item[1]);
        }
        return implode("\n", $contents);
    }

    /**
     * 设置 css url
     * @param string|array $url
     * @param array $options
     * @param int $position
     */
    public function css($url, array $options = [], int $position = ViewWebPosition::HEAD)
    {
        $position  = $options['position'] ?? $position;
        foreach ((array)$url as $item) {
            $key = $item;
            $this->cssFiles[$position][$key] = [$item, $options];
        }
    }

    /**
     * 渲染 css
     * @param int $position
     * @return string
     */
    public function renderCss(int $position): string
    {
        if (!isset($this->cssFiles[$position])) {
            return '';
        }
        $contents = [];
        foreach ($this->cssFiles[$position] as $item) {
            $contents[] = Html::cssFile($item[0], $item[1]);
        }
        return implode("\n", $contents);
    }

    /**
     * @var AssetManager|null
     */
    private $assetManager;

    /**
     * @param AssetManager $assetManager
     * @return $this
     */
    public function setAssetManager(AssetManager $assetManager)
    {
        $this->assetManager = $assetManager;
        return $this;
    }

    /**
     * 通过 AssetBundle 注册 js 和 css
     * @param $assets
     * @throws InvalidConfigException
     */
    public function registerAssets($assets)
    {
        if (!$this->assetManager) {
            throw new InvalidConfigException('不支持注册 AssetBundle，必须先配置 assetManager，使用 setAssetManager()');
        }
        $this->assetManager->register($assets);
        foreach ($this->assetManager->getJsFiles() as $item) {
            $this->js($item['url'], $item['options']);
        }
        foreach ($this->assetManager->getCssFiles() as $item) {
            $this->css($item['url'], $item['options']);
        }
    }
}
