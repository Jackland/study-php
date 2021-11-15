<?php

namespace App\Models\Seller\SellerStore\HomeModuleJson;

use InvalidArgumentException;

/**
 * 基于 Seller 的模块
 */
interface SellerBasedModuleInterface
{
    /**
     * 设置 sellerId
     * @param int $sellerId
     * @return void
     */
    public function setSellerId(int $sellerId);

    /**
     * @return int
     * @throws InvalidArgumentException
     */
    public function getSellerId(): int;
}
