<?php

namespace App\Components\RemoteApi\B2BManager;

use App\Components\RemoteApi\B2BManager\DTO\Freight\FreightDropShipDTO;
use App\Components\RemoteApi\B2BManager\DTO\Freight\FreightDTO;
use App\Components\RemoteApi\B2BManager\DTO\Freight\FreightPickUpDTO;
use App\Components\RemoteApi\B2BManager\DTO\Freight\FreightProductDTO;
use App\Components\RemoteApi\B2BManager\DTO\Freight\FreightWareHouseRentalDTO;
use App\Components\RemoteApi\B2BManager\Exceptions\NoPermissionException;
use App\Components\RemoteApi\Exceptions\ApiResponseException;
use App\Components\RemoteApi\Exceptions\ServeException;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

class FreightApi extends BaseB2BManagerApi
{
    /**
     * 获取b2b manager运费报价
     * @param FreightProductDTO $productInfo 请求产品详情，字段参考对象
     * @param int $customerId 用户ID
     * @return FreightDTO 获取成功会返回该对象
     * @throws Throwable 如果获取失败或者获取数据错误，将会抛出异常
     */
    public function getFreight(FreightProductDTO $productInfo,int $customerId): FreightDTO
    {
        $params = $productInfo->toArray();
        $params['customerId'] = $customerId;
        $data = $this->api('/api/freight/calculate', [
            'json' => $params,
        ], 'POST');
        // 封装对象
        return new FreightDTO([
            'ltlFlag' => $data['ltlFlag'],
            'dropShip' => new FreightDropShipDTO($data['dropShip']),
            'pickUp' => new FreightPickUpDTO($data['pickUp']),
            'wareHouseRental' => new FreightWareHouseRentalDTO($data['wareHouseRental']),
        ]);
    }

    /**
     * @throws ServeException
     * @throws ApiResponseException
     * @throws NoPermissionException
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
        if (!isset($data['code']) || $data['code'] !== 200 || empty($data['data'])) {
            throw new ApiResponseException($data['msg'], $data['code']);
        }
        return $data['data'];
    }
}
