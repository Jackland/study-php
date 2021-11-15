<?php

namespace App\Repositories\Product\ProductInfo\ComplexTransaction;

use App\Enums\Rebate\RebateAgreementResultEnum;
use App\Models\Rebate\RebateAgreementItem;
use App\Models\Rebate\RebateAgreementTemplateItem;
use App\Repositories\Product\ProductPriceRepository;
use Illuminate\Database\Eloquent\Collection;
use kriss\bcmath\BC;

/**
 * 返点
 */
class RebateInfoRepository extends AbsComplexTransactionRepository
{
    /**
     * @inheritDoc
     * @return RebateInfo[]
     */
    public function getInfos(): array
    {
        return parent::getInfos();
    }

    /**
     * @inheritDoc
     * @param RebateAgreementTemplateItem $model
     */
    protected function newInfoModel($model): AbsComplexTransactionInfo
    {
        return new RebateInfo($model, $model->rebateAgreementTemplate);
    }

    /**
     * @inheritDoc
     */
    protected function getModelsWithOneProductOneInfo(array $productIds): iterable
    {
        $models = $this->getAllTemplateItemsByProduct($productIds)->keyBy('id');

        $shouldSolvePrice = $this->isCustomerSet() && $this->isCustomerBuyer();
        $idPrices = [];
        foreach ($models as $id => $model) {
            $price = $model->price;
            if ($shouldSolvePrice) {
                // buyer 相关，返点金额需要计算免税金额
                $price = app(ProductPriceRepository::class)
                    ->getProductActualPriceByBuyer($model->rebateAgreementTemplate->seller_id, $this->getCustomer(), $price);
            }
            $price = (float)BC::create(['scale' => 2])->sub($price, $model->rebate_amount);
            if (!isset($idPrices[$model->product_id]) || $idPrices[$model->product_id]['price'] > $price) {
                // 按照返点金额从小到大排，取最小的
                $idPrices[$model->product_id] = [
                    'id' => $id,
                    'price' => $price,
                ];
            }
        }
        $result = [];
        foreach ($idPrices as $productId => $info) {
            $result[$productId] = $models[$info['id']];
        }
        return $result;
    }

    /**
     * @inheritDoc
     */
    protected function getTemplatePriceRanges(array $productIds): array
    {
        $models = $this->getAllTemplateItemsByProduct($productIds);

        $shouldSolvePrice = $this->isCustomerSet() && $this->isCustomerBuyer();
        $minMax = [];
        foreach ($models as $model) {
            $price = $model->price;
            if ($shouldSolvePrice) {
                // buyer 相关，返点金额需要计算免税金额
                $price = app(ProductPriceRepository::class)
                    ->getProductActualPriceByBuyer($model->rebateAgreementTemplate->seller_id, $this->getCustomer(), $price);
            }
            $price = (float)BC::create(['scale' => 2])->sub($price, $model->rebate_amount);
            if (!isset($minMax[$model->product_id])) {
                $minMax[$model->product_id] = [$price, $price];
                continue;
            }
            if ($price < $minMax[$model->product_id][0]) {
                $minMax[$model->product_id][0] = $price;
            }
            if ($price > $minMax[$model->product_id][1]) {
                $minMax[$model->product_id][1] = $price;
            }
        }
        return $minMax;
    }

    /**
     * @inheritDoc
     */
    protected function getBuyerPriceRanges($buyerId, array $productIds): array
    {
        $models = RebateAgreementItem::query()->alias('a')
            ->leftJoinRelations(['rebateAgreement as b'])
            ->selectRaw('max(a.template_price - a.rebate_amount) as max,min(a.template_price - a.rebate_amount) as min,a.product_id')
            ->where('b.buyer_id', $buyerId)
            ->where('b.status', 3)
            ->whereIn('b.rebate_result', [
                RebateAgreementResultEnum::__DEFAULT,
                RebateAgreementResultEnum::ACTIVE,
                RebateAgreementResultEnum::DUE_SOON,
            ])
            ->whereIn('a.product_id', $productIds)
            ->groupBy('product_id')
            ->get();
        $data = [];
        foreach ($models as $model) {
            $data[$model->product_id] = [$model['min'], $model['max']];
        }
        return $data;
    }

    private $_models;

    /**
     * @param array $productIds
     * @return Collection|RebateAgreementTemplateItem[]
     */
    private function getAllTemplateItemsByProduct(array $productIds): Collection
    {
        $key = implode(',', $productIds);
        if (isset($this->_models[$key])) {
            return $this->_models[$key];
        }

        return $this->_models[$key] = RebateAgreementTemplateItem::query()
            ->with(['rebateAgreementTemplateAvailable'])
            ->whereIn('product_id', $productIds)
            ->where(['is_deleted' => 0])
            ->whereHas('rebateAgreementTemplateAvailable') // 明细存在，但模板已删除的情况
            ->get();
    }
}
