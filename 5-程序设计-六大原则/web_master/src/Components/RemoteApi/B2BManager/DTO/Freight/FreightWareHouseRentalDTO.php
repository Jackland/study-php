<?php

namespace App\Components\RemoteApi\B2BManager\DTO\Freight;

use App\Components\RemoteApi\DTO\BaseDTO;

/**
 * @property-read float $volume 仓租体积
 * @property-read array|float[] $feeList 仓租费用阶梯报价
 * @property-read float $feeTotal 总仓租费用，feeList值之和
 */
class FreightWareHouseRentalDTO extends BaseDTO
{
}
