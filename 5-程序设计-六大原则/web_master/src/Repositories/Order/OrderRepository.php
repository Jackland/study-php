<?php

namespace App\Repositories\Order;

use App\Components\RemoteApi\B2BManager\DTO\Freight\FreightDTO;
use App\Enums\Common\YesNoEnum;
use App\Enums\Customer\CustomerAccountingType;
use App\Enums\FeeOrder\FeeOrderOrderType;
use App\Enums\Order\OcOrderStatus;
use App\Enums\Product\ComboFlag;
use App\Enums\Product\ProductTransactionType;
use App\Enums\Product\ProductType;
use App\Enums\Spot\SpotProductQuoteStatus;
use App\Models\FeeOrder\FeeOrder;
use App\Models\Futures\FuturesMarginAgreement;
use App\Models\Link\OrderAssociatedPre;
use App\Models\Margin\MarginAgreement;
use App\Models\Order\Order;
use App\Models\Order\OrderProduct;
use App\Models\Product\Product;
use App\Models\Product\ProductQuote;
use App\Repositories\Buyer\BuyerToSellerRepository;
use App\Repositories\Margin\MarginRepository;
use App\Repositories\Product\BatchRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\SellerAsset\SellerAssetRepository;
use App\Repositories\Setup\SetupRepository;
use Exception;
use Illuminate\Database\Concerns\BuildsQueries;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use kriss\bcmath\BCS;
use ModelAccountRmaManagement;
use Illuminate\Database\Query\Expression;
use ModelAccountOrder;
use ModelBuyerBuyerCommon;
use ModelCheckoutPreOrder;
use ModelCommonProduct;
use ModelExtensionModulePrice;

class OrderRepository
{
    /**
     * 获取商品单关联的费用单
     *
     * @param int $orderId 采购订单ID
     * @return FeeOrder[]|\Framework\Model\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection|Collection
     */
    public function getFeeOrderByOrderId($orderId)
    {
        //查出这个采购单对应的所有销售单
        $orderAssociatedPres = OrderAssociatedPre::where('order_id', $orderId)->get(['sales_order_id', 'run_id']);
        if ($orderAssociatedPres->isEmpty()) {
            return collect();
        }
        $feeOrderModel = FeeOrder::query()->where('order_type', FeeOrderOrderType::SALES);
        $feeOrderModel->where(function ($query) use ($orderAssociatedPres) {
            foreach ($orderAssociatedPres as $orderAssociatedPre) {
                $query->orWhere(function ($orQuery) use ($orderAssociatedPre) {
                    $orQuery->where('order_id', $orderAssociatedPre->sales_order_id)->where('purchase_run_id', $orderAssociatedPre->run_id);
                });
            }
        });
        return $feeOrderModel->get();
    }

    /**

     *
     * @param $feeOrderId
     * @return array
     */
    /**
     * 获取费用单关联的商品单ID和状态
     * @param $feeOrderId
     *
     * @return OrderAssociatedPre[]|array|BuildsQueries[]|\Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection|Builder[]|Collection
     */
    public function getOrderByFeeOrderId($feeOrderId)
    {
        $feeOrder = FeeOrder::find($feeOrderId);
        //查出费用单关联的商品订单
        return $this->getOrderBySalesOrderId($feeOrder->order_id, -1, $feeOrder->purchase_run_id, 2);
    }

