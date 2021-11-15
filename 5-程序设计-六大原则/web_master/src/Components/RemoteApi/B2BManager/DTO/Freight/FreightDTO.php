<?php

namespace App\Components\RemoteApi\B2BManager\DTO\Freight;

use App\Components\RemoteApi\DTO\BaseDTO;

/**
 * @property-read bool $ltlFlag 是否超大件
 * @property-read FreightDropShipDTO $dropShip 一件代发
 * @property-read FreightPickUpDTO $pickUp 上门取货
 * @property-read FreightWareHouseRentalDTO $wareHouseRentalDTO 仓租
 */
class FreightDTO extends BaseDTO
{
}
