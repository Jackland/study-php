<?php

namespace App\Models\Seller\SellerStore\HomeModuleJson;

use InvalidArgumentException;

trait SellerBasedModuleTrait
{
    private $sellerId;

    /**
     * @inheritDoc
     */
    public function setSellerId(int $sellerId)
    {
        $this->sellerId = $sellerId;
    }

    /**
     * @inheritDoc
     */
    public function getSellerId(): int
    {
        if (!$this->sellerId) {
            throw new InvalidArgumentException('必须先调用 setSellerId');
        }
        return $this->sellerId;
    }
}