    /**
     * 根据销售订单号查询关联的采购单
     *
     * @param $salesOrderId
     * @param int $orderStatus 订单状态 -1是所有，其他状态参考 Enums/Order/OcOrderStatus
     * @param int $runId 下单页的run_id
     * @param int $associateType 关联类型 1-关联囤货库存 2-关联新采购库存
     *
     * @return OrderAssociatedPre[]|array|BuildsQueries[]|\Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection|Builder[]|Collection
     */
    public function getOrderBySalesOrderId($salesOrderId, $orderStatus = -1, $runId = 0, $associateType = 0)
    {
        return OrderAssociatedPre::leftJoin("oc_order as oo", 'tb_sys_order_associated_pre.order_id', '=', 'oo.order_id')
            ->where('sales_order_id', $salesOrderId)
            ->when($orderStatus >= 0, function ($query) use ($orderStatus) {
                $query->where('oo.order_status_id', $orderStatus);
            })
            ->when($runId, function ($query) use ($runId) {
                $query->where('tb_sys_order_associated_pre.run_id', $runId);
            })
            ->when($associateType, function ($query) use ($associateType) {
                $query->where('tb_sys_order_associated_pre.associate_type', $associateType);
            })
            ->get(['tb_sys_order_associated_pre.order_id', 'oo.order_status_id']);
    }

    /**
     * 获取销售订单预绑定的采购订单
     *
     * @param int $salesOrderId
     * @param int $runId
     *
     * @return array []
     */
    public function getOrderIdBySalesOrderPre($salesOrderId, $runId)
    {
        $orderAssociatedPres = OrderAssociatedPre::where('sales_order_id', $salesOrderId)
            ->when($runId, function ($query) use ($runId) {
                $query->where('run_id', $runId);
            })
            ->groupBy(['order_id'])
            ->get(['order_id']);
        return $orderAssociatedPres->pluck('order_id')->toArray();
    }

    /**
     * 获取预绑定的run id
     *
     * @param int $orderId 采购订单ID
     * @param int $associateType
     *
     * @return string|mixed
     */
    public function getOrderAssociatedPreRunId($orderId, $associateType = 0)
    {
        return OrderAssociatedPre::where('order_id', $orderId)
            ->when($associateType, function ($query) use ($associateType) {
                $query->where('associate_type', $associateType);
            })->value('run_id');
    }

    /**
     * 获取订单的价格信息
     * @param int|OrderProduct $orderProductId
     * @return OrderProduct
     * @throws Exception
     */
    public function getOrderProductPrice($orderProductId): ?OrderProduct
    {
        if ($orderProductId instanceof OrderProduct) {
            $orderProduct = $orderProductId;
        } else {
            $orderProduct = OrderProduct::query()
                ->where('order_product_id', $orderProductId)
                ->first();
        }
        if (!$orderProduct) {
            return null;
        }
        $price = 0;
        if ($orderProduct->type_id == ProductTransactionType::SPOT) {
            $price = ProductQuote::query()
                ->where([
                    'status' => SpotProductQuoteStatus::SOLD,
                    'order_id' => $orderProduct->order_id,
                    'product_id' => $orderProduct->product_id
                ])
                ->value('price');
        } elseif ($orderProduct->type_id == ProductTransactionType::MARGIN) { //现货
            $price = MarginAgreement::query()->where('id', $orderProduct->agreement_id)->value('price');
        } elseif ($orderProduct->type_id == ProductTransactionType::FUTURE) { //期货
            $price = FuturesMarginAgreement::query()->where('id', $orderProduct->agreement_id)->value('unit_price');
        }
        if ($price) {
            $orderProduct->service_fee_per = $price - round(app('registry')->get('country')->getDisplayPrice(customer()->getCountryId(), $price), 2);
            $orderProduct->price = $price - $orderProduct->service_fee_per;
        }
        return $orderProduct;
    }

