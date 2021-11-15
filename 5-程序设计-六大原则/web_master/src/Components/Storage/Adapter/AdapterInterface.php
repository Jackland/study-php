<?php

namespace App\Components\Storage\Adapter;

use Symfony\Component\HttpFoundation\Response;

interface AdapterInterface
{
    /**
     * 获取 url 地址
     * @param $path
     * @return string
     */
    public function getUrl($path);

    /**
     * 重新调整宽高
     * @param $path
     * @param $width
     * @param $height
     * @return string 新的 path
     */
    public function resize($path, $width, $height);

    /**
     * 获取图片信息
     * @param $path
     * @return array [宽度, 高度, 类型]
     */
    public function getImageInfo($path);

    /**
     * 触发浏览器下载
     * @param $path
     * @param null|string $fileName
     * @return Response
     */
    public function browserDownload($path, $fileName = null);

    /**
     * 获取文件在本地的路径，若本地文件不存在，则下载到本地
     * @param $path
     * @return false|string
     */
    public function getLocalTempPath($path);

    /**
     * 某个文件在本地的临时文件
     * @param $path
     */
    public function deleteLocalTempFile($path);
}
