<?php

namespace App\Services\SalesOrder\AutoPurchase;

use App\Enums\Product\ProductTransactionType;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Exception\SalesOrder\AutoPurchaseException;
use App\Exception\SalesOrder\Enums\AutoPurchaseCode;
use App\Logging\Logger;
use App\Models\Cart\Cart;
use App\Models\Link\OrderAssociated;
use App\Models\SalesOrder\CustomerSalesOrder;
use App\Models\SalesOrder\CustomerSalesOrderLine;
use App\Repositories\FeeOrder\StorageFeeRepository;
use App\Services\FeeOrder\FeeOrderService;
use App\Services\FeeOrder\StorageFeeService;
use App\Services\Order\OrderService;
use Carbon\Carbon;
use Exception;
use Throwable;

class SaleOrderComponent
{
    private $saleOrder;
    private $buyerProductsStockPool;
    private $customer;
    private $europeFreightComponent;

    public function __construct(CustomerSalesOrder $salesOrder, BuyerProductsStockPool $buyerProductsStockPool)
    {
        $this->saleOrder = $salesOrder;
        $this->buyerProductsStockPool = $buyerProductsStockPool;
        $this->customer = customer();
        $this->europeFreightComponent = new EuropeFreightComponent($salesOrder, $this->customer);
    }

    /**
     * @return array|string[]
     * @throws Exception
     */
    public function handle(): array
    {
        /** @var CustomerSalesOrder $saleOrder */
        if ($this->saleOrder->orderAssociates->isNotEmpty()) {
            return ['exception', AutoPurchaseCode::getDescription(AutoPurchaseCode::CODE_销售订单存在已绑定的记录), AutoPurchaseCode::CODE_销售订单存在已绑定的记录];
        }

        $saleOrderLines = $this->saleOrder->lines;
        if ($saleOrderLines->pluck('item_code')->unique()->count() != $saleOrderLines->count()) {
            return ['exception', AutoPurchaseCode::getDescription(AutoPurchaseCode::CODE_销售订单明细中存在多个相同的sku), AutoPurchaseCode::CODE_销售订单明细中存在多个相同的sku];
        }

        $orderAssociatedRecords = [];
        $addCartLines = [];
        foreach ($saleOrderLines as $saleOrderLine) {
            /** @var CustomerSalesOrderLine $saleOrderLine */
            if ($saleOrderLine->qty == 0) {
                continue;
            }

            // 该条销售明细囤货记录和剩余数量
            [$lineOrderAssociatedRecords, $remainQty] = $this->getLineAssociatedRecordsAndRemainQty($saleOrderLine);
            $orderAssociatedRecords = array_merge($orderAssociatedRecords, $lineOrderAssociatedRecords);

            // 处理加购购买
            if ($remainQty > 0) {
                $addCartLines[] = [
                    'sales_line_id' => $saleOrderLine->id,
                    'item_code' => $saleOrderLine->item_code,
                    'quantity' => $remainQty,
                    'seller_id' => $saleOrderLine->seller_id ?: 0,
                ];
            }
        }

        if (empty($addCartLines) && empty($orderAssociatedRecords)) {
            return ['exception', AutoPurchaseCode::getDescription(AutoPurchaseCode::CODE_销售订单没有加购和可绑的数据), AutoPurchaseCode::CODE_销售订单没有加购和可绑的数据];
        }

        // 获取combo信息(只有全部走囤货才更新)
        $lineComboSkuQtyMap = [];
        if (empty($addCartLines)) {
            $lineComboSkuQtyMap = $this->lineComboSkuQtyMap($orderAssociatedRecords);
        }

        // 加购
        try {
            $cartList = $this->batchAddCart($addCartLines);
        } catch (AutoPurchaseException $e) {
            // 回收库存
            $this->resetBuyerProductsStockPool();
            return ['fail', $e->getMessage(), $e->getCode()];
        } catch (Throwable $e) {
            // 回收库存
            $this->resetBuyerProductsStockPool();

            Logger::autoPurchase('加购报错：' . $e->getMessage());
            return ['fail', AutoPurchaseCode::getDescription(AutoPurchaseCode::CODE_程序报错), AutoPurchaseCode::CODE_程序报错];
        }

        // 支付
        try {
            $purchaseOrder = $this->handleDataAndPurchaseOrder($lineComboSkuQtyMap, $orderAssociatedRecords, $this->saleOrder->id, empty($addCartLines), $cartList);
        } catch (AutoPurchaseException $e) {
            // 删除该用户自动购买加入购物车的数据 回收库存
            Cart::query()->where('api_id', 1)->where('customer_id', $this->saleOrder->buyer_id)->delete();
            $this->resetBuyerProductsStockPool();

            return ['fail', $e->getMessage(), $e->getCode()];
        } catch (Throwable $e) {
            // 删除该用户自动购买加入购物车的数据 回收库存
            Cart::query()->where('api_id', 1)->where('customer_id', $this->saleOrder->buyer_id)->delete();
            $this->resetBuyerProductsStockPool();

            Logger::autoPurchase('处理数据和采购订单报错：' . $e->getMessage());
            return ['fail', AutoPurchaseCode::getDescription(AutoPurchaseCode::CODE_程序报错), AutoPurchaseCode::CODE_程序报错];
        }

        if (!empty($purchaseOrder)) {
            /** @var \ModelCheckoutOrder $modelCheckoutOrder */
            $modelCheckoutOrder = load()->model('checkout/order');
            $modelCheckoutOrder->addOrderHistoryByYzcModelAfterCommit($purchaseOrder);
        }

        // 删除数据
        if ($this->europeFreightComponent->isHandle()) {
            unset($this->europeFreightComponent);
        }

        return ['success', '下单购买的采购单ID:' . $purchaseOrder, AutoPurchaseCode::CODE_销售订单自动购买成功];
    }

