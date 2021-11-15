<?php

namespace App\Catalog\Forms\SalesOrder;

use App\Enums\Common\YesNoEnum;
use App\Enums\Country\Country;
use App\Enums\FeeOrder\FeeOrderStatus;
use App\Enums\Order\OcOrderStatus;
use App\Enums\Product\ProductTransactionType;
use App\Enums\Product\ProductType;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Exception\AssociatedPreException;
use App\Logging\Logger;
use App\Models\Customer\Customer;
use App\Models\FeeOrder\FeeOrder;
use App\Models\Futures\FuturesMarginAgreement;
use App\Models\Futures\FuturesMarginDelivery;
use App\Models\Margin\MarginAgreement;
use App\Models\Marketing\Coupon;
use App\Models\Marketing\MarketingTimeLimitProduct;
use App\Models\Order\Order;
use App\Models\Product\Product;
use App\Models\SalesOrder\CustomerSalesOrder;
use App\Models\SalesOrder\PurchasePayRecord;
use App\Repositories\Marketing\MarketingDiscountRepository;
use App\Repositories\Order\OrderRepository;
use App\Repositories\Safeguard\SafeguardConfigRepository;
use App\Repositories\SalesOrder\CustomerSalesOrderRepository;
use App\Services\FeeOrder\FeeOrderService;
use App\Services\FeeOrder\StorageFeeService;
use App\Services\Marketing\CampaignService;
use App\Services\Marketing\CouponService;
use App\Services\Order\OrderService;
use App\Services\Quote\QuoteService;
use App\Services\Stock\BuyerStockService;
use Cart\Currency;
use Exception;
use Framework\Model\RequestForm\RequestForm;
use Illuminate\Support\Collection;
use kriss\bcmath\BCS;
use ModelAccountCartCart;
use ModelAccountCustomer;
use ModelAccountCustomerOrderImport;
use ModelAccountSalesOrderMatchInventoryWindow;
use ModelCheckoutOrder;
use ModelCheckoutPreOrder;

// 销售单订单提交
class ConfirmSubmitForm extends RequestForm
{
    public $run_id;
    public $delivery_type;// 发货类型
    public $order_id;// 采购单ID如果传了将不会新生成采购单
    public $coupon_id;// 优惠券ID
    public $total_storage_fee;// 总仓租费,用来校验仓租是否发生变化了
    public $safeguards;// 123:{sales_order_id:123,safeguard_config_id:[1,2,3]} ps:123是sales order id
    public $sub_item_total;// 页面上的总货值，用作比较

    protected function getRules(): array
    {
        return [
            'run_id' => 'required',
            'delivery_type' => 'required',
            'order_id' => 'nullable',
            'coupon_id' => 'nullable|integer',
            'total_storage_fee' => 'nullable|numeric',
            'safeguards' => 'nullable|array',
            'sub_item_total' => 'required|numeric'
        ];
    }

