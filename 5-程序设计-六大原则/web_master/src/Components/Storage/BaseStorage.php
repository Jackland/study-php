<?php

namespace App\Components\Storage;

use App\Components\Storage\Adapter\AdapterInterface;
use App\Components\Storage\Traits\DownloadTrait;
use App\Components\Storage\Traits\ImageTrait;
use App\Components\Storage\Traits\LocalAdapterFixOldPathTrait;
use App\Components\Storage\Traits\StopRootWriteTrait;
use App\Components\Storage\Traits\UploadFileTrait;
use Framework\Storage\Storage;
use Framework\Storage\StorageManager;

/**
 * @method static static root() 根目录
 *
 * @mixin Storage
 */
abstract class BaseStorage
{
    private const ROOT_PATH = ''; // 根目录路径，必须为空字符串

    use ImageTrait;
    use DownloadTrait;
    use UploadFileTrait;
    use StopRootWriteTrait;
    use LocalAdapterFixOldPathTrait;

    protected $storage;
    protected $adapter;

    public function __construct(Storage $storage)
    {
        $this->storage = $storage;
        $this->adapter = $this->getAdapter();
    }

    /**
     * @return \League\Flysystem\AdapterInterface|AdapterInterface
     */
    public function getAdapter()
    {
        return $this->storage->getAdapter();
    }

    /**
     * 获取 url 地址
     * @param $path
     * @param array $config
     * @return string
     */
    public function getUrl($path, $config = [])
    {
        return $this->getImageUrl($path, $config);
    }

    /**
     * method 与实际相对路径的映射
     * 如：'aaa' => 'aa/bb'
     * 调用方法 Storage::aaa()->read('ccc.txt')，表示读取文件为 aa/bb/ccc.txt
     * 若方法名无映射，则相对路径为方法名
     * @return string[]
     */
    protected static function methodPathMap()
    {
        return [
            'root' => self::ROOT_PATH,
        ];
    }

    protected static $storageManagerCached = [];

    public static function __callStatic($name, $arguments)
    {
        $disk = static::class;
        $cacheKey = $disk . $name;
        if (!isset(static::$storageManagerCached[$cacheKey])) {
            $manager = clone app(StorageManager::class);
            $relativePath = static::methodPathMap()[$name] ?? $name;
            static::$storageManagerCached[$cacheKey] = new static($manager->disk($disk)->setPathPrefix($relativePath));
        }
        return static::$storageManagerCached[$cacheKey];
    }

    public function __call($method, $arguments)
    {
        return $this->storage->$method(...$arguments);
    }
}
