<?php

namespace App\Repositories\SellerAsset;

use App\Components\Traits\RequestCachedDataTrait;
use App\Enums\Common\YesNoEnum;
use App\Enums\Country\Country;
use App\Enums\Customer\CustomerAccountingType;
use App\Enums\SalesOrder\CustomerSalesOrderMode;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Models\Link\OrderAssociated;
use App\Models\SalesOrder\CustomerSalesOrderLine;
use App\Models\SellerAsset\SellerAsset;
use App\Repositories\Futures\AgreementRepository;
use App\Repositories\Product\BatchRepository;
use App\Repositories\Seller\SellerRepository;
use App\Repositories\SellerBill\SellerBillRepository;
use Illuminate\Database\Query\Expression;
use kriss\bcmath\BCS;

/**
 * seller资产管理相关查询
 *
 * Class SellerAssetRepository
 * @package App\Repositories\SellerAsset
 */
class SellerAssetRepository
{
    use RequestCachedDataTrait;

    /**
     * 获取预计应收款
     *
     * @param int $sellerId
     * @param bool $isPureLogisticsFreight 是否要算上纯物流预计运费
     */
    public function getEstimatedAccounts(int $sellerId, $isPureLogisticsFreight = true)
    {
        $billingAssets = $this->getBillingAssets($sellerId);
        $estimatedAccounts = BCS::create($billingAssets['current_balance'], ['scale' => 2]);
        $assetInfo = $this->getAssetInfo($sellerId);
        if ($assetInfo) {
            // 减去海运费、关税、卸货费、仓租
            $estimatedAccounts->add($assetInfo->ocean_freight,
                $assetInfo->tariff,
                $assetInfo->unloading_charges);
        }
        if ($isPureLogisticsFreight) {
            // 减去纯物流运费
            $pureLogisticsFreight = $this->getPureLogisticsFreight($sellerId, CustomerSalesOrderStatus::BEING_PROCESSED);
            $estimatedAccounts->sub($pureLogisticsFreight);
        }
        return $estimatedAccounts->getResult();
    }

    /**
     * 获取指定seller的总资产
     *
     * @param int $sellerId
     * @param bool $isPureLogisticsFreight 是否要算上纯物流预计运费
     * @return float|string
     */
    public function getTotalAssets(int $sellerId, $isPureLogisticsFreight = true)
    {
        $estimatedAccounts = $this->getEstimatedAccounts($sellerId, $isPureLogisticsFreight);
        $total = BCS::create($estimatedAccounts, ['scale' => 2]);
        $assetInfo = $this->getAssetInfo($sellerId);
        if ($assetInfo) {
            //加上在库抵押物+在途抵押物
            $total->add($assetInfo->collateral_value, $assetInfo->shipping_value);
            //加上人民币押金
            if ($assetInfo->life_money_deposit > 0) {
                // 人民币转美元汇率
                /** @var \ModelLocalisationCurrency $currency */
                $currency = load()->model('localisation/currency');
                $rate = $currency->getExchangeRate('UUU', 'USD', true, false);
                $lifeMoneyDeposit = BCS::create($assetInfo->life_money_deposit, ['scale' => 2])->mul($rate)->getResult();
                $total->add($lifeMoneyDeposit);
            }
            // 减去供应链金融
            $total->add($assetInfo->supply_chain_finance);
            // 资产调整金额
            $total->add($assetInfo->asset_adjustment);
        }
        list($sellerIncomeAccount, $sellerExpendAccount) = app(AgreementRepository::class)->getSellerCollateralAmount($sellerId);
        if ($sellerIncomeAccount) {
            $total->add($sellerIncomeAccount);
        }
        if ($sellerExpendAccount) {
            $total->sub($sellerExpendAccount);
        }
        return $total->getResult();
    }