    public function submit(): array
    {
        // 默认返回值
        $res = [
            'success' => false,
            'error' => '',
            'next_step' => [
                'type' => 1,// 1-刷新 2-跳转
                'url' => '',
                'btn_name' => '', // 刷新的按钮名称
            ],
        ];
        // 校验数据
        if (!$this->isValidated()) {
            // 校验不通过返回错误信息
            $res['error'] = $this->getFirstError();
            return $res;
        }
        Logger::salesOrder(['------销售单订单提交开始------', 'post' => $this->request->post()]);
        $buyerId = customer()->getId();
        // 获取下单信息
        $purchasePayRecords = PurchasePayRecord::query()
            ->where('run_id', '=', $this->run_id)
            ->where('customer_id', '=', $buyerId)
            ->get();
        // 校验销售订单状态
        $salesOrderIdArr = $purchasePayRecords->unique('order_id')->pluck('order_id')->toArray();
        $checkStatusSalesOrderIdArr = app(CustomerSalesOrderRepository::class)
            ->checkOrderStatus($salesOrderIdArr, CustomerSalesOrderStatus::TO_BE_PAID);
        if (count($checkStatusSalesOrderIdArr) != count($salesOrderIdArr)) {
            $orderIdStr = CustomerSalesOrder::query()
                ->whereIn('id', array_diff($salesOrderIdArr, $checkStatusSalesOrderIdArr))
                ->get(['order_id'])->implode('order_id', ',');
            $res['error'] = "The Order (ID:{$orderIdStr}) status has changed and the current operation is unavailable.";
            $res['next_step'] = [
                'type' => 2,
                'url' => $this->getSalesOrderUrl()
            ];
            Logger::salesOrder(['------销售单订单提交失败------', 'error' => $res['error']]);
            return $res;
        }
        if ($this->order_id) {
            // 如果传了采购单
            // 校验采购单状态
            $order = Order::findOrFail($this->order_id, ['order_status_id']);
            if ($order->order_status_id !== OcOrderStatus::TO_BE_PAID) {
                // 采购单已支付
                $res['error'] = 'This purchase Order is no longer valid.';
                $res['next_step'] = [
                    'type' => 2,
                    'url' => url(['account/order', 'filterOrderId' => $this->order_id, '#' => 'tab_purchase_order'])
                ];
                Logger::salesOrder(['------销售单订单提交失败------', 'error' => "采购单状态错误{$this->order_id}[{$order->order_status_id}]"]);
                return $res;
            }
        } else {
            try {
                $buyProducts = $this->getBuyProducts($buyerId, $purchasePayRecords);
                if (!empty($buyProducts)) {
                    // 校验采购商品数据
                    $validateProduct = app(OrderRepository::class)->validateBeforeCreateOrder($buyProducts, $buyerId, $this->delivery_type);
                    if (!$validateProduct['success']) {
                        $res['error'] = $validateProduct['error'];
                        $res['next_step'] = [
                            'type' => 2,
                            'url' => $this->getSalesOrderUrl()
                        ];
                        return $res;
                    }
                }
            } catch (Exception $exception) {
                $res['error'] = $exception->getMessage();
                $res['next_step'] = [
                    'type' => 2,
                    'url' => $this->getSalesOrderUrl()
                ];
                Logger::salesOrder(['------销售单订单提交失败------', 'error' => $res['error']]);
                Logger::salesOrder($exception);
                return $res;
            }
        }
        $db = db()->getConnection();
        try {
            $db->beginTransaction();
            /** @var ModelAccountSalesOrderMatchInventoryWindow $matchModel */
            $matchModel = load()->model('account/sales_order/match_inventory_window');
            /** @var ModelAccountSalesOrderMatchInventoryWindow $modelAccountSalesOrderMatchInventoryWindow */
            $modelAccountSalesOrderMatchInventoryWindow = load()->model('account/sales_order/match_inventory_window');
            //region 创建采购单
            // 校验囤货库存,无需判断返回，库存不足会抛出异常
            $matchModel->checkStockpileStockBuPurchaseList($this->run_id, $buyerId);
            if ($this->order_id) {
                $res['order_id'] = $this->order_id;
            } elseif (!empty($buyProducts)) {
                $orderId = $this->createOrder($buyProducts, $this->sub_item_total);
                // 预绑定库存
                $matchModel->associateOrder($orderId, $this->run_id, $buyerId);
                $res['order_id'] = $orderId;
            }
            //endregion
            //region 创建费用单
            // 创建仓租的费用单
            $feeOrderRunId = app(FeeOrderService::class)->generateFeeOrderRunId();
            $feeOrderList = array_values($modelAccountSalesOrderMatchInventoryWindow->createStorageFeeOrderByPurchaseList($this->run_id, $buyerId, $this->total_storage_fee, $feeOrderRunId));
            // 创建保单的费用单
            if ($this->safeguards) {
                $safeguards = array_combine(array_keys($this->safeguards), array_column($this->safeguards, 'safeguard_config_id'));
                $safeguardsFeeOrderList = app(FeeOrderService::class)->findOrCreateSafeguardFeeOrderIdsWithRunId($this->run_id, $buyerId, $safeguards, $feeOrderRunId);
                $feeOrderList = array_merge($feeOrderList, $safeguardsFeeOrderList);
            }
            $res['fee_order_list'] = implode(',', $feeOrderList);
            //endregion
            $needPayFeeOrderTotal = 0;
            if (!empty($feeOrderList)) {
                $needPayFeeOrderTotal = FeeOrder::query()
                    ->whereIn('id', $feeOrderList)
                    ->where('status', '=', FeeOrderStatus::WAIT_PAY)
                    ->sum('fee_total');
            }

            if (empty($res['order_id']) && $needPayFeeOrderTotal <= 0) {
                // 没有采购单并且没有费用单订单直接BP
                $this->notPay($buyerId, $purchasePayRecords);
                // 跳转到成功页
                $res['next_step'] = [
                    'type' => 2,
                    'url' => url('checkout/success')
                ];
            } else {
                // 销售订单选择使用囤货库存，需锁定囤货库存
                app(BuyerStockService::class)->inventoryLockBySalesOrderPreAssociated((string)$this->run_id, (int)$buyerId);

                // 跳转到支付页
                $params = ['checkout/confirm/toPay', 'order_source' => 'sale'];
                if (!empty($res['order_id'])) {
                    $params['order_id'] = $res['order_id'];
                }
                if (!empty($res['fee_order_list'])) {
                    $params['fee_order_list'] = $res['fee_order_list'];
                }
                $res['next_step'] = [
                    'type' => 2,
                    'url' => url($params)
                ];
            }
            Logger::salesOrder(['------销售单订单提交成功------', 'data' => $res]);
            $db->commit();
        } catch (AssociatedPreException $e) {
            $db->rollBack();
            $res['error'] = 'The information displayed on this page has been updated. Please go back to the Sales Order list page to make the payment again.';
            $res['next_step'] = [
                'type' => 1,
                'url' => $this->getSalesOrderUrl(['order_status' => 1]),
                'btn_name' => 'Go Back',
            ];
            return $res;
        } catch (Exception $exception) {
            if ($exception->getCode() == 406) {
                $db->commit();
            } else {
                $db->rollBack();
            }
            Logger::salesOrder(['------销售单订单提交失败------', 'error' => $exception->getMessage()]);
            Logger::salesOrder($exception);
            $res['error'] = $exception->getMessage();
            $res['next_step'] = [
                'type' => $exception->getCode() == 302 ? 1 : 2,
                'url' => $this->getSalesOrderUrl()
            ];
            return $res;
        }
        // 返回去支付信息、跳转信息
        $res['success'] = true;
        return $res;
    }

