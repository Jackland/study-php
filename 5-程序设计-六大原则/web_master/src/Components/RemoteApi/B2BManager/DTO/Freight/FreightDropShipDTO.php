<?php

namespace App\Components\RemoteApi\B2BManager\DTO\Freight;

use App\Components\RemoteApi\DTO\BaseDTO;

/**
 * @property-read float $expressFreight 运费（包含了旺季附加费）
 * @property-read float $peakSeasonFee 旺季附加费
 * @property-read float $expressFreightPure 纯物流运费
 * @property-read float $peakSeasonFeePure 纯物流旺季附加费
 * @property-read float $dangerFee 危险品附加费
 * @property-read float $dangerFeePure 纯物流危险品附加费
 * @property-read float $ltlFreight 超大件运费
 * @property-read float $ltlFreightPure 纯物流超大件运费
 * @property-read float $packageFee 打包费
 * @property-read float $packageFeePure 纯物流打包费
 * @property-read float $total 总运费=运费+打包费
 * @property-read float $totalPure 纯物流总运费=运费+打包费
 */
class FreightDropShipDTO extends BaseDTO
{
    public function get($key, $default = null)
    {
        $value = parent::get($key, $default);
        return floatval($value);
    }
}