    /**
     * 加购和支付异常需要回收库存池
     * @throws Exception
     */
    private function resetBuyerProductsStockPool()
    {
        $this->buyerProductsStockPool = new BuyerProductsStockPool($this->customer->getId());
    }

    /**
     * 处理数据和采购订单
     * @param array $lineComboSkuQtyMap
     * @param array $orderAssociatedRecords
     * @param int $saleOrderId
     * @param bool $isEmptyCartLines
     * @param array $cartList [{cartId =>sales_line_id  },...]
     * @return mixed
     * @throws Throwable
     */
    private function handleDataAndPurchaseOrder(array $lineComboSkuQtyMap, array $orderAssociatedRecords, int $saleOrderId, bool $isEmptyCartLines, array $cartList)
    {
        $precision = $this->customer->isJapan() ? 0 : 2;

        return dbTransaction(function () use ($lineComboSkuQtyMap, $orderAssociatedRecords, $saleOrderId, $isEmptyCartLines, $precision, $cartList) {
            // 绑定
            foreach ($orderAssociatedRecords as $associatedRecord) {
                $discountsAmount = app(OrderService::class)->orderProductWillAssociateDiscountsAmount(intval($associatedRecord['order_product_id']), intval($associatedRecord['qty']), $precision);
                $associatedRecord['coupon_amount'] = $discountsAmount['coupon_amount'];
                $associatedRecord['campaign_amount'] = $discountsAmount['campaign_amount'];
                OrderAssociated::query()->insert($associatedRecord);

                // 生成欧洲补运费囤货部分请求数据
                if ($this->europeFreightComponent->isHandle()) {
                    $this->europeFreightComponent->generateStockRequestData(
                        $associatedRecord['product_id'],
                        $associatedRecord['sales_order_line_id'],
                        $associatedRecord['qty'],
                        $associatedRecord['seller_id'],
                        $associatedRecord['order_product_id']
                    );
                }
            }

            // 费用单逻辑
            $feeOrderId = 0;
            if (!empty($orderAssociatedRecords) || !empty($cartList)) {
                $feeOrderId = $this->getSalesFeeOrderId($saleOrderId, $cartList);
            }

            // 更新combo信息(只有全部走囤货才更新)
            foreach ($lineComboSkuQtyMap as $lineId => $comboSkuQtyMap) {
                CustomerSalesOrderLine::query()->where('id', $lineId)->update(['combo_info' => json_encode($comboSkuQtyMap)]);
            }

            // 当前销售单全部使用囤货
            if ($isEmptyCartLines) {
                CustomerSalesOrder::query()->where('id', $saleOrderId)->update(['order_status' => CustomerSalesOrderStatus::BEING_PROCESSED]);

                // 全部使用囤货 且 费用单不存在的情况 直接过
                if (empty($feeOrderId)) {
                    Logger::autoPurchase('全部使用囤货且费用单不存在的情况直接过：' . json_encode($orderAssociatedRecords));
                    return 0;
                }
            }

            // 处理欧洲补运费逻辑（加购）
            if ($this->europeFreightComponent->isHandle()) {
                $this->europeFreightComponent->handle();
            }

            // 购买
            $orderId =  (new OrderComponent($saleOrderId, $feeOrderId))->handle();

            // 处理欧洲补运费绑定逻辑
            if ($this->europeFreightComponent->isHandle()) {
                $this->europeFreightComponent->bind($orderId);
            }

            return $orderId;
        });
    }

