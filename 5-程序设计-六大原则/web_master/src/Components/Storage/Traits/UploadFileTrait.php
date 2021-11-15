<?php

namespace App\Components\Storage\Traits;

use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * 处理上传的文件的相关操作
 */
trait UploadFileTrait
{
    /**
     * 写入上传的文件
     * @param UploadedFile $file
     * @param string $path 相对路径
     * @param null $name 指定文件名，默认会自动取名
     * @return string 相对于根目录的全路径
     */
    public function writeFile(UploadedFile $file, $path = '', $name = null): string
    {
        $name = $name ?: $this->generateUploadFilename($file);
        $fullPath = $this->buildPath($path, $name);
        $stream = fopen($file->getRealPath(), 'r');

        $this->writeStream($fullPath, fopen($file->getRealPath(), 'r'));

        if (is_resource($stream)) {
            fclose($stream);
        }

        return $this->getFullPath($fullPath);
    }

    /**
     * 创建上传文件的名字
     * @param UploadedFile $file
     * @return string
     */
    protected function generateUploadFilename(UploadedFile $file): string
    {
        $hash = Str::random(40);
        if ($extension = $file->guessClientExtension()) {
            $extension = '.' . $extension;
        }
        return $hash . $extension;
    }
}
