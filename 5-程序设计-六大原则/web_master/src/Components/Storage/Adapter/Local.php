<?php

namespace App\Components\Storage\Adapter;

use Framework\Exception\Http\ForbiddenException;
use League\Flysystem\Adapter\Local as LeagueLocal;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Throwable;

class Local extends LeagueLocal implements AdapterInterface
{
    /**
     * @var string
     */
    protected $absoluteRootPath;
    /**
     * false 表示不允许 url 访问
     * @var string|false
     */
    protected $baseUrl;
    /**
     * 域名，如：http://baidu.com
     * @var string
     */
    protected $domain;

    public function __construct(string $absoluteRootPath, string $urlRootPath, string $domain = '')
    {
        $this->absoluteRootPath = $absoluteRootPath;
        $this->baseUrl = $urlRootPath;
        if (!$domain) {
            $host = request()->server('HTTP_HOST');
            if ($host) {
                $this->domain = (request()->server('HTTPS') ? 'https://' : 'http://') . $host;
            }
        } else {
            $this->domain = rtrim($domain, '/');
        }
        LeagueLocal::__construct($absoluteRootPath);
    }

    /**
     * 切换 root 目录
     * @param string $root
     * @throws \League\Flysystem\Exception
     */
    public function changeRoot(string $root)
    {
        $this->absoluteRootPath = $root;
        $this->ensureDirectory($root);
        $this->setPathPrefix($root);
    }

    /**
     * 切换 root url 的路径
     * @param string $urlRootPath
     */
    public function changeBaseUrl(string $urlRootPath)
    {
        $this->baseUrl = $urlRootPath;
    }

    /**
     * @inheritDoc
     */
    public function getUrl($path)
    {
        if ($this->baseUrl === false) {
            throw new ForbiddenException('不允许 url 访问');
        }
        return $this->applyDomain(implode('/', array_filter([$this->baseUrl, $path])));
    }

    /**
     * @inheritDoc
     */
    public function resize($path, $width, $height)
    {
        list($widthOrigin, $heightOrigin, $imageType) = $this->getImageInfo($path);
        if ($widthOrigin === 0 || !in_array($imageType, [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF])) {
            // 异常或者 不支持的格式
            return $this->getUrl($path);
        }
        if ($widthOrigin == $width && $heightOrigin == $height) {
            // 宽高和需要调整的一致时
            return $this->getUrl($path);
        }
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        // 处理 resize
        $filenameNoExtension = ltrim(utf8_substr($path, 0, utf8_strrpos($path, '.')), '/');
        // resize 的缓存目录
        $newFilename = "{$filenameNoExtension}-{$width}x{$height}.{$extension}";
        $cachePath = aliases('@imageCache/' . $newFilename);
        if (!file_exists($cachePath)) { // 使用 file_exists 而非 $this->has 是因为 $cachePath 为绝对路径
            // 宽高不等，且没有缓存的
            $image = new \Image($this->getFileAbsoluteRealPath($path));
            $image->resize($width, $height);
            $this->ensureDirectory(dirname($cachePath));
            $image->save($cachePath);
        }
        return $this->applyDomain(aliases('@imageCacheUrl/' . $newFilename));
    }

    /**
     * @inheritDoc
     */
    public function getImageInfo($path)
    {
        try {
            list($widthOrigin, $heightOrigin, $imageType) = getimagesize($this->getFileAbsoluteRealPath($path));
            return [$widthOrigin, $heightOrigin, $imageType];
        } catch (Throwable $e) {
            // 异常时返回 0 值
            return [0, 0, 'jpg'];
        }
    }

    /**
     * @inheritDoc
     */
    public function browserDownload($path, $fileName = null)
    {
        $disposition = ResponseHeaderBag::DISPOSITION_ATTACHMENT;
        $response = new BinaryFileResponse($this->getFileAbsoluteRealPath($path), 200, [], true, $disposition);

        if ($fileName) {
            $response->setContentDisposition($disposition, $fileName);
        }

        return $response;
    }

    /**
     * @inheritDoc
     */
    public function getLocalTempPath($path)
    {
        return $this->getFileAbsoluteRealPath($path);
    }

    /**
     * @inheritDoc
     */
    public function deleteLocalTempFile($path)
    {
        // 本地文件不需要临时存储，因此不需要删除
    }

    /**
     * 获取文件的实际绝对路径
     * @param $path
     * @return string
     */
    protected function getFileAbsoluteRealPath($path)
    {
        return rtrim($this->absoluteRootPath, '/') . '/' . ltrim($path, '/');
    }

    /**
     * @param string $path
     * @return string
     */
    private function applyDomain(string $path)
    {
        if (!$this->domain) {
            return $path;
        }
        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
            return $path;
        }
        return $this->domain . '/' . ltrim($path, '/');
    }
}
