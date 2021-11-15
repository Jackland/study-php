<?php

namespace App\Components\Storage\Adapter;

use Framework\Helper\FileHelper;
use Kaysonwu\Flysystem\Aliyun\OssAdapter;
use OSS\OssClient;
use Symfony\Component\HttpFoundation\RedirectResponse;

class AliOss extends OssAdapter implements AdapterInterface
{
    /**
     * 配置
     * @var array
     */
    protected $config = [
        'localTmpPath' => '@runtime/alioss-local-tmp', // 本地临时存储的地址
        'isUrlSign' => false, // url 是否经过签名
        'urlSignTimeout' => 180, // url 签名的有效期
    ];
    /**
     * Url 参数的配置
     * 配置到该数组而非直接补足在path上是因为在签名模式下，直接补足会导致签名错误
     * @see OssClient::OSS_PROCESS 等
     * @var array
     */
    protected $urlOptions = [];

    private $endpoint;

    public function __construct($config)
    {
        foreach ($config as $k => $v) {
            if (isset($this->config[$k])) {
                $this->config[$k] = $v;
            }
        }
        $this->endpoint = $config['endpoint'];
        $client = new OssClient($config['ak'], $config['sk'], $config['endpoint'], $config['isCName'] ?? false);
        parent::__construct($client, $config['bucket'], $config['domain'], $config['prefix'] ?? null, $config['options'] ?? []);
    }

    /**
     * @inheritDoc
     */
    public function getUrl($path)
    {
        $urlOptions = $this->urlOptions;
        $this->urlOptions = []; // 每次获取url之后清除urlOptions配置

        // 特殊字符进行转译，否则会获取不到对应数据
        $path = strtr($path, [
            '+' => '%2B',
        ]);
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        if (in_array($ext, ['txt', 'text'])) {
            // txt 文档格式预览时设为 utf8，防止页面编码错误
            $urlOptions['response-content-type'] = 'text/plain; charset=utf8';
        }
        if ($this->config['isUrlSign']) {
            $signUrl = $this->temporaryUrl($path, $this->config['urlSignTimeout'], $urlOptions);
            // 将自动产出的 signUrl 中的 domain 改为自定义 domain
            $normalSignDomain = 'http://' . $this->bucket . '.' . $this->endpoint . '/';
            return str_replace($normalSignDomain, $this->domain, $signUrl);
        } else {
            if ($urlOptions) {
                $path .= (strpos($path, '?') === false ? '?' : '&') . http_build_query($urlOptions);
            }
            return parent::getUrl($path);
        }
    }

    /**
     * @inheritDoc
     */
    public function resize($path, $width, $height)
    {
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
        $this->urlOptions[OssClient::OSS_PROCESS] = implode(',', $options);
        return $this->getUrl($path);
    }

    /**
     * @inheritDoc
     */
    public function getImageInfo($path)
    {
        // @link https://help.aliyun.com/document_detail/44975.html?spm=a2c4g.11186623.6.1446.253fc1f6MM36Sc
        $info = file_get_contents($this->getUrl($path) . '?x-oss-process=image/info');
        $info = json_decode($info, true);
        if ($info) {
            return [$info['ImageWidth']['value'], $info['ImageHeight']['value'], $info['Format']['value']];
        }
        return [0, 0, 'jpg'];
    }

    /**
     * @inheritDoc
     */
    public function browserDownload($path, $fileName = null)
    {
        $oldConfig = $this->config;
        if ($fileName === null) {
            $fileName = basename($path);
        } else {
            // 需要修改默认下载名称时，必须开启 urlSign，此处临时开启
            $this->config['isUrlSign'] = true;
            $this->config['urlSignTimeout'] = 24*3600; // 尽量将该值调大，以防止重复下载时出现无效的问题
        }
        $this->urlOptions['response-content-type'] = 'application/octet-stream';
        if ($this->config['isUrlSign']) {
            // 仅在使用签名的形式下才支持该参数
            $fileName = strtr(urlencode($fileName), [
                '+' => ' '
            ]);
            $this->urlOptions['response-content-disposition'] = "attachment; filename=\"{$fileName}\"";
        }
        $response = new RedirectResponse($this->getUrl($path));
        $this->config = $oldConfig; // 重新改回原来的

        return $response;
    }

    /**
     * @inheritDoc
     */
    public function getLocalTempPath($path)
    {
        $filename = aliases(rtrim($this->config['localTmpPath']) . '/' . $this->getTempFileName($path));

        if (!file_exists($filename)) {
            $info = $this->read($path);
            if (!$info) {
                return false;
            }

            FileHelper::createDirectory(dirname($filename));
            file_put_contents($filename, $info['contents']);
        }
        return $filename;
    }

    /**
     * @inheritDoc
     */
    public function deleteLocalTempFile($path)
    {
        $filename = aliases(rtrim($this->config['localTmpPath']) . '/' . $this->getTempFileName($path));
        if (file_exists($filename)) {
            unlink($filename);
        }
    }

    /**
     * @param $path
     * @return string
     */
    protected function getTempFileName($path)
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        return md5($path) . ($extension ? ('.' . $extension) : '');
    }
}
