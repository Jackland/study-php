<?php

namespace App\Components\Storage\Traits;

use App\Components\Storage\Adapter\AdapterInterface;

/**
 * 下载相关的操作
 * @property-read AdapterInterface $adapter
 */
trait DownloadTrait
{
    /**
     * 触发浏览器下载
     * @param $path
     * @param string|null $fileName
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function browserDownload($path, $fileName = null)
    {
        return $this->adapter->browserDownload($this->normalizePath($path), $fileName);
    }
}
