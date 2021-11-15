<?php

namespace App\Components\RemoteApi\B2BManager\DTO;

use App\Components\RemoteApi\DTO\BaseDTO;
use Framework\Helper\Json;
use JsonException;

/**
 * @property-read int $menuId 资源主表ID
 * @property-read int $subId 资源子表ID
 * @property-read string $fileName 原文件名
 * @property-read string $filePath oss文件全路径（url）
 * @property-read float $fileSize 文件大小，单位 kB
 * @property-read string $fileSuffix 文件后缀，不带.
 * @property-read string|null $reservedField 预留字段，存储针对该文件的额外信息
 * @property-read string $relativePath 相对路径
 * @property-read string $downloadUrl 下载的url
 */
class FileDTO extends BaseDTO
{
    /**
     * @return mixed|null
     */
    public function getExtra()
    {
        try {
            return Json::decode($this->reservedField);
        } catch (JsonException $e) {
            return $this->reservedField;
        }
    }

    /**
     * 获取文件大小
     * @param string $unit
     */
    public function getSize($unit = 'kB')
    {
        // 暂未实现
    }

    /**
     * 调整图片文件的大小，获取新的url
     * @param $width
     * @param $height
     * @return string
     */
    public function imageResize($width, $height): string
    {
        $path = $this->filePath;
        // @link https://help.aliyun.com/document_detail/44688.html?spm=a2c4g.11186623.6.1426.68961b76wBuMZb
        $options = [
            'image/resize',
            'w_' . $width,
            'h_' . $height,
        ];
        if (pathinfo($path, PATHINFO_EXTENSION) === 'gif') {
            // gif 不支持 m_pad
            // @link https://help.aliyun.com/document_detail/44688.html?spm=a2c4g.11186623.6.1426.68961b76wBuMZb#title-i3s-log-qvf
            $options[] = 'm_lfit';
        } else {
            $options[] = 'm_pad';
        }
        return $path . (strpos($path, '?') === false ? '?' : '&') . http_build_query($options);
    }
}
