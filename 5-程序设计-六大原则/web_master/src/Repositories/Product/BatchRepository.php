<?php

namespace App\Repositories\Product;

use App\Components\Traits\RequestCachedDataTrait;
use App\Enums\Stock\ReceiptsOrderShippingWay;
use App\Helper\CountryHelper;
use App\Models\Product\Batch;
use App\Models\Product\Product;
use App\Models\Product\ProductSetInfo;
use App\Repositories\Setup\SetupRepository;
use kriss\bcmath\BCS;

class BatchRepository
{
    use RequestCachedDataTrait;

    /**
     * 获取抵押物金额
     *
     * @param int $productId 商品ID
     * @param int $qty 数量
     * @return float
     */
    public function getCollateralAmountByProduct(int $productId, int $qty)
    {
        if (!$productId || $qty <= 0) {
            return 0;
        }
        $batches = Batch::query()
            ->where('product_id', $productId)->where('onhand_qty', '>', 0)
            ->orderBy('create_time')
            ->get();
        $unitPrice = 0;
        foreach ($batches as $batch) {
            // 循环计算货值
            if ($batch->onhand_qty >= $qty) {
                // 如果数量够，直接计算货值并返回
                $unitPrice += $qty * $batch->unit_price;
                break;
            } else {
                // 不够的话有多少先算多少
                $unitPrice += $batch->onhand_qty * $batch->unit_price;
                $qty -= $batch->onhand_qty;
            }
        }
        return $unitPrice;
    }

    /**
     * 获取抵押物金额
     *
     * @param int $productId 商品ID
     * @param int $qty 数量
     * @return float
     * @deprecated 该方法暂未使用
     */
    public function getCollateralAmountByProductBak(int $productId, int $qty)
    {
        if (!$productId || $qty <= 0) {
            return 0;
        }
        $product = Product::query()->with('customerPartner')->find($productId);
        $batchProductIds = [];
        $productList = [];
        if ($product->combo_flag) {
            // combo 品
            $combos = ProductSetInfo::query()
                ->with(['setProduct', 'setProduct.customerPartner'])
                ->where('product_id', $productId)->get();
            foreach ($combos as $combo) {
                $batchProductIds[] = $combo->set_product_id;
                $productList[] = [
                    'product' => $combo->setProduct,
                    'qty' => $qty * $combo->qty
                ];
            }
        } else {
            $batchProductIds[] = $productId;
            $productList[] = [
                'product' => $product,
                'qty' => $qty
            ];
        }
        $batches = Batch::query()
            ->with(['receiptsOrder', 'receiptsOrder.receiptDetails', 'receiptsOrder.receiptDetails.product'])
            ->whereIn('product_id', $batchProductIds)
            ->where('onhand_qty', '>', 0)
            ->orderBy('batch_id') // 不使用 create_time 是因为 create_time 存在为 null 的情况
            ->get()
            ->groupBy('product_id');// 按product_id分组
        $collateralAmount = BCS::create(0, ['scale' => 4]);
        foreach ($productList as $productItem) {
            /** @var Product $productData */
            $productData = $productItem['product'];
            $productQty = $productItem['qty'];
            //$productVolume = $productData->length * $productData->width * $productData->height;
            /** @var Batch[] $productBatches */
            $productBatches = $batches[$productData->product_id] ?? [];
            foreach ($productBatches as $batch) {
                // 30509 抵押物调整，单价获取方式调整
                //$batchUnitVolumePrice = $this->getUnitVolumeCollateralAmountByBatch($batch, $product->customerPartner->country_id);
                //$batchUnitPrice = BCS::create($productVolume, ['scale' => 4])->mul($batchUnitVolumePrice)->getResult();
                $batchUnitPrice = $batch->unit_price;
                // 循环计算货值
                if ($batch->onhand_qty >= $productQty) {
                    // 如果数量够，直接计算货值并返回
                    $collateralAmount->add($productQty * $batchUnitPrice);
                    break;
                } else {
                    // 不够的话有多少先算多少
                    $collateralAmount->add($batch->onhand_qty * $batchUnitPrice);
                    $productQty -= $batch->onhand_qty;
                }
            }
        }
        return $collateralAmount->getResult();
    }

    /**
     * 获取单个批次的单位体积抵押物价值
     *
     * @param Batch $batch
     * @param int $countryId
     *
     * @return float
     */
    private function getUnitVolumeCollateralAmountByBatch(Batch $batch, $countryId)
    {
        $cacheKey = [__CLASS__, __FUNCTION__, $batch->receipts_order_id ?? 0, $countryId];
        if ($unitVolumePrice = $this->getRequestCachedData($cacheKey)) {
            return $unitVolumePrice;
        }
        $amountOfCollateralKey = 'AMOUNT_OF_COLLATERAL_' . CountryHelper::getCountryCodeById($countryId);
        $amountOfCollateral = app(SetupRepository::class)->getValueByKey($amountOfCollateralKey);
        if (!$amountOfCollateral) {
            return 0;
        }
        // 先判断是整柜还是散货
        if ($batch->source_code == '入库单收货'
            && !empty($batch->receiptsOrder)
            && in_array($batch->receiptsOrder->shipping_way, [ReceiptsOrderShippingWay::ENTRUSTED_SHIPPING, ReceiptsOrderShippingWay::SELLER_SPONTANEOUS_SHIPPING])) {
            // 整柜
            // 获取整个入库单体积
            $volume = BCS::create(0, ['scale' => 4]);
            foreach ($batch->receiptsOrder->receiptDetails as $receiptDetail) {
                $productVolume = $receiptDetail->product->length * $receiptDetail->product->width * $receiptDetail->product->height;
                $productVolume *= $receiptDetail->received_qty;
                $volume->add($productVolume);
            }
            $volume = $volume->getResult();
        } else {
            // 散货
            $volume = 3966543.4;// 65m³=3966543.4立方英寸,这个值是产品那边给定的如有更改就手动改一下
        }
        $unitVolumePrice = BCS::create($amountOfCollateral, ['scale' => 4])->div($volume)->getResult();
        $this->setRequestCachedData($cacheKey, $unitVolumePrice);
        return $unitVolumePrice;
    }
}
