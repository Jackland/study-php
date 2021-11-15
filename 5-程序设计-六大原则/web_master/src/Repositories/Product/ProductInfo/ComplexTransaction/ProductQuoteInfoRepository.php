<?php

namespace App\Repositories\Product\ProductInfo\ComplexTransaction;

use App\Enums\Spot\SpotProductQuoteStatus;
use App\Models\Product\ProductQuote;

/**
 * 议价
 */
class ProductQuoteInfoRepository extends AbsComplexTransactionRepository
{
    /**
     * @inheritDoc
     * @return ProductQuoteInfo[]
     */
    public function getInfos(): array
    {
        return parent::getInfos();
    }

    /**
     * @inheritDoc
     * @param int $model productId
     */
    protected function newInfoModel($model): AbsComplexTransactionInfo
    {
        return new ProductQuoteInfo($model);
    }

    /**
     * @inheritDoc
     */
    protected function getModelsWithOneProductOneInfo(array $productIds): iterable
    {
        // 暂时无展示数据的需求，因此不需要查询内容
        return array_combine($productIds, $productIds);
    }

    /**
     * @inheritDoc
     */
    protected function getTemplatePriceRanges(array $productIds): array
    {
        // 议价必定与 buyer 相关，无模版价
        return [];
    }

    /**
     * @inheritDoc
     */
    protected function getBuyerPriceRanges($buyerId, array $productIds): array
    {
        $models = ProductQuote::query()
            ->selectRaw('max(price) as max,min(price) as min,product_id')
            ->whereIn('product_id', $productIds)
            ->where([
                'customer_id' => $buyerId,
                'status' => SpotProductQuoteStatus::APPROVED,
            ])
            ->groupBy(['product_id'])
            ->get();
        $data = [];
        foreach ($models as $model) {
            $data[$model->product_id] = [$model['min'], $model['max']];
        }
        return $data;
    }
}