    /**
     * 处理费用单
     * @param int $saleOrderId
     * @param array $cartList
     * @return mixed
     * @throws Exception
     */
    private function getSalesFeeOrderId(int $saleOrderId, array $cartList)
    {
        $orderAssociatedIds = OrderAssociated::query()->where('sales_order_id', $saleOrderId)->pluck('id')->toArray();
        $salesInfo = [];
        // 处理囤货库存仓租
        if (!empty($orderAssociatedIds)) {
            app(StorageFeeService::class)->bindByOrderAssociated($orderAssociatedIds);
            $salesInfo = app(StorageFeeRepository::class)->getBoundStorageFeeBySalesOrder([$saleOrderId]);
        }
        // 处理现货尾款仓租
        if (!empty($cartList)) {
            $carts = Cart::query()->whereIn('cart_id', array_keys($cartList))->get();
            foreach ($carts as $cart) {
                if ($cart->type_id == ProductTransactionType::MARGIN) {
                    // 现货尾款
                    $storageFees = app(StorageFeeRepository::class)
                        ->getAgreementRestStorageFee(ProductTransactionType::MARGIN, $cart->agreement_id, $cart->quantity);
                    foreach ($storageFees as $storageFee) {
                        // 按格式存放仓租数据
                        $salesInfo[$saleOrderId][$storageFee->id] = $cartList[$cart->cart_id];
                    }
                }
            }
        }
        if (empty($salesInfo)) {
            return 0;
        }
        $salesFeeOrders = app(FeeOrderService::class)->createSalesFeeOrder($salesInfo);

        return $salesFeeOrders[$saleOrderId] ?? 0;
    }

    /**
     * 加购逻辑
     * @param array $addCartLines
     * @return array [{cartId =>sales_line_id  },...]
     * @throws \Framework\Exception\Exception
     * @throws \Throwable
     */
    private function batchAddCart(array $addCartLines): array
    {
        $cartList = [];// [{cartId =>sales_line_id  },...]
        if (!empty($addCartLines)) {
            dbTransaction(function () use ($addCartLines, &$cartList) {
                foreach ($addCartLines as $addCartLine) {
                    Logger::autoPurchase('----AUTO PURCHASE ADD CART START----', 'info', [
                        Logger::CONTEXT_VAR_DUMPER => ['param' => $addCartLine],
                    ]);

                    // 处理单个加购
                    $cart = (new AddCartComponent($addCartLine['item_code'], $addCartLine['quantity'], $addCartLine['sales_line_id'], $addCartLine['seller_id']))->handle();

                    Logger::autoPurchase('---AUTO PURCHASE ADD CART END----', 'info', [
                        Logger::CONTEXT_VAR_DUMPER => ['cart' => $cart],
                    ]);
                    $cartList[$cart->cart_id] = $addCartLine['sales_line_id'];

                    // 处理欧洲补运费生成采购请求数据
                    if ($this->europeFreightComponent->isHandle()) {
                        $this->europeFreightComponent->generatePurchaseRequestData($cart->product_id, $addCartLine['sales_line_id'], $addCartLine['quantity']);
                    }
                }
            });
        }
        return $cartList;
    }

