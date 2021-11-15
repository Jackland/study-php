<?php

namespace App\Components\RemoteApi\B2BManager\DTO\Freight;

use App\Components\RemoteApi\DTO\BaseDTO;

/**
 * @property-read float $packageFee 打包费
 * @property-read float $packageFeePure 纯物流打包费
 * @property-read float $total 总运费=运费+打包费
 * @property-read float $totalPure 纯物流总运费=运费+打包费
 */
class FreightPickUpDTO extends BaseDTO
{
    public function get($key, $default = null)
    {
        $value = parent::get($key, $default);
        return floatval($value);
    }
}
