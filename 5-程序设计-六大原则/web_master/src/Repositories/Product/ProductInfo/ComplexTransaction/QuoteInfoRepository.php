<?php

namespace App\Repositories\Product\ProductInfo\ComplexTransaction;

use App\Models\Product\Product;
use App\Models\Product\ProQuoteDetail;

/**
 * 阶梯价
 */
class QuoteInfoRepository extends AbsComplexTransactionRepository
{
    /**
     * @inheritDoc
     * @return QuoteInfo[]
     */
    public function getInfos(): array
    {
        return parent::getInfos();
    }

    /**
     * @inheritDoc
     * @param Product $model
     */
    protected function newInfoModel($model): AbsComplexTransactionInfo
    {
        $info = new QuoteInfo($model->product_id);
        $info->setTemplateSellerId($model->customerPartnerToProduct->customer_id);
        return $info;
    }

    /**
     * @inheritDoc
     */
    protected function getModelsWithOneProductOneInfo(array $productIds): iterable
    {
        // #31737 需要知道产品属于某个seller的
        return Product::query()->with('customerPartnerToProduct')
            ->whereIn('product_id', $productIds)
            ->get()
            ->keyBy('product_id');
    }

    /**
     * @inheritDoc
     */
    protected function getTemplatePriceRanges(array $productIds): array
    {
        $models = ProQuoteDetail::query()
            ->selectRaw('max(home_pick_up_price) as max,min(home_pick_up_price) as min,product_id')
            ->whereIn('product_id', $productIds)
            ->groupBy('product_id')
            ->get();
        $data = [];
        foreach ($models as $model) {
            $data[$model->product_id] = [$model['min'], $model['max']];
        }
        return $data;
    }

    /**
     * @inheritDoc
     */
    protected function getBuyerPriceRanges($buyerId, array $productIds): array
    {
        // 阶梯价与 buyer 无关
        return [];
    }
}