    /**
     * 根据订单ID判断是否可以通过seller风控
     *
     * @param int $orderId
     * @return array 返回参考 checkAssetControlByProducts函数
     */
    public function checkAssetControlByOrder(int $orderId)
    {
        $orderProducts = OrderProduct::query()
            ->where('order_id', $orderId)
            ->with('customerPartnerToProduct')
            ->get()
            ->map(function (OrderProduct $item) {
                return $this->getOrderProductPrice($item);
            });
        $checkAssetControlProduct = [];
        foreach ($orderProducts as $orderProduct) {
            $checkAssetControlProduct[$orderProduct->customerPartnerToProduct->customer_id][] = [
                'product_id' => $orderProduct->product_id,
                'qty' => $orderProduct->quantity,
                'items_cost' => $orderProduct->price,
                'total' => ($orderProduct->price + $orderProduct->freight_per + $orderProduct->package_fee + $orderProduct->service_fee_per) * $orderProduct->quantity,
            ];
        }
        return $this->checkAssetControlByProducts($checkAssetControlProduct);
    }
    /**
     * 校验订单内商品是否满足资产风险控制
     *
     * @param array $productList [seller_id => [{product_id,qty:数量,total:总价,items_cost:货值}]]
     *                          seller id => 产品ID,数量,总金额
     * @return array 第一个参数 true 通过|false 拒绝 如果是false第二个参数是不满足资产风控的seller id
     */
    public function checkAssetControlByProducts(array $productList)
    {
        $errorSeller = [];
        if (empty($productList)) {
            return [true, $errorSeller];
        }
        $sellerAssetRepo = app(SellerAssetRepository::class);
        $productRepo = app(ProductRepository::class);
        $batchRepo = app(BatchRepository::class);
        $latestClosingBalance = app(SetupRepository::class)->getValueByKey('LATEST_CLOSING_BALANCE') ?? 0;
        foreach ($productList as $sellerId => $products) {
            if (!($sellerAssetRepo->checkSellerRiskCountry($sellerId))) {
                // 非外部seller不需要验证
                continue;
            }
            $approximateCost = BCS::create(0, ['scale' => 2]);//预计费用 普通采购订单产生的物流费、打包费
            $sellerTotalFreight = BCS::create(0, ['scale' => 8]);// 该运费不包含旺季附加费
            $itemsCostTotal = BCS::create(0, ['scale' => 8]);// 商品总货值
            foreach ($products as $product) {
                $itemsCostTotal->add($product['items_cost'] * $product['qty']);
                $requestFreightData = [
                    ['product_id' => $product['product_id'], 'qty' => $product['qty']]
                ];
                $estimateFreight = $productRepo->getB2BManageProductFreightByProductList($requestFreightData, $sellerId, true);
                if ($estimateFreight && !empty($estimateFreight['data'])) {
                    // 这边只是单个产品请求运费，所以不会出现包含ltl会将所有商品包装成ltl产品请求运费的情况，
                    // 所以这里不判断是否是一个大ltl产品
                    // 如果是多个产品，返回结果参见上面方法的说明
                    if (empty($estimateFreight['data'][$product['product_id']])) {
                        continue;
                    }
                    /** @var FreightDTO $freightDTO */
                    $freightDTO = $estimateFreight['data'][$product['product_id']];
                    $approximateCost->add($freightDTO->dropShip->total);
                    // 运费减去旺季附加费得出不包含旺季附加费的运费
                    if ($freightDTO->ltlFlag) {
                        $sellerTotalFreight->add($freightDTO->dropShip->ltlFreight);
                    } else {
                        $sellerTotalFreight->add($freightDTO->dropShip->expressFreight - $freightDTO->dropShip->peakSeasonFee);
                    }
                }
            }
            //纯物流计算公式：一件代发运费/（一件代发运费+货值）>0.4   则符合纯物流交易条件
            //其中一件代发运费不包含运费中的旺季附加费，上门取货纯物流校验也是一样的公式
            if (!($sellerTotalFreight
                ->div($itemsCostTotal->add($sellerTotalFreight->getResult())->getResult())
                ->isLargerThan(configDB('seller_asset_control_pure_logistics_proportion')))) {
                // 不符合纯物流订单校验，不需要校验
                continue;
            }
            // 获取总资产
            $totalAsset = $sellerAssetRepo->getTotalAssets($sellerId, false);
            // 先判断总资产是否大于0
            if ($totalAsset < 0) {
                $errorSeller[] = $sellerId;
                continue;
            }
            // 判断期末余额是否小于设定值
            $sellerBillingAssets = $sellerAssetRepo->getBillingAssets($sellerId);
            if (BCS::create($sellerBillingAssets['current_balance'], ['scale' => 2])->isLessThan($latestClosingBalance)) {
                $errorSeller[] = $sellerId;
                continue;
            }
            // 获取扣除纯物流预警运费的总资产
            $totalAsset = $sellerAssetRepo->getTotalAssets($sellerId);
            // 收入
            $income = BCS::create(0, ['scale' => 2]);
            // 在库抵押物货值
            $unitPriceTotal = BCS::create(0, ['scale' => 2]);
            // 这里拆成和上面相同的两个循环是为了避免减少不必要的查询计算
            foreach ($products as $product) {
                $unitPriceTotal->add($batchRepo->getCollateralAmountByProduct($product['product_id'], $product['qty']));
                $income->add($product['total']);
            }
            // 当前BP的抵押物获取
            $bpCollateralValue = $sellerAssetRepo->getPureLogisticsCollateralValueInBP($sellerId);
            // 总资产+收入增加-当前BP的抵押物获取-在库抵押物 < 预计费用 表示资产不足
            if (BCS::create($totalAsset, ['scale' => 2])->add($income->getResult())
                ->sub($bpCollateralValue, $unitPriceTotal->getResult())->isLessThan($approximateCost->getResult())) {
                // 资产不足返回false
                $errorSeller[] = $sellerId;
            }
        }
        if (empty($errorSeller)) {
            // 没有限制的seller就是成功
            return [true, $errorSeller];
        } else {
            return [false, $errorSeller];
        }
    }

