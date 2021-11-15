<?php

namespace Framework\View\Assets;

class AssetBundle
{
    /**
     * 资源的路径，在资源为第三方资源时使用
     * 会使用 publisher 发布资源
     * @see AssetPublisher::publish()
     * @var string
     */
    public $sourcePath;
    /**
     * 发布时的配置
     * @see AssetPublisher::publishDirectory()
     * @var array
     */
    public $publishOptions = [];
    /**
     * 基础路径
     * 配置 sourcePath 后无效
     * @var string
     */
    public $basePath;
    /**
     * 基础 url 路径
     * 配置 sourcePath 后无效
     * @var string
     */
    public $baseUrl;
    /**
     * js 文件名，相对 sourcePath 或 basePath
     * @see AssetManager::registerAssetFilesToView()
     * @var array
     */
    public $js = [];
    /**
     * css 文件名，相对 sourcePath 或 basePath
     * @see AssetManager::registerAssetFilesToView()
     * @var array
     */
    public $css = [];
    /**
     * 依赖，AssetBundle 的 classname
     * @var array
     */
    public $depends = [];
    /**
     * 本 Bundle 默认的 css 配置
     * @see AssetManager::registerAssetFilesToView()
     * @var array
     */
    public $cssOptions = [];
    /**
     * 本 Bundle 默认的 js 配置
     * @see AssetManager::registerAssetFilesToView()
     * @var array
     */
    public $jsOptions = [];
}
