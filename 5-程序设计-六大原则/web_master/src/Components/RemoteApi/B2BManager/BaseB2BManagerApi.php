<?php

namespace App\Components\RemoteApi\B2BManager;

use App\Components\RemoteApi\BaseApi;
use App\Components\RemoteApi\Exceptions\ApiResponseException;
use App\Components\RemoteApi\Exceptions\ServeException;
use App\Components\RemoteApi\B2BManager\Exceptions\NoPermissionException;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

abstract class BaseB2BManagerApi extends BaseApi
{
    /**
     * @inheritDoc
     */
    protected function solveResponse(ResponseInterface $response)
    {
        try {
            $data = $response->toArray(true);
        } catch (Throwable $e) {
            if ($e->getCode() === 403) {
                throw new NoPermissionException($e->getMessage(), $e->getCode());
            }
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
        return B2B_MANAGEMENT_BASE_URL;
    }

    /**
     * @inheritDoc
     */
    protected function buildOptions(array $options): array
    {
        $options['headers'][] = 'Authorization: Bearer ' . B2B_MANAGEMENT_AUTH_TOKEN;
        return $options;
    }
}
