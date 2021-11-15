<?php

namespace App\Repositories\Product\ProductInfo\ComplexTransaction\Traits;

use App\Repositories\Product\ProductInfo\ComplexTransaction\AbsComplexTransactionInfo;
use App\Repositories\Product\ProductInfo\ComplexTransaction\Interfaces\TemplatePriceBuyerBasedInterface;
use LogicException;

/**
 * 给 AbsComplexTransactionRepository 提供价格区间的能力
 * 需要迁移到 InfoRepository 下
 */
trait WithBuyerPriceRepositoryTrait
{
    private $priceRangeEnable = false;

    /**
     * @param int|null $buyerId
     * @return $this
     */
    public function withPriceRange(?int $buyerId = null): self
    {
        $new = clone $this;
        $new->priceRangeEnable = true;
        return $new->withCustomerId($buyerId);
    }

    /**
     * 获取产品的价格区间
     * @return array [$productId => [$min, $max]]
     */
    public function getPriceRanges(): array
    {
        if (!$this->priceRangeEnable) {
            throw new LogicException('必须先调用 withPriceRange 后才能获取价格区间');
        }

        $infos = $this->getInfos();
        $data = [];
        foreach ($infos as $info) {
            $data[$info->id] = $info->getPriceRange();
        }
        return $data;
    }

    /**
     * 获取指定产品的模版的价格区间
     * @param array $productIds
     * @return array [$productId => [$minPrice, $maxPrice]]
     */
    abstract protected function getTemplatePriceRanges(array $productIds): array;

    /**
     * 获取指定产品的指定 buyer 的价格区间
     * @param int|string $buyerId
     * @param array $productIds
     * @return array [$productId => [$minPrice, $maxPrice]]
     */
    abstract protected function getBuyerPriceRanges($buyerId, array $productIds): array;

    /**
     * @param array|AbsComplexTransactionInfo[] $infos
     */
    protected function solveWithPriceRanges(array $infos)
    {
        if ($this->priceRangeEnable) {
            $prices = $this->getTemplatePriceRanges(array_keys($infos));
            foreach ($prices as $productId => $price) {
                $info = $infos[$productId];
                if ($info instanceof TemplatePriceBuyerBasedInterface) {
                    $price = $info->solveTemplatePriceRangeByBuyer($price);
                }
                $info->setTemplatePriceRange($price);
                $infos[$productId] = $info;
            }

            if ($this->basedCustomerId) {
                $prices = $this->getBuyerPriceRanges($this->basedCustomerId, array_keys($infos));
                foreach ($prices as $productId => $price) {
                    $infos[$productId]->setBuyerPriceRange($price);
                }
            }
        }
    }
}
