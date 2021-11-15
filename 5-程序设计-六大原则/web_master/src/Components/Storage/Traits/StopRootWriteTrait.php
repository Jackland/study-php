<?php

namespace App\Components\Storage\Traits;

use Framework\Storage\Storage;
use Framework\Storage\UnableToWriteFile;

/**
 * 禁止向 root 目录写入文件
 * @property-read Storage $storage
 */
trait StopRootWriteTrait
{
    /**
     * 增加不允许 root 写入
     * @param string $location
     * @param $contents
     * @param array $config
     * @throws UnableToWriteFile
     * @see Storage::writeStream()
     */
    public function writeStream(string $location, $contents, array $config = [])
    {
        if ($this->storage->getPathPrefix() === self::ROOT_PATH) {
            throw new UnableToWriteFile('不允许使用 root 写入文件');
        }
        $this->storage->writeStream($location, $contents, $config);
    }

    /**
     * 增加不允许 root 写入
     * @param string $location
     * @param $contents
     * @param array $config
     * @throws UnableToWriteFile
     * @see Storage::write()
     */
    public function write(string $location, $contents, array $config = [])
    {
        if ($this->storage->getPathPrefix() === self::ROOT_PATH) {
            throw new UnableToWriteFile('不允许使用 root 写入文件');
        }
        $this->storage->write($location, $contents, $config);
    }
}
