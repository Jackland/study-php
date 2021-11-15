<?php

namespace App\Components\Storage\Traits;

use App\Components\Storage\Adapter\AdapterInterface;
use App\Components\Traits\RequestCachedDataTrait;
use App\Logging\Logger;
use League\Flysystem\FilesystemException;

/**
 * 图片相关的操作
 * @property-read AdapterInterface $adapter
 */
trait ImageTrait
{
    use RequestCachedDataTrait;

    /**
     * 获取图片的 url 地址
     * @param $path
     * @param array $config
     * @return string
     */
    public function getImageUrl($path, $config = [])
    {
        $cacheKey = [__CLASS__, __FUNCTION__, func_get_args(), 'v1'];
        $cacheData = $this->getRequestCachedData($cacheKey);
        if ($cacheData !== null) {
            return $cacheData;
        }

        $config = array_merge($this->getImageUrlDefaultConfig(), $config);
        // 处理配置
        if ($config['w']) {
            if (!$config['h']) {
                $config['h'] = $config['w'];
            }
        }
        // 判断存在
        if ($config['check-exist']) {
            if (!$this->checkPathExist($path)) {
                if (!$config['no-image']) {
                    return '';
                }
                // 检查但不存在时，使用默认图片
                return $this->getNoImageIfConfigNotExists($config['no-image'], $config['w'], $config['h']);
            }
        }
        // 不存在时直接返回空
        if (!$path) {
            return '';
        }
        $path = $this->normalizePath($path);
        // resize
        if ($config['w']) {
            $url = $this->adapter->resize($path, $config['w'], $config['h']);
        } else {
            $url = $this->adapter->getUrl($path);
        }

        $this->setRequestCachedData($cacheKey, $url);
        return $url;
    }

    /**
     * 获取默认的获取图片 url 配置
     * @return array
     */
    private function getImageUrlDefaultConfig()
    {
        return $this->requestCachedData([__CLASS__, __FUNCTION__, 'v1'], function () {
            return [
                'w' => null, // 宽度
                'h' => null, // 高度，设置宽度后，若高度不设置，自动等于高度
                'check-exist' => config('storage.defaultCheckExistWhenGetUrl', true), // 检查文件是否存在，在非必要时设为false提高效率
                'no-image' => 'no_image.png', // 文件不存在时的返回，设置为空或者false时可以返回空值
            ];
        });
    }

    /**
     * 检查文件是否存在
     * @param $path
     * @return bool
     */
    private function checkPathExist($path)
    {
        $exist = $this->requestCachedData([__CLASS__, __FUNCTION__, $path, 'v1'], function () use ($path) {
            if (!$path || !$this->fileExists($path)) {
                if (defined('IMAGE_NOT_EXIST_LOG') && IMAGE_NOT_EXIST_LOG) {
                    Logger::imageCloud(['no image', $path, url()->current()], 'warning');
                }
                return 'not-exist';
            }
            return 'exist';
        });
        return $exist === 'exist';
    }

    /**
     * 获取的图片不存在时，调用该方法
     * @param $path
     * @param $width
     * @param $height
     * @return string
     */
    protected function getNoImageIfConfigNotExists($path, $width, $height)
    {
        // 获取不到图片时默认返回空
        return '';
    }

    /**
     * 是否是图片
     * @param $path
     * @return bool
     */
    public function isImage($path)
    {
        try {
            $mimeType = $this->mimeType($path);
            return substr($mimeType, 0, strlen('image/')) === 'image/';
        } catch (FilesystemException $e) {
            return false;
        }
    }

    /**
     * 获取图片的信息
     * @param $path
     * @return array [宽度, 高度, 类型]
     */
    public function getImageInfo($path)
    {
        return $this->adapter->getImageInfo($this->normalizePath($path));
    }
}