    /**
     * @param $buyerId
     * @param PurchasePayRecord[]|Collection $purchasePayRecords
     * @return bool
     * @throws Exception
     */
    private function notPay($buyerId, Collection $purchasePayRecords): bool
    {
        /** @var ModelAccountSalesOrderMatchInventoryWindow $modelAccountSalesOrderMatchInventoryWindow */
        $modelAccountSalesOrderMatchInventoryWindow = load()->model('account/sales_order/match_inventory_window');

        /** @var PurchasePayRecord[][]|Collection $notBuyProducts */
        $notBuyProducts = $purchasePayRecords->where('quantity', '=', 0)->groupBy('item_code');
        if ($notBuyProducts->isEmpty()) {
            return false;
        }
        // 分配库存
        $associateArr = [];
        foreach ($notBuyProducts as $itemCode => $purchasePayRecord) {
            $__itemCodeCostInfos = $modelAccountSalesOrderMatchInventoryWindow->getPurchaseOrderInfo($itemCode, $buyerId);
            foreach ($purchasePayRecord as $purchasePayItem) {
                // 使用数量就是销售数量，上面已经做了限制
                $useStockQty = $purchasePayItem->sales_order_quantity;
                // 开始循环消耗库存
                foreach ($__itemCodeCostInfos as $costKey => &$costInfo) {
                    //该采购订单明细剩余可用库存
                    $commonInfo = [
                        'sales_order_id' => $purchasePayItem->order_id,
                        'sales_order_line_id' => $purchasePayItem->line_id,
                        'order_id' => $costInfo['order_id'],
                        'order_product_id' => $costInfo['order_product_id'],
                        'product_id' => $costInfo['product_id'],
                        'seller_id' => $costInfo['seller_id'],
                        'buyer_id' => $buyerId,
                        'memo' => 'purchase record add',
                        'CreateUserName' => 'admin',
                        'CreateTime' => date('Y-m-d H:i:s'),
                        'ProgramCode' => 'V1.0'
                    ];
                    if ($costInfo['left_qty'] >= $useStockQty) {
                        // 如果该批次库存大于使用库存，直接生成数据
                        $commonInfo['qty'] = $useStockQty;
                        $associateArr[] = $commonInfo;
                        $costInfo['left_qty'] = $costInfo['left_qty'] - $useStockQty;
                        if ($costInfo['left_qty'] <= 0) {
                            // 消耗完直接unset掉
                            unset($__itemCodeCostInfos[$costKey]);
                        }
                        continue 2;// 直接下个订单产品
                    } else {
                        // 该采购订单明细不够使用
                        // 有多少用多少
                        $commonInfo['qty'] = $costInfo['left_qty'];
                        $associateArr[] = $commonInfo;
                        // 修改还需使用的数量
                        $useStockQty = $useStockQty - $costInfo['left_qty'];
                        // 消耗完直接unset掉
                        unset($__itemCodeCostInfos[$costKey]);
                    }
                }
                // 如果走到这步说明库存不足
                throw new Exception($itemCode . ' not has enough available inventory.');
            }
        }

        $associatedRecordId = [];
        $salesOrderList = [];// 暂存销售订单明细
        foreach ($associateArr as $associatedRecord) {
            //依次插入数据，并获取id，用于下一步修改仓租信息
            $discountsAmount = app(OrderService::class)->orderProductWillAssociateDiscountsAmount(intval($associatedRecord['order_product_id']), intval($associatedRecord['qty']), customer()->isJapan() ? 0 : 2);
            $associatedRecord = array_merge($associatedRecord, $discountsAmount);
            $associatedRecordId[] = $modelAccountSalesOrderMatchInventoryWindow->associatedOrderByRecordGetId($associatedRecord);
            $sales_order_id = $associatedRecord['sales_order_id'];
            $sales_order_line_id = $associatedRecord['sales_order_line_id'];
            if (empty($salesOrderList[$sales_order_id]) || !in_array($sales_order_line_id, $salesOrderList[$sales_order_id])) {
                $salesOrderList[$sales_order_id][] = $sales_order_line_id;
            }
        }
        /** @var ModelAccountCustomerOrderImport $modelCustomerOrderImport */
        $modelCustomerOrderImport = load()->model('account/customer_order_import');
        // 循环更新销售单信息
        foreach ($salesOrderList as $salesOrderId => $salesOrderLineIds) {
            foreach ($salesOrderLineIds as $salesOrderLineId) {
                // 更新订单明细信息
                $modelCustomerOrderImport->updateCustomerSalesOrderLine($salesOrderId, $salesOrderLineId);
            }
            // 更新订单状态
            $modelCustomerOrderImport->updateCustomerSalesOrder($salesOrderId);
        }
        // 修改仓租表信息
        app(StorageFeeService::class)->bindByOrderAssociated($associatedRecordId);
        return true;
    }

