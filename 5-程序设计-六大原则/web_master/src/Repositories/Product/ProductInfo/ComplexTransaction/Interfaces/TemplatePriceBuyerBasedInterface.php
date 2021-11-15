<?php

namespace App\Repositories\Product\ProductInfo\ComplexTransaction\Interfaces;

/**
 * 模版价格需要与 buyer 相关的接口
 * 主要是欧洲的需要针对 buyer 处理为免税价（#31737）
 */
interface TemplatePriceBuyerBasedInterface
{
    /**
     * 获取复杂交易模版（合约）的 seller id
     * @return int
     */
    public function getTemplateSellerId(): int;

    /**
     * 根据 buyer 处理模版价格
     * @param float $price
     * @return float
     */
    public function solveTemplatePriceByBuyer(float $price): float;

    /**
     * 根据 buyer 处理模版的价格区间
     * @param array $prices
     * @return array
     */
    public function solveTemplatePriceRangeByBuyer(array $prices): array;
}
