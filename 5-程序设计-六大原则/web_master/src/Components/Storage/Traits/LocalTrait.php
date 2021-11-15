<?php

namespace App\Components\Storage\Traits;

use App\Components\Storage\Adapter\AdapterInterface;

/**
 * 用于临时从云端下载文件到本地
 * @property-read AdapterInterface $adapter
 */
trait LocalTrait
{
    /**
     * 获取文件在本地的路径，若本地文件不存在，则下载到本地
     * @param $path
     * @return false|string
     */
    public function getLocalTempPath($path)
    {
        return $this->adapter->getLocalTempPath($this->normalizePath($path));
    }

    /**
     * 删除某个文件在本地的临时文件
     * @param $path
     */
    public function deleteLocalTempFile($path)
    {
        $this->adapter->deleteLocalTempFile($this->normalizePath($path));
    }
}
