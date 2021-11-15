<?php

namespace Framework\View\Assets;

use Framework\Aliases\Aliases;
use Framework\Exception\InvalidConfigException;
use Framework\View\Util;
use Illuminate\Filesystem\Filesystem;

class AssetPublisher
{
    private $cssDefaultOptions = [];
    private $jsDefaultOptions = [];

    /**
     * @var Aliases
     */
    private $aliases;
    /**
     * @var Filesystem
     */
    private $filesystem;
    /**
     * 发布资源的本地基础路径
     * @var string
     */
    private $assetBasePath;
    /**
     * 发布资源的基础 url
     * @var string
     */
    private $assetBaseUrl;
    /**
     * 强制复制
     * @var bool
     */
    private $forceCopy = false;
    /**
     * 追加时间戳
     * @var bool
     */
    private $appendTimestamp = false;

    public function __construct(string $assetBasePath, string $assetBaseUrl, Aliases $aliases, Filesystem $filesystem)
    {
        $this->assetBasePath = $assetBasePath;
        $this->assetBaseUrl = $assetBaseUrl;
        $this->aliases = $aliases;
        $this->filesystem = $filesystem;
    }

    /**
     * 强制复制
     * @param bool|null $bool
     * @return $this
     * @see publishDirectory
     */
    public function setForceCopy(?bool $bool)
    {
        if ($bool === null) {
            return $this;
        }
        $this->forceCopy = $bool;
        return $this;
    }

    /**
     * 自动追加资源的 timestamp
     * @param bool|null $bool
     * @return $this
     * @see getAssetUrl
     */
    public function setAppendTimestamp(?bool $bool)
    {
        if ($bool === null) {
            return $this;
        }
        $this->appendTimestamp = $bool;
        return $this;
    }

    /**
     * 根据 bundleName 获取 bundle
     * @param string $bundleName
     * @param array $config
     * @return AssetBundle
     * @throws InvalidConfigException
     */
    public function loadBundle(string $bundleName, $config = []): AssetBundle
    {
        /** @var AssetBundle $bundle */
        $bundle = new $bundleName();

        foreach ($config as $property => $value) {
            $bundle->$property = $value;
        }
        $bundle->cssOptions = array_merge($bundle->cssOptions, $this->cssDefaultOptions);
        $bundle->jsOptions = array_merge($bundle->jsOptions, $this->jsDefaultOptions);

        if ($bundle->sourcePath) {
            [$bundle->basePath, $bundle->baseUrl] = $this->publish($bundle);
        }

        if (!$bundle->basePath || !$bundle->baseUrl) {
            $bundleClass = get_class($bundle);
            throw new InvalidConfigException("必须配置 basePath 和 baseUrl 或 sourcePath: {$bundleClass}");
        }

        return $bundle;
    }

    /**
     * 获取资源的 url 地址
     * @param AssetBundle $bundle
     * @param string $filePath
     * @return string
     */
    public function getAssetUrl(AssetBundle $bundle, string $filePath)
    {
        if (Util::isHttpUrl($filePath)) {
            return $filePath;
        }

        $basePath = $this->aliases->get($bundle->basePath);
        $baseUrl = $this->aliases->get($bundle->baseUrl);

        if ($this->appendTimestamp) {
            $timestamp = @filemtime("{$basePath}/{$filePath}");
            if ($timestamp > 0) {
                $pos = strpos($filePath, '?') === false ? '?' : '&';
                return "{$baseUrl}/{$filePath}{$pos}v={$timestamp}";
            }
        }

        return "{$baseUrl}/{$filePath}";
    }

    private $_published = [];

    /**
     * 发布资源
     * @param AssetBundle $bundle
     * @return array [目录绝对路径, 目录url路径]
     * @throws InvalidConfigException
     */
    private function publish(AssetBundle $bundle)
    {
        if (!$bundle->sourcePath) {
            $bundleClass = get_class($bundle);
            throw new InvalidConfigException("sourcePath 必须配置: {$bundleClass}");
        }

        if (!isset($this->_published[$bundle->sourcePath])) {
            $sourcePath = $this->aliases->get($bundle->sourcePath);
            if (!is_dir($sourcePath)) {
                throw new InvalidConfigException("sourcePath 不存在: {$bundle->sourcePath}");
            }

            $this->_published[$bundle->sourcePath] = $this->publishDirectory($sourcePath, $bundle->publishOptions);
        }

        return $this->_published[$bundle->sourcePath];
    }

    /**
     * 发布目录
     * @param string $sourcePath 绝对路径
     * @param array $publishOptions
     * @return array [目录绝对路径, 目录url路径]
     */
    private function publishDirectory(string $sourcePath, array $publishOptions)
    {
        $dir = $this->hash($sourcePath);
        $dstDir = $this->aliases->get($this->assetBasePath . '/' . $dir);

        if (
            (isset($publishOptions['forceCopy']) && $publishOptions['forceCopy'])
            || $this->forceCopy
            || !is_dir($dstDir)
        ) {
            $this->filesystem->copyDirectory($sourcePath, $dstDir);
        }

        return [$dstDir, $this->aliases->get($this->assetBaseUrl . '/' . $dir)];
    }

    /**
     * @param string $sourcePath
     * @return string
     */
    private function hash(string $sourcePath)
    {
        return hash('md4', $sourcePath);
    }
}
