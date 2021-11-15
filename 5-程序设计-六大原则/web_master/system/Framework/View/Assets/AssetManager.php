<?php

namespace Framework\View\Assets;

use Framework\Exception\InvalidConfigException;

class AssetManager
{
    private $publisher;

    /**
     * @var array [$bundleName => $bundleObj]
     */
    private $assetBundles = [];
    /**
     * @var array
     */
    private $jsFiles = [];
    /**
     * @var array
     */
    private $cssFiles = [];

    public function __construct(AssetPublisher $publisher)
    {
        $this->publisher = $publisher;
    }

    /**
     * 注册 AssetBundle
     * @param string|array $bundleNames
     */
    public function register($bundleNames)
    {
        foreach ((array)$bundleNames as $name) {
            $this->registerAssetBundle($name);
            $this->registerFiles($name);
        }
    }

    /**
     * 获取 js 文件
     * @return array [['url' => '', 'options' => []]]
     */
    public function getJsFiles()
    {
        return $this->jsFiles;
    }

    /**
     * 获取 css 文件
     * @return array [['url' => '', 'options' => []]]
     */
    public function getCssFiles()
    {
        return $this->cssFiles;
    }

    /**
     * 注册 Bundle 到 $this->assetBundles
     * 解决依赖关系
     * @param string $name
     * @return AssetBundle
     * @throws InvalidConfigException
     */
    private function registerAssetBundle(string $name)
    {
        if (!isset($this->assetBundles[$name])) {
            $bundle = $this->publisher->loadBundle($name);

            $this->assetBundles[$name] = false;

            foreach ($bundle->depends as $dep) {
                $this->registerAssetBundle($dep);
            }

            $this->assetBundles[$name] = $bundle;
        } elseif ($this->assetBundles[$name] === false) {
            throw new \RuntimeException("{$name} 不允许依赖自己");
        } else {
            $bundle = $this->assetBundles[$name];
        }

        return $bundle;
    }

    /**
     * 注册 bundle 中的资源文件到 $this->jsFiles 和  $this->cssFiles
     * 处理依赖
     * @param string $name
     */
    private function registerFiles(string $name)
    {
        if (!isset($this->assetBundles[$name])) {
            return;
        }

        $bundle = $this->assetBundles[$name];

        foreach ($bundle->depends as $dep) {
            $this->registerFiles($dep);
        }

        $this->registerAssetFiles($bundle);
    }

    /**
     * 注册 bundle 中的资源文件到 $this->jsFiles 和  $this->cssFiles
     * @param AssetBundle $bundle
     */
    private function registerAssetFiles(AssetBundle $bundle)
    {
        foreach ($bundle->js as $js) {
            $options = [];
            if (is_array($js)) {
                $url = array_shift($js);
                $options = $js;
                $js = $url;
            }
            $url = $this->publisher->getAssetUrl($bundle, $js);
            $options = array_merge($bundle->jsOptions, $options);

            $this->jsFiles[] = [
                'url' => $url,
                'options' => $options,
            ];
        }

        foreach ($bundle->css as $css) {
            $options = [];
            if (is_array($css)) {
                $url = array_shift($css);
                $options = $css;
                $css = $url;
            }
            $url = $this->publisher->getAssetUrl($bundle, $css);
            $options = array_merge($bundle->cssOptions, $options);
            $this->cssFiles[] = [
                'url' => $url,
                'options' => $options,
            ];
        }
    }
}
