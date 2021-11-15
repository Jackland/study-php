<?php

namespace App\Repositories\Product\ProductInfo;

use App\Repositories\Product\ProductInfo\ComplexTransaction\AbsComplexTransactionRepository;
use App\Repositories\Product\ProductInfo\ComplexTransaction\FuturesInfoRepository;
use App\Repositories\Product\ProductInfo\ComplexTransaction\MarginInfoRepository;
use App\Repositories\Product\ProductInfo\ComplexTransaction\ProductQuoteInfoRepository;
use App\Repositories\Product\ProductInfo\ComplexTransaction\QuoteInfoRepository;
use App\Repositories\Product\ProductInfo\ComplexTransaction\RebateInfoRepository;
use LogicException;

class ProductInfoFactory
{
    /**
     * @var array|int[]
     */
    private $ids = [];

    /**
     * @param int|array|int[] $ids
     * @return $this
     */
    public function withIds($ids): self
    {
        $new = clone $this;
        $new->ids = array_map('intval', !is_array($ids) ? [$ids] : $ids);

        return $new;
    }

    /**
     * 基础信息
     * @return BaseInfoRepository
     */
    public function getBaseInfoRepository(): BaseInfoRepository
    {
        return new BaseInfoRepository($this->ids);
    }

    /**
     * @param string|AbsComplexTransactionRepository $class
     * @return AbsComplexTransactionRepository
     */
    public function buildComplexTransactionRepository($class): AbsComplexTransactionRepository
    {
        if (!is_a($class, AbsComplexTransactionRepository::class, true)) {
            throw new LogicException("{$class} 非 AbsComplexTransactionRepository");
        }
        return app()->make($class, ['ids' => $this->ids]);
    }

    /**
     * 返点
     * @return RebateInfoRepository|AbsComplexTransactionRepository
     */
    public function getRebateInfoRepository()
    {
        return $this->buildComplexTransactionRepository(RebateInfoRepository::class);
    }

    /**
     * 现货保证金
     * @return MarginInfoRepository|AbsComplexTransactionRepository
     */
    public function getMarginInfoRepository(): MarginInfoRepository
    {
        return $this->buildComplexTransactionRepository(MarginInfoRepository::class);
    }

    /**
     * 期货
     * @return FuturesInfoRepository|AbsComplexTransactionRepository
     */
    public function getFuturesInfoRepository(): FuturesInfoRepository
    {
        return $this->buildComplexTransactionRepository(FuturesInfoRepository::class);
    }

    /**
     * 阶梯价
     * @return QuoteInfoRepository|AbsComplexTransactionRepository
     */
    public function getQuoteInfoRepository(): QuoteInfoRepository
    {
        return $this->buildComplexTransactionRepository(QuoteInfoRepository::class);
    }

    /**
     * 议价
     * @return ProductQuoteInfoRepository|AbsComplexTransactionRepository
     */
    public function getProductQuoteInfoRepository(): ProductQuoteInfoRepository
    {
        return $this->buildComplexTransactionRepository(ProductQuoteInfoRepository::class);
    }
}
