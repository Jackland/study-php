<?php

namespace App\Repositories\Product\ProductInfo\ComplexTransaction;

use App\Enums\Future\FutureMarginContractStatus;
use App\Enums\Future\FuturesMarginAgreementStatus;
use App\Enums\Future\FuturesMarginDeliveryStatus;
use App\Models\Futures\FuturesContract;
use App\Models\Futures\FuturesMarginAgreement;
use App\Repositories\Product\ProductInfo\ProductPriceRangeFactory;

/**
 * 期货
 */
class FuturesInfoRepository extends AbsComplexTransactionRepository
{
    /**
     * @inheritDoc
     * @return FuturesInfo[]
     */
    public function getInfos(): array
    {
        return parent::getInfos();
    }

    /**
     * @inheritDoc
     * @param FuturesContract $model
     */
    protected function newInfoModel($model): AbsComplexTransactionInfo
    {
        return new FuturesInfo($model);
    }

    /**
     * @inheritDoc
     */
    protected function getModelsWithOneProductOneInfo(array $productIds): iterable
    {
        $models = FuturesContract::query()
            ->select(['id', 'product_id', 'last_unit_price', 'margin_unit_price'])
            ->whereIn('product_id', $productIds)
            ->where('status', FutureMarginContractStatus::SALE)
            ->where('is_deleted', 0)
            ->orderBy('id')
            ->get();

        $data = collect();
        foreach ($models as $model) {
            $price = collect([$model->last_unit_price, $model->margin_unit_price])
                ->filter(function ($price) {
                    return $price > 0;
                })->min();
            if ($price <= 0) {
                continue;
            }
            $item = [
                'price' => $price,
                'id' => $model->id,
            ];
            if (!isset($data[$model->product_id])) {
                $data[$model->product_id] = $item;
                continue;
            }
            // 同产品多个合约，取价格最低的
            if ($item['price'] < $data[$model->product_id]['price']) {
                $data[$model->product_id] = $item;
            }
        }
        $ids = $data->values()->pluck('id')->toArray();

        // 获取符合条件的一个产品的一个返点信息
        return FuturesContract::query()
            ->whereIn('id', $ids)
            ->get()->keyBy('product_id');
    }

    /**
     * @inheritDoc
     */
    protected function getTemplatePriceRanges(array $productIds): array
    {
        $query = FuturesContract::query()
            ->whereIn('product_id', $productIds)
            ->where('status', FutureMarginContractStatus::SALE)
            ->where('is_deleted', 0);

        $models = (clone $query)
            ->selectRaw('max(last_unit_price) as max,min(last_unit_price) as min,product_id')
            ->where('last_unit_price', '>', 0)
            ->groupBy(['product_id'])
            ->get();
        $futures = [];
        foreach ($models as $model) {
            $futures[$model->product_id] = [$model['min'], $model['max']];
        }

        $models = (clone $query)
            ->selectRaw('max(margin_unit_price) as max,min(margin_unit_price) as min,product_id')
            ->where('margin_unit_price', '>', 0)
            ->groupBy(['product_id'])
            ->get();
        $margins = [];
        foreach ($models as $model) {
            $margins[$model->product_id] = [$model['min'], $model['max']];
        }

        $priceRangeFactory = new ProductPriceRangeFactory();
        foreach ($productIds as $productId) {
            $priceRangeFactory->addPrice($productId, $futures[$productId] ?? []);
            $priceRangeFactory->addPrice($productId, $margins[$productId] ?? []);
        }

        return $priceRangeFactory->getRanges();
    }

    /**
     * @inheritDoc
     */
    protected function getBuyerPriceRanges($buyerId, array $productIds): array
    {
        $models = FuturesMarginAgreement::query()->alias('a')
            ->leftJoinRelations(['futuresMarginDelivery as b'])
            ->leftJoin('oc_product_lock as c', function ($join) {
                $join->on('c.agreement_id', '=', 'a.id')->where('c.type_id', '=', configDB('transaction_type_margin_futures'));
            })
            ->selectRaw('max(b.last_unit_price) as max,min(b.last_unit_price) as min,a.product_id')
            ->where('a.buyer_id', $buyerId)
            ->where('a.agreement_status', FuturesMarginAgreementStatus::SOLD)
            ->where('b.delivery_status', FuturesMarginDeliveryStatus::TO_BE_PAID)
            ->where('c.qty', '>', 0)
            ->whereIn('a.product_id', $productIds)
            ->groupBy('product_id')
            ->get();
        $data = [];
        foreach ($models as $model) {
            $data[$model->product_id] = [$model['min'], $model['max']];
        }
        return $data;
    }
}