    /**
     * 返回order product复杂交易html标签
     *
     * @param OrderProduct|int $orderProduct
     * @return string HTML代码，没有则是空字符串
     * @throws Exception
     */
    public function getTransactionTypeHtmlByOrderProduct($orderProduct): string
    {
        $transactionHtml = "";
        if (!$orderProduct) {
            return $transactionHtml;
        }
        if (!($orderProduct instanceof OrderProduct)) {
            $orderProduct = OrderProduct::find($orderProduct);
        }
        /** @var ModelAccountOrder $modelAccountOrder */
        $modelAccountOrder = load()->model('account/order');
        switch ($orderProduct->type_id) {
            case ProductTransactionType::REBATE:
                $url = url(['account/product_quotes/rebates_contract/rebatesAgreementList', 'agreement_id' => $orderProduct->agreement_id, 'act' => 'view']);
                $agreementCode = $modelAccountOrder->getRebateAgreementCode($orderProduct->agreement_id);
                $transactionHtml = '<a class="oris-mark-futures oris-tooltip" href="'.$url.'" target="_blank" data-toggle="tooltip"  data-original-title="Click to view the rebate agreement details for agreement ID ' . $agreementCode . '.">R</a>';
                break;
            case ProductTransactionType::MARGIN:
                $url = url(['account/product_quotes/margin/detail_list', 'id' => $orderProduct->agreement_id]);
                $agreementCode = MarginAgreement::where('id',$orderProduct->agreement_id)->value('agreement_id');
                $transactionHtml = '<a class="oris-mark-futures oris-tooltip" href="'.$url.'" target="_blank" data-toggle="tooltip"  data-original-title="Click to view the margin agreement details for agreement ID ' . $agreementCode . '">M</a>';
                break;
            case ProductTransactionType::FUTURE:
                $future_margin_info = $modelAccountOrder->getFutureMarginInfo($orderProduct->agreement_id);
                $agreementCode = $future_margin_info['agreement_no'];
                if (!empty($future_margin_info['contract_id'])) {
                    $url = url(['account/product_quotes/futures/buyerFuturesBidDetail', 'id' => $orderProduct->agreement_id]);
                } else {
                    $url = url(['account/product_quotes/futures/detail', 'id' => $orderProduct->agreement_id]);
                }
                $transactionHtml = '<a class="oris-mark-futures oris-tooltip" href="' . $url . '" target="_blank" data-toggle="tooltip"  data-original-title="Click to view the future goods agreement details for agreement ID ' . $agreementCode . '.">F</a>';
                break;
            case ProductTransactionType::SPOT:
                break;
        }
        return $transactionHtml;
    }

