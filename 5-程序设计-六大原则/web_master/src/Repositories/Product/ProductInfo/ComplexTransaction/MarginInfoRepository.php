<?php

namespace App\Repositories\Product\ProductInfo\ComplexTransaction;

use App\Enums\Agreement\AgreementCommonPerformerAgreementType;
use App\Enums\Margin\MarginAgreementStatus;
use App\Models\Agreement\AgreementCommonPerformer;
use App\Models\Margin\MarginAgreement;
use App\Models\Margin\MarginTemplate;

/**
 * 现货
 */
class MarginInfoRepository extends AbsComplexTransactionRepository
{
    /**
     * @inheritDoc
     * @return MarginInfo[]
     */
    public function getInfos(): array
    {
        return parent::getInfos();
    }

    /**
     * @inheritDoc
     * @param MarginTemplate $model
     */
    protected function newInfoModel($model): AbsComplexTransactionInfo
    {
        return new MarginInfo($model);
    }

    /**
     * @inheritDoc
     */
    protected function getModelsWithOneProductOneInfo(array $productIds): iterable
    {
        $models = MarginTemplate::query()
            ->select(['product_id', 'id'])
            ->whereIn('product_id', $productIds)
            ->where('is_del', 0)
            ->orderBy('product_id')
            ->orderBy('price')
            ->get();

        $ids = [];
        foreach ($models as $model) {
            if (!isset($ids[$model->product_id])) {
                $ids[$model->product_id] = $model->id;
            }
        }
        $ids = array_values($ids);

        // 获取符合条件的一个产品的一个返点信息
        return MarginTemplate::query()
            ->whereIn('id', $ids)
            ->get()->keyBy('product_id');
    }

    /**
     * @inheritDoc
     */
    protected function getTemplatePriceRanges(array $productIds): array
    {
        $models = MarginTemplate::query()
            ->selectRaw('max(price) as max,min(price) as min,product_id')
            ->whereIn('product_id', $productIds)
            ->where('is_del', 0)
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
        // 查询该 buyer 的所有协议
        $subQuery = AgreementCommonPerformer::query()
            ->select('agreement_id')
            ->where('buyer_id', $buyerId)
            ->whereIn('product_id', $productIds)
            ->where('agreement_type', AgreementCommonPerformerAgreementType::MARGIN);

        $models = MarginAgreement::query()->alias('a')
            ->selectRaw('max(price - deposit_per) as max,min(price - deposit_per) as min,product_id')
            ->whereIn('id', $subQuery)
            ->whereIn('status', [MarginAgreementStatus::APPROVED, MarginAgreementStatus::SOLD])
            ->where('expire_time', '>', date('Y-m-d H:i:s'))
            ->groupBy('product_id')
            ->get();
        $data = [];
        foreach ($models as $model) {
            $data[$model->product_id] = [$model['min'], $model['max']];
        }
        return $data;
    }
}
