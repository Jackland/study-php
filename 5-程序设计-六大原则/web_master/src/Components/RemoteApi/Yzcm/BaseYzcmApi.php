<?php

namespace App\Components\RemoteApi\Yzcm;

use App\Components\RemoteApi\BaseApi;
use App\Components\RemoteApi\Exceptions\ApiResponseException;
use App\Components\RemoteApi\Exceptions\ServeException;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

class BaseYzcmApi extends BaseApi
{
    /**
     * @inheritDoc
     */
    protected function solveResponse(ResponseInterface $response)
    {
        try {
            $data = $response->toArray(true);
        } catch (Throwable $e) {
            throw new ServeException($e->getMessage(), $e->getCode());
        }

        if ($data['code'] !== 200) {
            throw new ApiResponseException($data['msg'], $data['code']);
        }
        return $data['data'];
    }

    /**
     * @inheritDoc
     */
    protected function getBaseUri(): string
    {
        return URL_YZCM;
    }

    /**
     * @inheritDoc
     */
    protected function buildOptions(array $options): array
    {
        // yzcm 实际默认需要 basic auth 授权
        // 但是很多接口都通过白名单进行忽略授权，因此后续添加接口时记得在 yzcm 中添加白名单
        return parent::buildOptions($options);
    }

    /**
     * @inheritDoc
     */
    protected function api($uri, array $options = [], string $method = 'POST')
    {
        return parent::api($uri, $options, $method);
    }
}
