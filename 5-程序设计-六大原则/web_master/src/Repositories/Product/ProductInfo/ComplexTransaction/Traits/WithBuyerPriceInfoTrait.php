<?php

namespace App\Repositories\Product\ProductInfo\ComplexTransaction\Traits;

/**
 * 给 AbsComplexTransactionInfo 提供获取价格区间的能力
 *  * 需要迁移到 Info 下
 */
trait WithBuyerPriceInfoTrait
{
    /**
     * 模版的价格区间
     * @var float[]
     */
    public $templatePriceRange = [0, 0];

    /**
     * 针对 buyer 的价格区间
     * @var float[]
     */
    public $buyerPriceRange = [0, 0];

    /**
     * @param array $minMax [$min, $max]
     */
    public function setTemplatePriceRange(array $minMax)
    {
        $this->templatePriceRange = $minMax;
    }

    /**
     * @param array $minMax [$min, $max]
     */
    public function setBuyerPriceRange(array $minMax)
    {
        $this->buyerPriceRange = $minMax;
    }

    /**
     * @return array
     */
    public function getPriceRange(): array
    {
        [$min, $max] = $this->templatePriceRange;
        if ($this->buyerPriceRange[0] > 0 && $min > $this->buyerPriceRange[0]) {
            $min = $this->buyerPriceRange[0];
        }
        if ($this->buyerPriceRange[1] > 0 && $max < $this->buyerPriceRange[1]) {
            $max = $this->buyerPriceRange[1];
        }
        return [$min, $max];
    }
}