    /**
     * @param int $buyerId
     * @param PurchasePayRecord[]|Collection $purchasePayRecords
     * @return array
     * @throws Exception
     */
    private function getBuyProducts(int $buyerId, $purchasePayRecords): array
    {
        /** @var PurchasePayRecord[]|Collection $buyList */
        $buyList = $purchasePayRecords->where('quantity', '>', 0);// 需要采购的列表
        if ($buyList->isEmpty()) {
            return [];
        }
        $isCollectionFromDomicile = customer()->isCollectionFromDomicile();
        $countryId = customer()->getCountryId();
        // 校验需要采购的数据
        $products = [];
        foreach ($buyList as $item) {
            if (!$item->product_id) {
                // 数据错误，不能没有product id
                continue;
            }
            $__productId = $item->product_id;
            $buyProduct = $products[$__productId] ?? [];
            if (empty($buyProduct)) {
                // 不存在初始化数据
                $buyProduct = [
                    'product_id' => $item->product_id,
                    'transaction_type' => $item->type_id,
                    'quantity' => $item->quantity,
                    'agreement_id' => $item->agreement_id,
                    'seller_id' => $item->seller_id,
                    'add_cart_type' => 0,
                ];
            } else {
                // 存在则判断一些特殊的
                if ($item->type_id != $buyProduct['transaction_type']) {
                    // 相同产品的不同交易方式不允许创建订单
                    throw new Exception('The transaction type of ' . $item->item_code . ' is inconsistent in the selected Sales Orders.');
                }
                $buyProduct['quantity'] += $item->quantity;
            }
            $products[$__productId] = $buyProduct;
        }
        /** @var ModelCheckoutPreOrder $modelCheckoutPreOrder */
        $modelCheckoutPreOrder = load()->model('checkout/pre_order');
        return $modelCheckoutPreOrder->handleProductsData(array_values($products), $buyerId, $this->delivery_type, $isCollectionFromDomicile, $countryId);
    }

