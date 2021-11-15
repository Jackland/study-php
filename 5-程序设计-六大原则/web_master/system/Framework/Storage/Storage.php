<?php

namespace Framework\Storage;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use Throwable;

/**
 * 使用的是 flysystem 2.0 的 api，实现目前使用的是 1.0 版本的实现，后续可以跟进到 2.0
 */
class Storage implements StorageInterface
{
    protected $filesystem;

    private $pathPrefix;

    public function __construct(AdapterInterface $adapter, Config $config)
    {
        $this->filesystem = new Filesystem($adapter, $config);
    }

    /**
     * 设置相对路径
     * @param string|null $path
     * @return Storage
     */
    public function setPathPrefix(?string $path): self
    {
        $this->pathPrefix = $path ?: '';

        return $this;
    }

    /**
     * 获取相对路径
     * @return string
     */
    public function getPathPrefix(): string
    {
        return $this->pathPrefix;
    }

    /**
     * @return Filesystem
     */
    public function getFilesystem()
    {
        return $this->filesystem;
    }

    /**
     * @return AdapterInterface
     */
    public function getAdapter()
    {
        return $this->filesystem->getAdapter();
    }

    /**
     * 构建路径，按照数组传递各个路径即可，可以拼接成最终使用的路径
     * ['aaa/', 'bbb/ccc', '', '/dd\ee'] 可以得到结果 'aaa/bbb/ccc/dd/ee'
     * @param mixed ...$paths
     * @return string
     */
    public function buildPath(...$paths)
    {
        $startWithSeparator = isset($paths[0][0]) && $paths[0][0] === '/';
        return ($startWithSeparator ? '/' : '') . implode('/', array_map(function ($path) {
                return ltrim(str_replace('\\', '/', $path), '/');
            }, $paths));
    }

    /**
     * 格式化为相对根目录的路径
     * @param string $path 相对路径，如果设置了 relativePath 则为相对 relative 的路径，否则为相对根目录
     * @return string
     */
    public function normalizePath(string $path)
    {
        return $this->buildPath($this->getPathPrefix(), $path);
    }

    /**
     * 根据相对路径获取全路径
     * @param string $relativePath
     * @return string
     */
    public function getFullPath(string $relativePath)
    {
        return $this->normalizePath($relativePath);
    }

    /**
     * 根据全路径获取相对路径
     * @param string $fullPath
     * @return string
     */
    public function getRelativePath(string $fullPath)
    {
        $pathPrefix = $this->getPathPrefix();
        if (!$pathPrefix) {
            return $fullPath;
        }
        $fullPath = ltrim($fullPath, '/');
        if (strpos($fullPath, $pathPrefix) !== 0) {
            return $fullPath;
        }
        return ltrim(mb_substr($fullPath, mb_strlen($pathPrefix) + 1), '/');
    }

    /**
     * @inheritDoc
     */
    public function fileExists(string $location): bool
    {
        return $this->getFilesystem()->has($this->normalizePath($location));
    }

    /**
     * @inheritDoc
     */
    public function read(string $location): string
    {
        return $this->getFilesystem()->read($this->normalizePath($location));
    }

    /**
     * @inheritDoc
     */
    public function readStream(string $location)
    {
        return $this->getFilesystem()->readStream($this->normalizePath($location));
    }

    /**
     * 注意返回之后的 path 为相对 root 目录的值
     * @inheritDoc
     * [
     * [type] =&gt; file 或 dir
     * [path] =&gt; image/wkseller/26/20200429_5a83910b72a060097952c4032d3f66a2.png
     * [timestamp] =&gt; 1588225243
     * [size] =&gt; 64632
     * [dirname] =&gt; image/wkseller/26
     * [basename] =&gt; 20200429_5a83910b72a060097952c4032d3f66a2.png
     * [extension] =&gt; png  // dir 时不存在
     * [filename] =&gt; 20200429_5a83910b72a060097952c4032d3f66a2
     * ]
     * ]
     */
    public function listContents(string $location, bool $deep = false)
    {
        return $this->getFilesystem()->listContents($this->normalizePath($location), $deep);
    }

    /**
     * @inheritDoc
     */
    public function lastModified(string $path): int
    {
        return $this->getFilesystem()->getTimestamp($this->normalizePath($path));
    }

    /**
     * @inheritDoc
     */
    public function fileSize(string $path): int
    {
        return $this->getFilesystem()->getSize($this->normalizePath($path));
    }

    /**
     * @inheritDoc
     */
    public function mimeType(string $path): string
    {
        return $this->getFilesystem()->getMimetype($this->normalizePath($path));
    }

    /**
     * @inheritDoc
     */
    public function visibility(string $path): string
    {
        return $this->getFilesystem()->getVisibility($this->normalizePath($path));
    }

    /**
     * @inheritDoc
     */
    public function write(string $location, string $contents, array $config = []): void
    {
        try {
            $isOk = $this->getFilesystem()->put($this->normalizePath($location), $contents, $config);
        } catch (Throwable $e) {
            $isOk = false;
        }
        if (!$isOk) {
            throw new UnableToWriteFile();
        }
    }

    /**
     * @inheritDoc
     */
    public function writeStream(string $location, $contents, array $config = []): void
    {
        try {
            $isOk = $this->getFilesystem()->putStream($this->normalizePath($location), $contents, $config);
        } catch (Throwable $e) {
            $isOk = false;
        }
        if (!$isOk) {
            throw new UnableToWriteFile();
        }
    }

    /**
     * @inheritDoc
     */
    public function setVisibility(string $path, string $visibility): void
    {
        try {
            $isOk = $this->getFilesystem()->setVisibility($this->normalizePath($path), $visibility);
        } catch (Throwable $e) {
            $isOk = false;
        }
        if (!$isOk) {
            throw new UnableToSetVisibility();
        }
    }

    /**
     * @inheritDoc
     */
    public function delete(string $location): void
    {
        try {
            $isOk = $this->getFilesystem()->delete($this->normalizePath($location));
        } catch (Throwable $e) {
            $isOk = false;
        }
        if (!$isOk) {
            throw new UnableToDeleteFile();
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteDirectory(string $location): void
    {
        try {
            $isOk = $this->getFilesystem()->deleteDir($this->normalizePath($location));
        } catch (Throwable $e) {
            $isOk = false;
        }
        if (!$isOk) {
            throw new UnableToDeleteDirectory();
        }
    }

    /**
     * @inheritDoc
     */
    public function createDirectory(string $location, array $config = []): void
    {
        try {
            $isOk = $this->getFilesystem()->createDir($this->normalizePath($location), $config);
        } catch (Throwable $e) {
            $isOk = false;
        }
        if (!$isOk) {
            throw new UnableToCreateDirectory();
        }
    }

    /**
     * @inheritDoc
     */
    public function move(string $source, string $destination, array $config = []): void
    {
        try {
            $isOk = $this->getFilesystem()->rename($this->normalizePath($source), $this->normalizePath($destination));
        } catch (Throwable $e) {
            $isOk = false;
        }
        if (!$isOk) {
            throw new UnableToMoveFile();
        }
    }

    /**
     * @inheritDoc
     */
    public function copy(string $source, string $destination, array $config = []): void
    {
        try {
            $isOk = $this->getFilesystem()->copy($this->normalizePath($source), $this->normalizePath($destination));
        } catch (Throwable $e) {
            $isOk = false;
        }
        if (!$isOk) {
            throw new UnableToCopyFile();
        }
    }
}