    /**
     * 组装combo_info信息
     * @param array $orderAssociatedRecords
     * @return array
     */
    private function lineComboSkuQtyMap(array $orderAssociatedRecords): array
    {
        $lineComboSkuQtyMap = [];
        if (empty($orderAssociatedRecords)) {
            return $lineComboSkuQtyMap;
        }

        foreach ($orderAssociatedRecords as $orderAssociatedRecord) {
            $comboOrders = db('tb_sys_order_combo')
                ->where('order_id', $orderAssociatedRecord['order_id'])
                ->where('order_product_id', $orderAssociatedRecord['order_product_id'])
                ->select('qty', 'item_code', 'set_item_code')
                ->get();

            if ($comboOrders->isNotEmpty()) {
                $itemCode = $comboOrders->first()->item_code;
                $qtySkuMap = $comboOrders->pluck('qty', 'set_item_code')->toArray();

                $lineComboSkuQtyMap[$orderAssociatedRecord['sales_order_line_id']][] = array_merge([$itemCode => $orderAssociatedRecord['qty']], $qtySkuMap);
            }
        }

        return $lineComboSkuQtyMap;
    }

    /**
     * 囤货和剩余购买数量计算
     * @param CustomerSalesOrderLine $saleOrderLine
     * @return array
     */
    private function getLineAssociatedRecordsAndRemainQty(CustomerSalesOrderLine $saleOrderLine): array
    {
        $orderAssociatedRecords = [];
        $remainQty = $saleOrderLine->qty;
        $itemCode = strtoupper($saleOrderLine->item_code);

        // 获取这个产品的囤货情况
        $unbindStocks = $this->buyerProductsStockPool->getProductStocks($itemCode);
        if (empty($unbindStocks)) {
            return [$orderAssociatedRecords, $remainQty];
        }

        // 这边未处理更加sellerId判断优先级的问题，原java逻辑也没处理
        foreach ($unbindStocks as &$unbindStock) {
            $unbindQty = $unbindStock['qty'];
            if ($unbindQty <= 0) {
                continue;
            }

            // 没有明细数量，则直接跳过
            if ($remainQty <= 0) {
                break;
            }

            if ($remainQty <= $unbindQty) {
                // 该销售单明细数量都可通过该条囤货记录绑定
                $bindQty = $remainQty;
                $isLast = $bindQty >= $unbindStock['qty'];
                // 扣减囤货库存
                $unbindStock['qty'] = $unbindQty - $bindQty;
                $remainQty = 0;
            } else {
                // 该条不够处理，需多次处理
                $bindQty = $unbindStock['qty'];
                $isLast = $bindQty >= $unbindStock['qty'];
                // 扣减囤货库存
                $unbindStock['qty'] = 0;
                $remainQty -= $bindQty;
            }

            $orderAssociatedRecords[] = [
                'sales_order_id' => $saleOrderLine->header_id,
                'sales_order_line_id' => $saleOrderLine->id,
                'order_id' => $unbindStock['ocOrderId'],
                'order_product_id' => $unbindStock['ocOrderProductId'],
                'qty' => $bindQty,
                'product_id' => $unbindStock['productId'],
                'seller_id' => $unbindStock['sellerId'],
                'buyer_id' => $unbindStock['buyerId'],
                'CreateUserName' => '1',
                'CreateTime' => Carbon::now(),
                'last' => $isLast,
            ];
        }

        unset($unbindStock);
        $this->buyerProductsStockPool->resetProductStocks($itemCode, $unbindStocks);

        return [$orderAssociatedRecords, $remainQty];
    }
}
