<?php

namespace App\Components\RemoteApi\YzcTaskWork;

use App\Components\RemoteApi\BaseApi;
use App\Components\RemoteApi\Exceptions\ApiResponseException;
use App\Components\RemoteApi\Exceptions\ServeException;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

class BaseYzcTaskWorkApi extends BaseApi
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
            throw new ApiResponseException($data['message'], $data['code']);
        }
        return $data['data'];
    }

    /**
     * @inheritDoc
     */
    protected function getBaseUri(): string
    {
        return URL_TASK_WORK . '/api';
    }

    /**
     * @inheritDoc
     */
    protected function api($uri, array $options = [], string $method = 'POST')
    {
        return parent::api($uri, $options, $method);
    }
}