    /**
     * 创建订单之前验证
     *
     * @param $products
     * @param $customerId
     * @param $deliveryType
     * @return array
     * @throws Exception
     */
    public function validateBeforeCreateOrder($products,$customerId,$deliveryType){
        $res = [
            'success' => false,
        ];
        if (!count($products)) {
            $res['error'] = '产品不存在';
            return $res;
        }
        $setProductArray=[];
        /** @var ModelCheckoutPreOrder $modelCheckoutPreOrder */
        $modelCheckoutPreOrder = load()->model('checkout/pre_order');
        /** @var ModelBuyerBuyerCommon $modelBuyerBuyerCommon */
        $modelBuyerBuyerCommon = load()->model('buyer/buyer_common');
        /** @var ModelCommonProduct $modelCommonProduct */
        $modelCommonProduct = load()->model('common/product');
        /** @var ModelExtensionModulePrice $priceModel */
        $priceModel = load()->model('extension/module/price');
        $configStockCheckout = configDB('config_stock_checkout');
        try {
            $existsProducts = Product::query()->whereIn('product_id', array_column($products, 'product_id'))
                ->where('status','=',YesNoEnum::YES)
                ->where('is_deleted','=',YesNoEnum::NO)
                ->get(['product_id'])->pluck('product_id')->toArray();
            $buyerToSellers = app(BuyerToSellerRepository::class)->connectedSeller($customerId);
            $buyerToSellers = array_column($buyerToSellers,'seller_id');
            $marginAgreementIds = [];
            foreach ($products as $product) {
                // 验证产品是否上架
                if (!in_array($product['product_id'], $existsProducts)) {
                    $res['error'] = "{$product['sku']} does not exist in the Marketplace and cannot be purchased.";
                    return $res;
                }
                // 验证seller是否还有联系
                if (!in_array($product['seller_id'], $buyerToSellers)) {
                    $res['error'] = "You do not have permission to purchase {$product['sku']}, please contact Seller.";
                    return $res;
                }
                // 验证产品的合法性
                if (!$configStockCheckout && !$modelCheckoutPreOrder->validateProduct($product, $customerId)) {
                    $res['error'] = sprintf('%s is not available in the desired quantity or not in stock!', $product['sku']);
                    return $res;
                }
                // 验证产品的上架库存
                if (!$configStockCheckout && !$modelCheckoutPreOrder->isEnoughProductStock($product['product_id'], $product['quantity'], $product['stock_quantity'], $product['product_type'], $product['agreement_id'], $product['transaction_type'])) {
                    $res['error'] = 'The available inventory is insufficient for your purchase. Please select a lower quantity.';
                    return $res;
                }
                // 验证产品最低购买数量
                if ($product['minimum'] > $product['quantity']) {
                    $res['error'] = 'Requested product quantity exceeds amount in-stock. Please enter a lower quantity.';
                    return $res;
                }
                // 对常规产品和补差价产品做在库验证
                if (in_array($product['product_type'], [ProductType::NORMAL, ProductType::COMPENSATION_FREIGHT])) {
                    if (in_array($product['transaction_type'],
                        [ProductTransactionType::NORMAL, ProductTransactionType::REBATE, ProductTransactionType::SPOT])) {
                        //非保证金产品，保证金产品库存是在锁定的部分
                        if ($product['combo_flag'] == ComboFlag::YES) {
                            $comboInfo = $modelBuyerBuyerCommon->getComboInfoByProductId($product['product_id'], $product['quantity']);
                            $setProductArray = array_merge($setProductArray, $comboInfo);
                        } else {
                            $productInfo = array();
                            $productInfo[] = array(
                                'set_product_id' => $product['product_id'],
                                'qty'            => $product['quantity']
                            );
                            $setProductArray = array_merge($setProductArray, $productInfo);
                        }
                    }
                }
                // 现货保证金头款支付 && 商品属于Onsite Seller 需要校验 Onsite Seller 应收款是否足够
                if ($product['product_type'] == ProductType::MARGIN_DEPOSIT && $product['accounting_type'] == CustomerAccountingType::GIGA_ONSIDE) {
                    $marginAgreementIds[] = $product['agreement_id'];
                }
                // 校验产品库存和协议库存
                $transactionQtyResults = $priceModel->getProductPriceInfo($product['product_id'], $customerId);
                $quantity = 0;
                switch ($product['transaction_type']) {
                    case ProductTransactionType::NORMAL :
                    case ProductTransactionType::REBATE :
                        $quantity = $transactionQtyResults['base_info']['quantity'];
                        break;
                    case ProductTransactionType::MARGIN:
                    case ProductTransactionType::SPOT:
                        $transactionQty = collect($transactionQtyResults['transaction_type'])
                            ->where('type', $product['transaction_type'])
                            ->where('agreement_code', $product['agreement_code'])->first();
                        if (!$transactionQty) {
                            $res['error'] = sprintf('%s is not available in the desired quantity or not in stock!', $product['sku']);
                            return $res;
                        }
                        if ($product['transaction_type'] == ProductTransactionType::SPOT) {
                            // 议价还要校验数量是否完全匹配
                            if ($transactionQty['qty'] != $product['quantity']) {
                                $res['error'] = "The total purchase quantity of {$product['sku']} does not meet the quantity specified as {$transactionQty['qty']} in the Spot Price Agreement. Please select again.";
                                return $res;
                            }
                        }
                        $quantity = $transactionQty['left_qty'];
                        break;
                }
                if ($product['quantity'] > $quantity) {
                    $res['error'] = sprintf('%s is not available in the desired quantity or not in stock!', $product['sku']);
                    return $res;
                }
            }
            // 对常规产品和补差价产品做在库验证
            $resolve_order_qty = [];
            foreach ($setProductArray as $setProduct) {
                $resolve_order_qty[] = ['product_id' => $setProduct['set_product_id'], 'quantity' => $setProduct['qty']];
            }
            // 对常规产品和补差价产品做在库验证
            if (!$modelCommonProduct->checkProductQuantityValid($resolve_order_qty)) {
                $res['error'] = 'Product not available in the desired quantity or not in stock! Please contact with our customer service to argue.';
                return $res;
            }
            // 如果是云送仓的订单校验，购物车与填写的云送仓文件是否匹配
            if ($deliveryType == 2) {
                $cloudFlag = $modelCheckoutPreOrder->checkCloudLogisticsOrder($products);
                if (!$cloudFlag) {
                    $res['error'] = 'Items in your cart has been changed, please refresh the page and submit again.';
                    return $res;
                }
            }
            // 存在Onsite Seller的现货头款保证金商品，检测Onsite Seller的账户余额是否充足(需要排序由期货转现货头款)
            if ($marginAgreementIds) {
                $notBuyList = app(MarginRepository::class)->checkOnsiteSellerAmountByAgreementIds($marginAgreementIds, customer()->getCountryId());
                if ($notBuyList) {
                    $res['error'] = sprintf("There are risks in this seller's account, and the Margin Agreement (ID: %s) is unable to be purchased", implode(',', $notBuyList));
                    return $res;
                }
            }
        } catch (Exception $e) {
            $res['error'] = $e->getMessage();
            return $res;
        }
        $res['success'] = true;
        return $res;
    }