    /**
     * @param array $products
     * @return int
     * @throws Exception
     */
    private function createOrder(array $products, $itemsCostTotal): int
    {
        $customer = Customer::findOrFail(customer()->getId());
        /** @var ModelAccountCartCart $modelAccountCartCart */
        $modelAccountCartCart = load()->model('account/cart/cart');
        /** @var ModelAccountCustomer $modelAccountCustomer */
        $modelAccountCustomer = load()->model('account/customer');
        /** @var ModelCheckoutOrder $modelCheckoutOrder */
        $modelCheckoutOrder = load()->model('checkout/order');
        //region 订单基础信息
        $currency = session('currency');
        $precision = $customer->country_id == Country::JAPAN ? 0 : 2;
        /** @var Currency $currencyLibrary */
        $currencyLibrary = app()->ocRegistry->get('currency');
        $totalData = $modelAccountCartCart->orderTotalShow($products, true,
            [
                'coupon_ids' => (empty($this->coupon_id) ? [] : [$this->coupon_id]),
                'limit_quote' => false
            ]);
        $totalDataCollection = collect($totalData['totals'])->keyBy('code');
        $subItemsTotal = $totalDataCollection['sub_total']['value'] + ($totalDataCollection['wk_pro_quote']['value'] ?? 0); // 总货值金额
        // 校验总金额是否一致
        if (BCS::create($subItemsTotal, ['scale' => 2])->compare($itemsCostTotal) !== 0) {
            throw new Exception('The current price is invalid. Please refresh the page and try again.');
        }
        $orderData = [
            'totals' => $totalData['all_totals'],
            'invoice_prefix' => configDB('config_invoice_prefix'),
            'store_id' => configDB('config_store_id'),
            'store_name' => configDB('config_name'),
            'customer_id' => $customer->customer_id,
            'customer_group_id' => $customer->customer_group_id,
            'firstname' => $customer->firstname,
            'lastname' => $customer->lastname,
            'email' => $customer->email,
            'telephone' => $customer->telephone,
            'custom_field' => json_decode($customer->custom_field, true),
            'total' => $totalData['total']['value'],
            'language_id' => configDB('config_language_id'),
            'currency_id' => $currencyLibrary->getId($currency),
            'currency_code' => $currency,
            'current_currency_value' => $currencyLibrary->getValue($currency),
            'currency_value' => 1,
            'ip' => $this->request->serverBag->get('REMOTE_ADDR', ''),
            'forwarded_ip' => $this->request->serverBag->get('HTTP_X_FORWARDED_FOR') ? $this->request->serverBag->get('HTTP_X_FORWARDED_FOR') : $this->request->serverBag->get('HTTP_CLIENT_IP', ''),
            'user_agent' => $this->request->serverBag->get('HTTP_USER_AGENT', ''),
            'accept_language' => $this->request->serverBag->get('HTTP_ACCEPT_LANGUAGE', ''),
            'products' => []
        ];
        if ($orderData['store_id']) {
            $orderData['store_url'] = configDB('config_url');
        } else {
            if ($this->request->serverBag->get('HTTPS')) {
                $orderData['store_url'] = HTTPS_SERVER;
            } else {
                $orderData['store_url'] = HTTP_SERVER;
            }
        }
        //endregion
        $campaigns = $totalDataCollection['promotion_discount']['discounts'] ?? []; // 促销活动
        $serviceFeeTotal = $totalDataCollection['service_fee']['value'] ?? 0; // 总服务费
        $subItemsAndServiceFeeTotal = bcadd($subItemsTotal, $serviceFeeTotal, 4); //总货值金额+总服务费
        // 计算每个产品占用满减份额
        $campaignPerDiscount = app(CampaignService::class)->calculateMutiCampaignDiscount($campaigns, $products, $precision);
        // 判断是否使用优惠券
        if ($this->coupon_id) {
            $coupon = Coupon::find($this->coupon_id);
            // 计算每个产品占用优惠券份额
            $couponPerDiscount = app(CouponService::class)->calculateCouponDiscount($coupon->denomination, $products, $precision);
        }
        //region 组装product数据
        // 组合 oc_order_product的数据
        foreach ($products as $product) {
            $discount = null;
            // 协议折扣失效判断
            if ($product['transaction_type'] == ProductTransactionType::MARGIN) {
                $agreement = MarginAgreement::query()->where('id', $product['agreement_id'])->first(['discount', 'discount_price', 'product_id', 'is_bid']); //产品折扣
                $discount = $agreement->discount;
                $product['discount_price'] = $agreement->discount_price;
                $product['discount_info'] && $product['discount_info']->buy_qty = $product['quantity'];
            } elseif ($product['transaction_type'] == ProductTransactionType::FUTURE) {
                $agreement = FuturesMarginAgreement::query()->where('id', $product['agreement_id'])->first(['discount', 'discount_price', 'product_id', 'is_bid']); //产品折扣
                $discount = $agreement->discount;
                $product['discount_price'] = $agreement->discount_price;
            } elseif ($product['transaction_type'] == ProductTransactionType::NORMAL && $product['product_type'] == ProductType::NORMAL) {
                $discount = $product['discount_info']->discount ?? null; //产品折扣
                $product['discount_info'] && $product['discount_info']->buy_qty = $product['quantity'];
            }


            $orderData['products'][] = array(
                'product_id' => $product['product_id'],
                'name' => $product['name'],
                'model' => $product['model'],
                'option' => [],
                'quantity' => $product['quantity'],
                'subtract' => $product['subtract'],
                'price' => round($product['product_price_per'], 2),//使用货值，即上门取货价
                'serviceFeePer' => round($product['service_fee_per'], 2),
                'serviceFee' => round($product['service_fee_per'], 2) * $product['quantity'],
                'total' => round($product['current_price'], 2) * $product['quantity'],
                'tax' => 0,
                'freight_per' => $product['freight_per'],//单件运费（基础运费+超重附加费）
                'base_freight' => $product['base_freight'],//基础运费
                'overweight_surcharge' => $product['overweight_surcharge'],//超重附加费
                'package_fee' => $product['package_fee_per'], //打包费
                'coupon_amount' => $couponPerDiscount[$product['product_id']]['discount'] ?? 0, //优惠券金额
                'campaign_amount' => $campaignPerDiscount[$product['product_id']]['discount'] ?? 0, //满减金额
                'discount' => $discount, //产品折扣
                'discount_price' => $product['discount_price'] ?? 0, //产品折扣
                'discount_info' => $product['discount_info'],
                'type_id' => $product['transaction_type'],
                'agreement_id' => $product['agreement_id'],
                'volume' => $product['volume'],
                'volume_inch' => $product['volume_inch'],
                'danger_flag'=> $product['danger_flag'] ?? 0,
            );
        }
        //endregion
        //region 订单保存
        // 设置delivery type，addOrder里面有用到
        session()->set('delivery_type', $this->delivery_type);
        $orderId = $modelCheckoutOrder->addOrder($orderData);
        // 议价
        app(QuoteService::class)->addOrderQuote($products, $customer->customer_id, $orderId, $customer->country_id, customer()->isEurope());
        //endregion
        //region 处理订单支付结束
        // 不走购物车，记录0
        cache()->set('order_id_' . $orderId, 0);
        // 预扣库存
        $modelCheckoutOrder->withHoldStock($orderId);
        // 优惠券设置为已使用
        app(CouponService::class)->setCouponUsed($orderId, $this->coupon_id, $subItemsAndServiceFeeTotal);
        // 记录订单参与的促销活动
        app(CampaignService::class)->addCampaignOrder(array_merge($campaigns, $totalData['gifts']), $orderId);
        //endregion
        return $orderId;
    }

    /**
     * @param array $filterConditions
     * @return \Framework\Route\Url|string
     */
    private function getSalesOrderUrl(array $filterConditions = [])
    {
        if (customer()->isCollectionFromDomicile()) {
            // 上门取货
            $url = ['account/customer_order', 'tabIndex' => 1, 'initFilter' => 0, 'filter_flag' => 1];
        } else {
            // 一件代发
            $url = ['account/sales_order/sales_order_management'];
        }

        if (isset($filterConditions['order_status'])) {
            $url['filter_orderStatus'] = $filterConditions['order_status'];
        }

        return url($url);
    }
}
