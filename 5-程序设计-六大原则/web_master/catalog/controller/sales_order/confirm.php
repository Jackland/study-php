<?php

use App\Catalog\Controllers\AuthBuyerController;
use App\Catalog\Forms\SalesOrder\ConfirmSubmitForm;
use App\Enums\Country\Country;
use App\Enums\FeeOrder\FeeOrderFeeType;
use App\Enums\Order\OrderDeliveryType;
use App\Enums\Product\ProductTransactionType;
use App\Enums\Product\ProductType;
use App\Enums\SalesOrder\HomePickImportMode;
use App\Helper\CurrencyHelper;
use App\Logging\Logger;
use App\Models\Link\OrderAssociatedPre;
use App\Models\SalesOrder\PurchasePayRecord;
use App\Repositories\FeeOrder\FeeOrderRepository;
use App\Repositories\FeeOrder\StorageFeeRepository;
use App\Repositories\Margin\MarginRepository;
use App\Repositories\Marketing\CouponRepository;
use App\Repositories\Order\OrderRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Safeguard\SafeguardConfigRepository;
use App\Repositories\Seller\SellerRepository;
use Framework\Exception\Http\NotFoundException;
use Illuminate\Support\Collection;
use kriss\bcmath\BCS;

// 销售订单确认页
class ControllerSalesOrderConfirm extends AuthBuyerController
{
    // 该页面的数字计算全局配置
    private $bcsConfig = [
        'scale' => 4
    ];

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->load->language('account/sales_order/sales_order_management');
    }

    /**
     * 订单确认页
     * run_id
     * order_id 默认0
     * @throws NotFoundException
     */
    public function index()
    {
        // 校验
        $validator = validator($this->request->get(), [
            'run_id' => 'required|numeric'
        ]);
        if ($validator->fails()) {
            throw new NotFoundException($validator->errors()->first());
        }
        try {
            $data = $this->getData();
        } catch (Exception $exception) {
            Logger::salesOrder(['支付信息获取失败', 'error' => $exception->getMessage(), 'params' => $this->request->get()]);
            Logger::salesOrder($exception);
            throw new NotFoundException('Data exception.');
        }
        $data['source'] = $this->request->get('source');
        $data['run_id'] = $this->request->get('run_id');
        $data['order_id'] = $this->request->get('order_id', 0);
        $countryId = customer()->getCountryId();
        $data['is_europe'] = customer()->isEurope();
        $data['symbolLeft'] = $this->currency->getSymbolLeft(CurrencyHelper::getCurrentCode());
        $data['symbolRight'] = $this->currency->getSymbolRight(CurrencyHelper::getCurrentCode());
        $data['precision'] = customer()->getCountryId() == Country::JAPAN ? 0 : 2;
        $data['storage_fee_description_id'] = app(StorageFeeRepository::class)->getStorageFeeDescriptionId($countryId);
        $data['delivery_type'] = customer()->isCollectionFromDomicile() ? OrderDeliveryType::WILL_CALL : OrderDeliveryType::DROP_SHIPPING;

        return $this->render('sales_order/confirm/index', $data, 'buyer');
    }

    /**
     * 获取订单确认数据
     *
     * @throws NotFoundException
     * @throws Exception
     */
    private function getData(): array
    {
        $runId = $this->request->get('run_id');
        $orderId = $this->request->get('order_id');
        $secondPayment = $orderId > 0;// 是否是二次支付
        $buyerId = customer()->getId();
        $countryId = customer()->getCountryId();
        /** @var ModelCheckoutPreOrder $modelPreOrder */
        $modelPreOrder = load()->model('checkout/pre_order');
        /** @var ModelExtensionTotalPromotionDiscount $modelExtensionTotalPromotionDiscount */
        $modelExtensionTotalPromotionDiscount = load()->model('extension/total/promotion_discount');
        /** @var ModelExtensionTotalGigaCoupon $modelExtensionTotalGigaCoupon */
        $modelExtensionTotalGigaCoupon = load()->model('extension/total/giga_coupon');
        // 获取采购+囤货数据
        $purchasePayRecords = PurchasePayRecord::query()
            ->with(['salesOrder'])
            ->where('run_id', '=', $runId)
            ->where('customer_id', '=', $buyerId)
            ->get();
        if ($purchasePayRecords->isEmpty()) {
            // 数据不存在
            throw new NotFoundException('Data exception.');
        }
        $salesOrderList = [];
        $buyPurchasePayRecords = $purchasePayRecords->where('quantity', '>', 0);
        // 获取商品数据（图片 tags）
        $productInfoList = app(ProductRepository::class)
            ->getProductInfoByProductId($buyPurchasePayRecords->pluck('product_id')->toArray());
        // 暂存所有seller信息
        $sellerInfoList = app(SellerRepository::class)->getSellerInfo($buyPurchasePayRecords->pluck('seller_id')->toArray())
            ->pluck('screenname', 'customer_id');
        // 获取需要采购的产品采购价格信息
        $neeBuyProductPriceInfoList = $this->getPurchaseProductsPriceInfo($buyPurchasePayRecords,$countryId);
        // 获取预绑信息
        $associatedPres = $this->getSalesOrderAssociatedPreByPurchaseRecords($runId, $purchasePayRecords);
        $associatedPreIds = $associatedPres->pluck('id')->toArray();
        $associatedPres = $associatedPres->groupBy(['sales_order_id', 'sales_order_line_id']);
        // 获取仓租数据
        $orderStorageFeeInfos = [];
        $feeOrderList = app(FeeOrderRepository::class)->getCanPayFeeOrderByRunId($runId, $buyerId, FeeOrderFeeType::STORAGE);
        if ($feeOrderList) {
            // 如果有未支付的费用单，优先用费用单的信息
            $feeOrderList = app(StorageFeeRepository::class)->getDetailsByFeeOrder(array_values($feeOrderList), true);
            foreach ($feeOrderList as $feeOrderDetails) {
                foreach ($feeOrderDetails as $feeOrderDetail) {
                    $orderStorageFeeInfos[$feeOrderDetail['fee_order_id']][$feeOrderDetail['order_product_id']] = $feeOrderDetail;
                }
            }
        } else {
            $orderStorageFeeInfos = app(StorageFeeRepository::class)->getAllCanBind($associatedPreIds, $purchasePayRecords->toArray(), true);
        }
        // 各单项总和
        $itemsCost = BCS::create(0, $this->bcsConfig);
        $serviceFee = BCS::create(0, $this->bcsConfig);
        $freightFee = BCS::create(0, $this->bcsConfig);
        $storageFee = BCS::create(0, $this->bcsConfig);
        // 循环构建数据
        foreach ($purchasePayRecords as $purchasePayRecord) {
            $__salesOrderId = $purchasePayRecord->order_id;
            $__salesOrder = $salesOrderList[$__salesOrderId] ?? [];// 订单数据
            //region 订单基础数据
            if (empty($__salesOrder)) {
                // 基础数据
                $__shipInfo = $purchasePayRecord->salesOrder->delivery_address_info;
                // 是否可以购买保障服务
                $canBuySafeguard = true;
                if ($purchasePayRecord->salesOrder->import_mode == HomePickImportMode::IMPORT_MODE_BUYER_PICK_UP) {
                    // 如果是自提货的，取收货人信息
                    if (!$purchasePayRecord->salesOrder->pickUp) {
                        throw new Exception('Data exception.');
                    }
                    $__shipInfo = $purchasePayRecord->salesOrder->pickUp->user_name . ','
                        . $purchasePayRecord->salesOrder->pickUp->user_phone . ','
                        . $purchasePayRecord->salesOrder->pickUp->warehouse->full_address;
                    $canBuySafeguard = false;
                }
                $__salesOrder = [
                    'id' => $purchasePayRecord->order_id,
                    'sales_order_id' => $purchasePayRecord->salesOrder->order_id,
                    'ship_info' => $__shipInfo,
                    'total' => 0,
                    'product_total' => 0,
                    'can_buy_safeguard' => $canBuySafeguard,
                ];
            }
            // 本次支付销售单总共需要支出的费用，不包含保障服务费
            $__salesOrderTotal = BCS::create($__salesOrder['total'], $this->bcsConfig);
            // 该销售单所有的货款，包括囤货库存的采购货款
            $__salesOrderProductTotal = BCS::create($__salesOrder['product_total'], $this->bcsConfig);
            // 该line的总支出
            $__lineTotal = BCS::create($__salesOrder['product_total'], $this->bcsConfig);
            //endregion
            //region lines
            //region 价格、数量、仓租
            $__lineBuyProductInfo = null;// 临时存放整个line的所有货款明细
            $__lineBuyFreightInfo = null;// 临时存放整个line的所有运费
            $__lineStorageFeeList = [];// 临时存放整个line的所有仓租明细
            //region 需要购买的数据
            $__buyData = null;
            if ($purchasePayRecord->quantity > 0) {
                // 获取商品价格信息
                $__buyPriceKey = $this->generatePriceKey($purchasePayRecord);
                if (empty($neeBuyProductPriceInfoList[$__buyPriceKey])) {
                    throw new Exception('Price Data exception.');
                }
                $__buyPriceInfo = $neeBuyProductPriceInfoList[$__buyPriceKey];
                $__buyStockStorage = BCS::create(0, $this->bcsConfig);
                // 如果现货尾款采购，获取仓租
                if ($purchasePayRecord->type_id == ProductTransactionType::MARGIN) {
                    // 现货仓租
                    // 获取头款产品
                    $advanceOrderProduct = app(MarginRepository::class)->getAdvanceOrderProductByAgreementId($purchasePayRecord->agreement_id);
                    if ($advanceOrderProduct) {
                        $__storageFeeData = $orderStorageFeeInfos[$__salesOrderId][$advanceOrderProduct->order_product_id] ?? [];
                        if ($__storageFeeData) {
                            $__buyStockStorage->add($__storageFeeData['need_pay']);
                            $__lineStorageFeeList[] = $__storageFeeData;
                        }
                    }
                }
                $__itemsCost = $__buyPriceInfo['product_price_per'] * $purchasePayRecord->quantity;// 总货值
                $__serviceFee = $__buyPriceInfo['service_fee_per'] * $purchasePayRecord->quantity;//总服务费
                $__freightFee = BCS::create($__buyPriceInfo['freight'], $this->bcsConfig)->add($__buyPriceInfo['package_fee'])->mul($purchasePayRecord->quantity)->getResult();// 总运费
                $__storageFee = $__buyStockStorage->getResult();// 仓租费
                $__buyTotal = BCS::create($__itemsCost, $this->bcsConfig)->add($__serviceFee, $__freightFee, $__storageFee)->getResult();
                $__buyData = [
                    'items_cost' => $__itemsCost,
                    'service_fee' => $__serviceFee,
                    'freight_fee' => $__freightFee,
                    'storage_fee' => $__storageFee,
                    'quantity' => $purchasePayRecord->quantity,
                    'total' => $__buyTotal,
                ];
                // 销售单需要支出的总和
                $__salesOrderTotal->add($__buyData['total']);
                // 销售单需要采购的支出总和，除仓租,如果有头款，头款也要加上
                $__salesOrderProductTotal->add($__itemsCost, $__serviceFee,
                    $__freightFee, ($__buyPriceInfo['deposit_per'] * $purchasePayRecord->quantity));
                // 该sku的总和
                $__lineTotal->add($__buyTotal);
                // 统计各单项的总和
                $itemsCost->add($__itemsCost);
                $serviceFee->add($__serviceFee);
                $freightFee->add($__freightFee);
                $storageFee->add($__storageFee);
                // 需要购买的产品基础价格信息、交易方式、店铺名称
                $__lineBuyProductInfo = [
                    'quantity' => $purchasePayRecord->quantity,
                    'seller_name' => $sellerInfoList[$purchasePayRecord->seller_id] ?? '',
                    'type_id' => $purchasePayRecord->type_id,
                    'transaction_type' => ProductTransactionType::getDescription($purchasePayRecord->type_id),
                    'agreement_code' => $__buyPriceInfo['agreement_code'],
                    'price' => $__buyPriceInfo['product_price_per'],// 单价
                    'service_fee' => $__buyPriceInfo['service_fee_per'],// 服务费
                    'total' => $__buyData['items_cost'] + $__buyData['service_fee'],// 单价
                ];
                // 需要购买的产品基础运费信息
                $__lineBuyFreightInfo = [
                    'quantity' => $purchasePayRecord->quantity,
                    'freight_fee' => $__buyPriceInfo['freight'],// 单件运费
                    'package_fee' => $__buyPriceInfo['package_fee'],// 单件打包费
                    'freight_fee_total' => $__buyData['freight_fee'],// 总运费
                ];
            }
            //endregion
            //region 使用囤货的数据
            $__inStockData = null;// 使用囤货库存数据
            if ($purchasePayRecord->sales_order_quantity > $purchasePayRecord->quantity) {
                // 使用囤货
                /** @var OrderAssociatedPre[] $__associatedPres */
                $__associatedPres = $associatedPres[$__salesOrderId][$purchasePayRecord->line_id] ?? [];
                $__inStockItemsCost = BCS::create(0, $this->bcsConfig);
                $__inStockFreight = BCS::create(0, $this->bcsConfig);
                $__inStockService = BCS::create(0, $this->bcsConfig);
                $__inStockStorage = BCS::create(0, $this->bcsConfig);
                // 处理产品价格信息
                foreach ($__associatedPres as $__associatedPre) {
                    $__orderProduct = app(OrderRepository::class)->getOrderProductPrice($__associatedPre->orderProduct);
                    $__inStockItemsCost->add($__orderProduct->price * $__associatedPre->qty);
                    $__inStockFreight->add(($__orderProduct->freight_per + $__orderProduct->package_fee) * $__associatedPre->qty);
                    $__inStockService->add($__orderProduct->service_fee_per * $__associatedPre->qty);
                    $__storageFeeData = $orderStorageFeeInfos[$__salesOrderId][$__associatedPre->order_product_id] ?? [];
                    if ($__storageFeeData) {
                        $__inStockStorage->add($__storageFeeData['need_pay']);
                        $__lineStorageFeeList[] = $__storageFeeData;
                    }
                }
                // 处理库存数据
                $__inStockItemsCost = $__inStockItemsCost->getResult();
                $__inStockFreight = $__inStockFreight->getResult();
                $__inStockService = $__inStockService->getResult();
                $__inStockStorage = $__inStockStorage->getResult();
                $__inStockData = [
                    'product_id' => $__associatedPres[0]['product_id'],
                    'items_cost' => $__inStockItemsCost,
                    'service_fee' => $__inStockService,
                    'freight_fee' => $__inStockFreight,
                    'storage_fee' => $__inStockStorage,
                    'quantity' => $purchasePayRecord->sales_order_quantity - $purchasePayRecord->quantity,
                    'total' => $__inStockStorage,
                ];
                // 销售单需要支出的总和
                $__salesOrderTotal->add($__inStockData['total']);
                // 销售单囤货采购的支出总和，除仓租
                $__salesOrderProductTotal->add($__inStockItemsCost, $__inStockService, $__inStockFreight);
                // 该sku总和
                $__lineTotal->add($__inStockStorage);
                // 统计单项总和
                $storageFee->add($__inStockStorage);
            }
            //endregion
            //region 产品信息
            $__productInfo = [];
            if (isset($productInfoList[$purchasePayRecord->product_id])) {
                $__productInfo = $productInfoList[$purchasePayRecord->product_id];
            } elseif (!empty($__lineStorageFeeList)) {
                $__productInfo = app(ProductRepository::class)->getProductInfoByProductId($__lineStorageFeeList[0]['product_id']);
            } elseif (!empty($__inStockData['product_id'])) {
                $__productInfo = app(ProductRepository::class)->getProductInfoByProductId($__inStockData['product_id']);
                unset($__inStockData['product_id']);
            }
            //endregion

            $__salesOrder['lines'][] = [
                'product_info' => $__productInfo,
                'quantity' => $purchasePayRecord->sales_order_quantity,
                'buy_data' => $__buyData,
                'in_stock_data' => $__inStockData,
                'buy_product_info' => $__lineBuyProductInfo,
                'buy_freight_info' => $__lineBuyFreightInfo,
                'storage_fee_list' => $__lineStorageFeeList,
                'line_total_fee' => $__lineTotal->getResult()
            ];
            $__salesOrderTotal = $__salesOrderTotal->getResult();
            $__salesOrderProductTotal = $__salesOrderProductTotal->getResult();
            $__salesOrder['total'] = $__salesOrderTotal;
            $__salesOrder['product_total'] = $__salesOrderProductTotal;
            //endregion
            //endregion
            $salesOrderList[$__salesOrderId] = $__salesOrder;
        }
        //region 二次循环填充保障服务数据，因为保障服务数据需要基于产品数据
        list($salesOrderList,$feeOrderSecondPayment) = $this->getSalesOrderSafeguardList($buyerId, $salesOrderList, $runId);
        $secondPayment = $secondPayment || $feeOrderSecondPayment;// 只要采购单或者费用单二次支付都判定为二次支付
        //endregion
        $data['sales_order_list'] = array_values($salesOrderList);
        //region 优惠券
        $buyTotal = 0;
        $gifts = []; // 满送
        $discounts = []; // 满减
        $totalData = [
            'totals' => &$totals,
            'total' => &$buyTotal,
            'gifts' => &$gifts,
            'discounts' => &$discounts,
        ];
        list($purchaseTotal, $purchaseProducts) = $this->handlePurchaseProductsBySalesOrder($salesOrderList);
        $modelExtensionTotalPromotionDiscount->getTotalByProducts($totalData, $purchaseProducts);
        $modelExtensionTotalGigaCoupon->getTotalByProducts($totalData, $purchaseProducts);
        $collection = collect($totalData['totals']);
        $data['campaign']['campaign_discount'] = $collection->where('code', 'promotion_discount')->first()['value'] ?? 0; // 满减金额
        $data['campaign']['campaigns'] = $discounts; // 促销活动
        array_map(function ($gift) {
            $gift->is_coupon = !empty($gift->conditions[$gift->id]->couponTemplate);
            $gift->condition_remark = $gift->conditions[$gift->id]->remark;
            if ($gift->is_coupon) {
                $gift->format_coupon_price = $this->currency->formatCurrencyPrice($gift->conditions[$gift->id]->couponTemplate->denomination, $this->session->get('currency'));
            }
        }, $gifts);
        // 满送
        $data['gifts'] = $gifts;
        $selectCoupon = $collection->where('code', 'giga_coupon')->first();
        if ($orderId) {
            $coupons = app(CouponRepository::class)->getCouponByOrderId($orderId);
            if ($coupons->isNotEmpty()) {
                $selectCoupon['coupon_ids'] = $coupons->pluck('id')->toArray();
                $selectCoupon['value'] = $coupons->sum('denomination') * -1;
                $selectCoupon['denomination'] = $coupons[0]->denomination;
                $selectCoupon['order_amount'] = $coupons[0]->order_amount;
            }
        }
        $data['selected_coupon'] = $selectCoupon;
        $data['coupon'] = $modelPreOrder->getPreOrderCoupons($purchaseTotal, $selectCoupon['coupon_ids'], abs($data['campaign']['campaign_discount']));
        //endregion
        //region 统计
        $itemsCost = $itemsCost->getResult();
        $serviceFee = $serviceFee->getResult();
        $freightFee = $freightFee->getResult();
        $storageFee = $storageFee->getResult();
        $discountFee = BCS::create($selectCoupon['value'], $this->bcsConfig)->add($data['campaign']['campaign_discount'])->getResult();
        $totalFee = BCS::create(0, $this->bcsConfig)->add($itemsCost, $serviceFee, $freightFee, $storageFee);
        $data['total_data'] = [
            'items_cost' => $itemsCost,
            'service_fee' => $serviceFee,
            'freight_fee' => $freightFee,
            'storage_fee' => $storageFee,
            'discount_fee' => $discountFee,// 优惠金额
            'total_fee' => $totalFee->getResult(),
            'discount_total_fee' => $totalFee->add($discountFee)->getResult(),// 优惠后总金额
        ];
        //endregion
        $data['second_payment'] = $secondPayment;
        return $data;
    }

    /**
     * 获取销售单的预绑定信息
     *
     * @param $runId
     * @param PurchasePayRecord[] $purchasePayRecords
     * @return OrderAssociatedPre[]|Collection
     */
    private function getSalesOrderAssociatedPreByPurchaseRecords($runId, $purchasePayRecords)
    {
        $orderAssociatedPreWhere = [];
        foreach ($purchasePayRecords as $purchasePayRecord) {
            if ($purchasePayRecord->sales_order_quantity > $purchasePayRecord->quantity) {
                $orderAssociatedPreWhere[] = [
                    'sales_order_id' => $purchasePayRecord->order_id,
                    'sales_order_line_id' => $purchasePayRecord->line_id
                ];
            }
        }
        if (empty($orderAssociatedPreWhere)) {
            return collect();
        }
        return OrderAssociatedPre::query()
            ->with(['orderProduct'])
            ->where('run_id', '=', $runId)
            ->where('associate_type', '=', 1)
            ->where(function ($query) use ($orderAssociatedPreWhere) {
                foreach ($orderAssociatedPreWhere as $where) {
                    $query->orWhere(function ($orderWhere) use ($where) {
                        $orderWhere->where('sales_order_id', '=', $where['sales_order_id'])
                            ->where('sales_order_line_id', '=', $where['sales_order_line_id']);
                    });
                }
            })->get();
    }

    private function getSalesOrderSafeguardList($buyerId, $salesOrderList, $runId)
    {
        // 获取可购买的所有保障服务
        $safeguardConfigs = app(SafeguardConfigRepository::class)->getBuyerConfigs($buyerId);
        // 获取二次支付已选择的保障服务
        $safeguardFeeOrderConfigList = [];// 已选择的保障服务
        $safeguardFeeOrderList = app(FeeOrderRepository::class)->getFeeOrderByRunId($runId, $buyerId, FeeOrderFeeType::SAFEGUARD);
        $secondPayment = $safeguardFeeOrderList->isNotEmpty();
        foreach ($safeguardFeeOrderList as $safeguardFeeOrder) {
            $safeguardFeeOrderList->load('safeguardDetails');
            foreach ($safeguardFeeOrder->safeguardDetails as $safeguardDetail) {
                $safeguardFeeOrderConfigList[$safeguardFeeOrder->order_id][] = $safeguardDetail->safeguard_config_id;
            }
        }
        // 循环销售单放入保障服务列表
        foreach ($salesOrderList as $salesOrderId => &$__salesOrder) {
            if (empty($__salesOrder['can_buy_safeguard'])) {
                continue;
            }
            $__salesOrderProductMax = max(array_column($__salesOrder['lines'], 'quantity'));
            $__salesOrderSelectSafeguard = $safeguardFeeOrderConfigList[$salesOrderId] ?? [];// 获取该销售单选过的保障服务id
            foreach ($safeguardConfigs as $safeguardConfig) {
                // 保障服务的服务费
                $__safeguardServiceFee = BCS::create($__salesOrder['product_total'], $this->bcsConfig)
                    ->mul($safeguardConfig['config']['service_rate'])->getResult();
                $__safeguardConfigItem = [
                    'id' => $safeguardConfig['config']['id'],
                    'title' => $safeguardConfig['config']['title'],
                    'title_cn' => $safeguardConfig['config']['title_cn'],
                    'is_select' => in_array($safeguardConfig['config']['id'], $__salesOrderSelectSafeguard),// 是否默认选中
                    'can_select' => $safeguardConfig['config']['order_product_max'] == -1 || $__salesOrderProductMax <= $safeguardConfig['config']['order_product_max'],
                    'service_rate' => $safeguardConfig['config']['service_rate'] * 100 . '%',
                    'service_fee' => $__safeguardServiceFee,
                ];
                $__salesOrder['safeguard_config_list'][] = $__safeguardConfigItem;
            }
        }
        unset($__salesOrder);
        // 原数组返回，里面已经加好了保障服务的数据
        return [$salesOrderList, $secondPayment];
    }

    /**
     * 获取需要采购的价格信息
     *
     * @param PurchasePayRecord[] $purchasePayRecords
     * @param $countryId
     * @return array [key=>{价格信息}]
     * @throws Exception
     */
    private function getPurchaseProductsPriceInfo($purchasePayRecords, $countryId)
    {
        $getDataList = [];
        foreach ($purchasePayRecords as $purchasePayRecord) {
            if ($purchasePayRecord->quantity <= 0) {
                continue;
            }
            $key = $this->generatePriceKey($purchasePayRecord);
            if (!isset($getDataList[$key])) {
                $getDataList[$key] = $purchasePayRecord->attributesToArray();
                $getDataList[$key]['country_id'] = $countryId;
            } else {
                $getDataList[$key]['quantity'] += $purchasePayRecord->quantity;
            }
        }
        /** @var ModelAccountSalesOrderMatchInventoryWindow $matchModel */
        $matchModel = load()->model('account/sales_order/match_inventory_window');
        $res = [];
        foreach ($getDataList as $key => $item) {
            $res[$key] = $matchModel->getLineTotal($item);
        }
        return $res;
    }

    /**
     * 获取价格的key，为了保持两边统一
     *
     * @param PurchasePayRecord $purchasePayRecord
     * @return string
     */
    private function generatePriceKey(PurchasePayRecord $purchasePayRecord)
    {
        return "{$purchasePayRecord->product_id}-{$purchasePayRecord->type_id}-{$purchasePayRecord->agreement_id}";
    }

    /**
     * 计算所有采购产品总价并且组装产品数据
     *
     * @param $salesOrders
     * @return array [total,products]
     */
    private function handlePurchaseProductsBySalesOrder($salesOrders): array
    {
        $total = BCS::create(0, $this->bcsConfig);
        $data = [];
        foreach ($salesOrders as $salesOrder) {
            foreach ($salesOrder['lines'] as $line) {
                if ($line['buy_product_info']
                    && isset($line['product_info']['product_type'])
                    && $line['product_info']['product_type'] == ProductType::NORMAL) {
                    $total->add($line['buy_product_info']['price'] * $line['buy_product_info']['quantity']);
                    $item = array_merge($line['product_info'], $line['buy_product_info']);
                    if ($item['type_id'] == ProductTransactionType::SPOT) {
                        $item['quote_amount'] = $item['price'];
                    }
                    $data[] = $item;
                }
            }
        }
        return [$total->getResult(), $data];
    }

    // 订单提交
    public function submit(ConfirmSubmitForm $form)
    {
        $data = $form->submit();
        if ($data['success']) {
            return $this->jsonSuccess($data);
        } else {
            return $this->jsonFailed($data['error'], $data);
        }
    }
}