    /**
     * 议价协议购买的数量
     * @param array $ids oc_product_quote主键id
     * @return array key：oc_product_quote主键id  value:购买数量
     */
    public function getQuotePurchasedQty(array $ids):array
    {
        return ProductQuote::query()->alias('qp')
            ->leftJoin('oc_order_quote as oq', 'oq.quote_id', '=', 'qp.id')
            ->leftJoin('oc_order as o', 'o.order_id', '=', 'oq.order_id')
            ->leftJoin('oc_order_product as op', function (JoinClause $q) {
                $q->on('op.order_id', '=', 'o.order_id')
                    ->on('op.product_id', '=', 'qp.product_id');
            })
            ->where('o.order_status_id', '=', OcOrderStatus::COMPLETED)
            ->whereIn('qp.id', array_unique($ids))
            ->select(['qp.id', new Expression('sum(op.quantity) as purchased_qty')])
            ->groupBy('qp.id')
            ->pluck('purchased_qty', 'id')
            ->toArray();
    }

    /**
     * 处理商品是否是纯物流
     * @param array $products [{product_id,current_price,quantity,transaction_type,agreement_id},{}]
     * @param int $customerId
     * @return array
*/
    public function handleOrderProductPureLogistics(array $products,int $customerId): array
    {
        $requestProducts = [];
        foreach ($products as $product) {
            if (empty($product['product_id']) && empty($product['quantity'])) {
                continue;
            }
            $requestProducts[] = [
                'product_id' => $product['product_id'],
                'qty' => $product['quantity']
            ];
        }
        if (empty($products)) {
            return [];
        }
        // 请求运费数据
        $productFreightList = app(ProductRepository::class)
            ->getB2BManageProductFreightByProductList($requestProducts, $customerId, true);
        // 获取运费数据
        if(empty($productFreightList['data'])){
            return [];
        }
        $res = [];
        $pureLogisticsProportion = configDB('seller_asset_control_pure_logistics_proportion');
        foreach ($products as $product) {
            if (empty($productFreightList['data'][$product['product_id']])) {
                continue;
            }
            /** @var FreightDTO $freightDTO */
            $freightDTO = $productFreightList['data'][$product['product_id']];
            // 商品单价
            $unitPrice = $product['current_price'];
            if ($product['transaction_type'] == ProductTransactionType::MARGIN) {
                // 现货
                $margin = MarginAgreement::query()
                    ->findOrFail($product['agreement_id']);
                if ($margin->process->advanceOrder) {
                    // 尾款用头款的
                    /** @var OrderProduct $advanceOrderProduct */
                    $advanceOrderProduct = $margin->process->advanceOrder
                        ->orderProducts()
                        ->where('product_id', '=', $margin->process->advance_product_id)
                        ->first();
                    if($advanceOrderProduct){
                        $res[$product['product_id']] = $advanceOrderProduct->is_pure_logistics;
                        continue;
                    }
                }
                $unitPrice = $margin->price;
            } elseif ($product['transaction_type'] == ProductTransactionType::FUTURE) {
                // 期货
                $futures = FuturesMarginAgreement::query()
                    ->findOrFail($product['agreement_id']);
                if ($futures->process->advanceOrder) {
                    /** @var OrderProduct $advanceOrderProduct */
                    $advanceOrderProduct = $futures->process->advanceOrder
                        ->orderProducts()
                        ->where('product_id', '=', $futures->process->advance_product_id)
                        ->first();
                    if($advanceOrderProduct){
                        $res[$product['product_id']] = $advanceOrderProduct->is_pure_logistics;
                        continue;
                    }
                }
                $unitPrice = $futures->unit_price;
            }
            $itemsCost = $unitPrice * $product['quantity'];
            $_dropShip = $freightDTO->dropShip;
            // 运费减去旺季附加费得出不包含旺季附加费的运费
            if ($freightDTO->ltlFlag) {
                $productFreight = $_dropShip->ltlFreight;
            } else {
                $productFreight = $_dropShip->expressFreight - $_dropShip->peakSeasonFee - $_dropShip->dangerFee;
            }
            $res[$product['product_id']] = $productFreight / ($itemsCost + $productFreight) > $pureLogisticsProportion;
        }
        return $res;
    }
}