    /**
     * 获取seller账单内资产
     *
     * @param int $sellerId
     * @return float[]
     * {
     * current_balance 当前资产
     * }
     */
    public function getBillingAssets(int $sellerId)
    {
        $key = [__CLASS__, __FUNCTION__, $sellerId];
        if ($data = $this->getRequestCachedData($key)) {
            return $data;
        }
        $sellerBillRepo = app(SellerBillRepository::class);
        $sellerBill = $sellerBillRepo->getCurrentBill($sellerId);
        $currentBalance = $sellerBill ? $sellerBill->total : 0;
        // 当前资产
        $data = [
            'current_balance' => $currentBalance
        ];
        $this->setRequestCachedData($key, $data);
        return $data;
    }

    /**
     * 获取当前seller的纯物流运费
     *
     * @param int $sellerId sellerID
     * @param int|array $orderStatus 订单状态
     * @param array $where 附加条件,支持：tb_sys_customer_sales_order_line=>line tb_sys_customer_sales_order=>order
     * @return float
     */
    public function getPureLogisticsFreight(int $sellerId, $orderStatus, $where = [])
    {
        $estimateFreight = CustomerSalesOrderLine::query()->alias('line')
            ->leftJoinRelations('customerSalesOrder as order')
            ->where('order.order_mode', CustomerSalesOrderMode::PURE_LOGISTICS)// 纯物流
            ->whereIn('order.order_status', (array)$orderStatus)
            ->where('order.buyer_id', $sellerId)
            ->when(!empty($where), function ($query) use ($where) {
                $query->where($where);
            })
            ->first(new Expression('sum(line.estimate_freight) as estimate_freight'));
        return $estimateFreight ? floatval($estimateFreight->estimate_freight) : 0.00;
    }

    /**
     * 获取 seller 纯物流 抵押物减值金额（BP）
     *
     * @param int $sellerId
     * @return float
     */
    public function getPureLogisticsCollateralValueInBP(int $sellerId): float
    {
        $data = CustomerSalesOrderLine::query()->alias('a')
            ->leftJoinRelations('customerSalesOrder as b')
            ->where('b.order_status', CustomerSalesOrderStatus::BEING_PROCESSED)
            ->where('b.order_mode', CustomerSalesOrderMode::PURE_LOGISTICS)// 纯物流
            ->where('b.buyer_id', $sellerId)
            ->select(['a.product_id', new Expression('sum(a.qty) as qty')])
            ->groupBy('a.product_id')
            ->get()->toArray();
        $batchRepo = app(BatchRepository::class);
        $sum = 0;
        foreach ($data as $item) {
            if (!$item['product_id']) {
                continue;
            }
            $sum += $batchRepo->getCollateralAmountByProduct($item['product_id'], $item['qty']);
        }

        return $sum;
    }

    /**
     * 获取oc_seller_asset表信息
     * 本次请求内会缓存
     *
     * @param int $sellerId
     * @return SellerAsset|null
     */
    public function getAssetInfo(int $sellerId)
    {
        $key = [__CLASS__, __FUNCTION__, $sellerId];
        if ($data = $this->getRequestCachedData($key)) {
            return $data;
        }
        $asset = SellerAsset::query()->where('customer_id', $sellerId)->first();
        if ($asset) {
            $this->setRequestCachedData($key, $data);
        }
        return $asset;
    }

    /**
     * 获取所有需要报警的seller
     * 目前是获取所有美国外部seller
     * @param integer $countryId
     *
     * @return SellerAsset[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getAllAlarmSeller($countryId = Country::AMERICAN)
    {
        return SellerAsset::query()->alias('sa')
            ->leftJoinRelations('seller as s')
            ->where('s.country_id', $countryId)
            ->where('s.status', YesNoEnum::YES)
            ->whereIn('s.accounting_type', CustomerAccountingType::outerAccount())
            ->get(['sa.*']);
    }

    /**
     * 判断seller是否需要验证风控
     * 目前是只有美国外部seller才需要
     *
     * @param int $sellerId
     * @return bool
     */
    public function checkSellerRiskCountry($sellerId)
    {
        $sellerRepo = app(SellerRepository::class);
        return $sellerRepo->isOuterSellerNotGigaOnside($sellerId)
            && $sellerRepo->isCountrySeller($sellerId, Country::AMERICAN);
    }
}
