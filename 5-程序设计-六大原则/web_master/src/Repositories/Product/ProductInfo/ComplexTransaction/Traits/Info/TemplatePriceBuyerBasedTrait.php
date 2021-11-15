<?php

namespace App\Repositories\Product\ProductInfo\ComplexTransaction\Traits\Info;

use App\Repositories\Product\ProductInfo\ComplexTransaction\Interfaces\TemplatePriceBuyerBasedInterface;
use App\Repositories\Product\ProductPriceRepository;

/**
 * @see TemplatePriceBuyerBasedInterface
 */
trait TemplatePriceBuyerBasedTrait
{
    private $templateSellerId;

    /**
     * @inheritDoc
     */
    public function getTemplateSellerId(): int
    {
        if (!$this->templateSellerId) {
            throw new \InvalidArgumentException('必须先设置 templateSellerId');
        }
        return $this->templateSellerId;
    }

    /**
     * @inheritDoc
     */
    public function solveTemplatePriceByBuyer(float $price): float
    {
        if (!$this->isCustomerSet() || !$this->isCustomerBuyer()) {
            return $price;
        }
        return (float)app(ProductPriceRepository::class)
            ->getProductActualPriceByBuyer($this->getTemplateSellerId(), $this->getCustomer(), $price);
    }

    /**
     * @inheritDoc
     */
    public function solveTemplatePriceRangeByBuyer(array $prices): array
    {
        [$min, $max] = $prices;
        $min = $this->solveTemplatePriceByBuyer($min);
        $max = $this->solveTemplatePriceByBuyer($max);
        return [$min, $max];
    }

    /**
     * 设置模版的 seller id
     */
    public function setTemplateSellerId(int $sellerId): void
    {
        $this->templateSellerId = $sellerId;
    }
}
