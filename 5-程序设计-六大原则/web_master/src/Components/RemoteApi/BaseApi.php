<?php

namespace App\Components\RemoteApi;

use App\Logging\Logger;
use Framework\View\Util;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

abstract class BaseApi
{
    /**
     * 接口请求
     * @param $uri
     * @param array $options
     * @param string $method
     * @return mixed
     */
    protected function api($uri, array $options = [], string $method = 'GET')
    {
        $options = array_merge([
            'max_redirects' => 0, // 默认不允许自动重定向
        ], $options);

        $options = $this->buildOptions($options);

        $response = $this->getHttpClient()->request(strtoupper($method), $this->buildUrl($uri), $options);

        try {
            $result = $this->solveResponse($response);
            $this->logRequest($uri, $method, $options, $response);
            return $result;
        } catch (Throwable $e) {
            $this->logRequest($uri, $method, $options, $response, 'error', $e);
            throw $e;
        }
    }

    /**
     * 扩展 options，一般处理授权等逻辑
     * @param array $options
     * @return array
     */
    protected function buildOptions(array $options): array
    {
        return $options;
    }

    /**
     * 统一处理响应结果
     * @param ResponseInterface $response
     * @return mixed
     */
    abstract protected function solveResponse(ResponseInterface $response);

    protected $httpClient;

    /**
     * @return \Symfony\Contracts\HttpClient\HttpClientInterface
     */
    protected function getHttpClient()
    {
        if ($this->httpClient) {
            return $this->httpClient;
        }

        $this->httpClient = HttpClient::create();

        return $this->httpClient;
    }

    /**
     * 基础 url 地址
     * @return string
     */
    abstract protected function getBaseUri(): string;

    /**
     * 构建完整的 url 地址
     * @param string $uri
     * @param array $params query 参数
     * @return string
     */
    protected function buildUrl(string $uri, array $params = []): string
    {
        $url = Util::buildPath($this->getBaseUri(), $uri);
        if ($params) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($params);
        }
        return $url;
    }

    /**
     * 记录错误请求日志
     * @param string $uri
     * @param string $method
     * @param array $options
     * @param ResponseInterface $response
     * @param string $logType
     */
    private function logRequest(string $uri, string $method, array $options, ResponseInterface $response, string $logType = 'info', Throwable $e = null)
    {
        $logOptions = [];
        foreach ($options as $key => $value) {
            if ($key === 'max_redirects' && $value === 0) {
                // 忽略默认参数
                continue;
            }
            if ($key === 'headers') {
                // 忽略header
                continue;
            }
            if ($key === 'body') {
                if (is_iterable($value) && !is_array($value)) {
                    // 忽略body传的文件
                    continue;
                }
            }
            $logOptions[$key] = $value;
        }
        $log = [
            'class' => get_called_class(),
            'request' => [
                'uri' => $uri,
                'method' => $method,
                'options' => $logOptions,
            ],
        ];
        if ($e) {
            $log['exception'] = $e->getMessage();
            Logger::remoteAPI($log, $logType);
            Logger::remoteAPI($e, $logType);
        } else {
            $log['response'] = $response->getContent(false);
            Logger::remoteAPI($log, $logType);
        }
    }
}
