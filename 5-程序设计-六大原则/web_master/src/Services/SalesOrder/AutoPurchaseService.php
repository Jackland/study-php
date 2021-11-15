<?php

namespace App\Services\SalesOrder;

use App\Components\Locker;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Exception\SalesOrder\Enums\AutoPurchaseCode;
use App\Helper\CountryHelper;
use App\Logging\Logger;
use App\Models\SalesOrder\CustomerSalesOrder;
use App\Services\SalesOrder\AutoPurchase\BuyerProductsStockPool;
use App\Services\SalesOrder\AutoPurchase\SafeguardComponent;
use App\Services\SalesOrder\AutoPurchase\SaleOrderComponent;
use Cart\Customer;
use Framework\Exception\Exception;
use Illuminate\Support\Collection;

class AutoPurchaseService
{
    /**
     * 自动购买
     * @param array $saleOrderIds
     * @return array
     * @throws Exception
     */
    public function autoPurchase(array $saleOrderIds): array
    {
        $handleResult = [];

        // 处理不是new order的订单
        $unHandleSaleOrders = CustomerSalesOrder::query()
            ->whereIn('id', $saleOrderIds)
            ->where('order_status', '!=',CustomerSalesOrderStatus::TO_BE_PAID)
            ->get();
        foreach ($unHandleSaleOrders as $unHandleSaleOrder) {
            $this->isHandleSalesOrder($unHandleSaleOrder, $handleResult);
        }

        $saleOrders = CustomerSalesOrder::query()->with(['lines', 'orderAssociates'])
            ->whereIn('id', $saleOrderIds)
            ->where('order_status', CustomerSalesOrderStatus::TO_BE_PAID)
            ->get();

        if ($saleOrders->isEmpty() && $unHandleSaleOrders->isEmpty()) {
            throw new Exception('Warning: No sales order processing!', 406);
        }

        // 找出buyer的某些销售单
        $buyerSaleOrders = $saleOrders->groupBy('buyer_id');
        // 原因: 1.单个销售单明细查库存效率低;2.按java原逻辑拿出所有buyer的库存在内存中,导致同时调用效验库存问题;
        // 解决：在buyer上加锁处理， 此方案无法解决有buyer在页面上操作绑定，暂不考虑
        $unHandleOfLockSaleOrders = new Collection();
        $customerNoFoundSaleOrders = new Collection();
        foreach ($buyerSaleOrders as $buyerId => $saleOrders) {
            $lock = Locker::autoPurchase($buyerId, 10);
            if (!$lock->acquire()) {
                $unHandleOfLockSaleOrders = $unHandleOfLockSaleOrders->merge($saleOrders);
                continue;
            }

            // 初始化用户
            session()->set('customer_id', $buyerId);
            $customer = new Customer(app('registry'));
            if (!$customer->getModel() || db('oc_customer_exts')->where('auto_buy', 1)->where('customer_id', $customer->getId())->doesntExist()) {
                $customerNoFoundSaleOrders = $customerNoFoundSaleOrders->merge($saleOrders);
                continue;
            }
            app('registry')->set('customer', $customer);
            $currency = CountryHelper::getCountryCurrencyCodeById($customer->getCountryId());
            session()->set('currency', $currency);
            session()->set('country', CountryHelper::getCountryCodeById($customer->getCountryId()));
            $isCollectionFromDomicile = $customer->isCollectionFromDomicile() ? 1 : 0;

            try {
                // 获取这个buyer的所有产品的库存,按照sku分组
                $buyerProductsStockPool = new BuyerProductsStockPool($buyerId);
                foreach ($saleOrders as $saleOrder) {
                    /** @var CustomerSalesOrder $saleOrder */
                    // 检测防止有销售单以及被处理
                    if (!$this->isHandleSalesOrder(CustomerSalesOrder::query()->find($saleOrder->id), $handleResult)) {
                        continue;
                    }

                    $saleOrderComponent = new SaleOrderComponent($saleOrder, $buyerProductsStockPool);
                    // 购买成功会在clearCartByOrderId方法中清除delivery_type，每次处理一个销售单需要重新赋值
                    session()->set('delivery_type', $isCollectionFromDomicile);
                    [$result, $content, $code] = $saleOrderComponent->handle();
                    $handleResult[] = [
                        'salesOrderId' => $saleOrder->id,
                        'result' => $result,
                        'content' => $content,
                        'code' => $code,
                    ];
                }
            } catch (\Exception $e) {
                // 这边只会捕获获取库存的和删除购物车的异常
                Logger::autoPurchase('自动购买报错：' . $e->getMessage());
            } finally {
                $lock->release();
            }
        }

        foreach ($handleResult as $salesOrderResult) {
            if ($salesOrderResult['result'] === 'success') {
                try {
                    // 处理成功的销售订单继续购买保障服务
                    $saleOrderComponent = new SafeguardComponent($salesOrderResult['salesOrderId']);
                    $saleOrderComponent->handle();
                } catch (\Exception $e) {
                    Logger::autoPurchase('自动购买保障服务报错：' . $e->getMessage());
                }
            }
        }

        // 处理加锁的
        if ($unHandleOfLockSaleOrders->isNotEmpty()) {
            foreach ($unHandleOfLockSaleOrders as $unHandleOfLockSaleOrder) {
                $handleResult[] = [
                    'salesOrderId' => $unHandleOfLockSaleOrder->id,
                    'result' => 'fail',
                    'content' => AutoPurchaseCode::getDescription(AutoPurchaseCode::CODE_销售订单正在处理请稍后再试),
                    'code' => AutoPurchaseCode::CODE_销售订单正在处理请稍后再试,
                ];
            }
        }

        // 处理找不到用户的
        if ($customerNoFoundSaleOrders->isNotEmpty()) {
            foreach ($customerNoFoundSaleOrders as $customerNoFoundSaleOrder) {
                $handleResult[] = [
                    'salesOrderId' => $customerNoFoundSaleOrder->id,
                    'result' => 'exception',
                    'content' => AutoPurchaseCode::getDescription(AutoPurchaseCode::CODE_自动购买权限未开通),
                    'code' => AutoPurchaseCode::CODE_自动购买权限未开通,
                ];
            }
        }

        return $handleResult;
    }

    /**
     * @param CustomerSalesOrder $salesOrder
     * @param array $handleResult
     * @return bool
     */
    private function isHandleSalesOrder(CustomerSalesOrder $salesOrder, array &$handleResult): bool
    {
        if ($salesOrder->order_status == CustomerSalesOrderStatus::BEING_PROCESSED) {
            $handleResult[] = [
                'salesOrderId' => $salesOrder->id,
                'result' => 'success',
                'content' => AutoPurchaseCode::getDescription(AutoPurchaseCode::CODE_销售订单的状态为BEING_PROCESSED),
                'code' => AutoPurchaseCode::CODE_销售订单的状态为BEING_PROCESSED,
            ];
            return false;
        }
        if ($salesOrder->order_status != CustomerSalesOrderStatus::TO_BE_PAID) {
            $statusName = CustomerSalesOrderStatus::getViewItems()[$salesOrder->order_status];
            $handleResult[] = [
                'salesOrderId' => $salesOrder->id,
                'result' => 'exception',
                'content' => str_replace('#replace_status#', $statusName, AutoPurchaseCode::getDescription(AutoPurchaseCode::CODE_销售订单的状态异常)),
                'code' => AutoPurchaseCode::CODE_销售订单的状态异常,
            ];
            return false;
        }

        return true;
    }
}
