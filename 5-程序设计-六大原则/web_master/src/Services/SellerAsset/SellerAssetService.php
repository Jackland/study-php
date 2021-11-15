<?php

namespace App\Services\SellerAsset;

use App\Models\Seller\SellerDeliveryLine;
use App\Models\SellerAsset\SellerAsset;
use App\Repositories\Seller\SellerRepository;
use Carbon\Carbon;
use Illuminate\Database\Query\Expression;

class SellerAssetService
{
    /**
     * 查询或者添加一条seller asset记录并返回
     *
     * @param int $sellerId
     * @return SellerAsset|null
     */
    public function firstOrCreateSellerAsset($sellerId)
    {
        if (!(app(SellerRepository::class)->isOuterSellerNotGigaOnside($sellerId))) {
            // 30509 调整为无论新增哪个国家的外部seller都插入数据
            return null;
        }
        return SellerAsset::query()->firstOrCreate([
            'customer_id' => $sellerId,
        ], [
            'ocean_freight' => 0,
            'tariff' => 0,
            'unloading_charges' => 0,
            'storage_fee' => 0,
            'collateral_value' => 0,
            'shipping_value' => 0,
            'life_money_deposit' => 0,
            'supply_chain_finance' => 0,
            'alarm_level' => 0,
            'memo' => 0,
            'create_user_name' => 'php add seller',
            'create_time' => Carbon::now()
        ]);
    }

    /**
     * 减在库抵押物货值
     *
     * @param int $orderId 采购订单ID
     * @return bool
     */
    public function subCollateralValueByOrder($orderId)
    {
        // 查询采购单的seller出库信息
        $sellerDeliveryLineList = SellerDeliveryLine::query()
            ->where('order_id',$orderId)
            ->has('batch')
            ->with('batch')
            ->get();
        $subCollateralValue = [];// seller_id=>CollateralValue
        foreach ($sellerDeliveryLineList as $sellerDeliveryLine) {
            if (!($sellerDeliveryLine->batch)
                || $sellerDeliveryLine->batch->unit_price <= 0
                || $sellerDeliveryLine->qty <= 0) {
                // 不存在批次信息或者批次单价小于等于0就不计算了,或者数量小于等于0也不参与计算（这种可能应该是没有）
                continue;
            }
            // 获取默认值
            $sellerCollateralValue = $subCollateralValue[$sellerDeliveryLine->seller_id] ?? 0;
            // 根据批次的单价计算
            $sellerCollateralValue += $sellerDeliveryLine->batch->unit_price * $sellerDeliveryLine->qty;
            $subCollateralValue[$sellerDeliveryLine->seller_id] = $sellerCollateralValue;
        }
        foreach ($subCollateralValue as $sellerId => $subValue) {
            SellerAsset::query()->where('customer_id', $sellerId)
                ->update(['collateral_value' => new Expression("collateral_value - {$subValue}")]);
        }
        return true;
    }

    /**
     * 加在库抵押物值
     *
     * @param int $customerId
     * @param $value
     * @return bool|int
     */
    public function addCollateralValueByOrder($customerId, $value)
    {
        if (!$customerId || $value == 0) {
            return false;
        }
        return SellerAsset::query()->where('customer_id', $customerId)
            ->update(['collateral_value' => new Expression("collateral_value + {$value}")]);
    }
}
