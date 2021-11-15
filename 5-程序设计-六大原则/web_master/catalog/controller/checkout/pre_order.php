<?php

use App\Catalog\Controllers\AuthController;
use App\Enums\Common\YesNoEnum;
use App\Enums\Customer\CustomerAccountingType;
use App\Enums\Future\FuturesVersion;
use App\Enums\Product\ProductTransactionType;
use App\Enums\Product\ProductType;
use App\Models\Customer\Customer;
use App\Models\CWF\CloudWholesaleFulfillmentFileExplain;
use App\Models\CWF\OrderCloudLogistics;
use App\Models\Futures\FuturesMarginAgreement;
use App\Repositories\CWF\CloudWholesaleFulfillmentRepository;
use App\Repositories\Futures\ContractRepository;
use App\Repositories\Margin\MarginRepository;
use App\Repositories\Order\OrderRepository;
use App\Repositories\Seller\SellerRepository;
use App\Services\Marketing\CouponService;

/**
 * @property ModelAccountCartCart $model_account_cart_cart
 * @property ModelCustomerPartnerDelicacyManagement $model_customerpartner_DelicacyManagement
 * @property ModelFuturesAgreement $model_futures_agreement
 * Class ControllerCheckoutPreOrder
 */
class ControllerCheckoutPreOrder extends AuthController
{
    protected $modelPreOrder;

    public function __construct(Registry $registry, ModelCheckoutPreOrder $modelCheckoutPreOrder)
    {
        parent::__construct($registry);
        $this->modelPreOrder = $modelCheckoutPreOrder;
    }

    public function index()
    {
        // 加载页面布局
        $data = $this->framework();
        $customerId = $this->customer->getId();
        $isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
        $countryId = $this->customer->getCountryId();
        $data['delivery_type'] = $deliveryType = $this->request->get('delivery_type');
        $data['buy_now_data'] = $this->request->attributes->get('buy_now_data', '');
        $data['cart_id_str'] = $this->request->get('cart_id_str');
        // 云送仓批量导单数据更新
        $data['is_batch_cwf'] = 0;
        $data['cwf_file_upload_id'] = request('cwf_file_upload_id');
        if (!$data['buy_now_data'] && $data['cwf_file_upload_id'] && $data['delivery_type'] == 2) {
            $data['is_batch_cwf'] = 1;
            $purchaseOrderInfo = CloudWholesaleFulfillmentFileExplain::query()->alias('fe')
                ->leftJoin('oc_order as oo', 'oo.order_id', '=','fe.order_id')
                ->where('fe.cwf_file_upload_id',$data['cwf_file_upload_id'])
                ->where('oo.customer_id',customer()->getId())
                ->select(['oo.order_status_id', 'fe.order_id'])
                ->get()
                ->first();
            if ($purchaseOrderInfo && $purchaseOrderInfo->order_id) {
                if ($purchaseOrderInfo->order_status_id != 0) {
                    //跳转回云送仓页面
                    return $this->response->redirectTo($this->url->link('account/sales_order/sales_order_management',['tab' => 2]));
                } else {
                    // 已存在 跳转支付页面
                    return $this->response->redirectTo($this->url->link('checkout/confirm/toPay', ['order_id' => $purchaseOrderInfo->order_id, 'order_source' => 'sale']));
                }
            }
            $data['buy_now_data'] = app(CloudWholesaleFulfillmentRepository::class)->getBatchCWFUploadInfo($data['cwf_file_upload_id']);
        }
        // 云送仓批量导单数据更新 end
        $originalProducts = $this->modelPreOrder->getPreOrderCache($data['cart_id_str'], $data['buy_now_data']);
        if (!$originalProducts) {
            return $this->response->redirectTo($this->url->link('checkout/cart', '', true));
        }
        $products = $this->modelPreOrder->handleProductsData($originalProducts, $customerId, $deliveryType, $isCollectionFromDomicile, $countryId);
        $data['products'] = $this->modelPreOrder->preOrderShow($products, $isCollectionFromDomicile, $countryId, $this->customer->isEurope());
        $this->load->model('account/cart/cart');
        $cartModel = $this->model_account_cart_cart;
        $data['total'] = $cartModel->orderTotalShow($products, true);
        // 活动满减金额
        $totalCodeValueMap = array_column($data['total']['totals'], 'value', 'code');
        $fullReductionCampaignAmount = abs($totalCodeValueMap['promotion_discount'] ?? 0);
        // 可用的优惠券
        $subTotal = $this->getSubtotal($data['products']);
        $data['coupon'] = $this->modelPreOrder->getPreOrderCoupons($subTotal, $data['total']['select_coupon_ids'], $fullReductionCampaignAmount);
        $data['symbolLeft'] = $this->currency->getSymbolLeft($this->session->get('currency'));
        $data['symbolRight'] = $this->currency->getSymbolRight($this->session->get('currency'));
        $data['precision'] = $this->customer->getCountryId() == JAPAN_COUNTRY_ID ? 0 : 2;
        // 是否是欧洲账号
        $data['isEuropean'] = $this->isEuropeanAccount();
        // 交易类型字典
        $data['transaction_type'] = $this->modelPreOrder::TRANSACTION_TYPES;
        // 如果存在期货产品，判断是否要作答
        if (in_array($this->modelPreOrder::TRANSACTION_FUTURE, array_column($products, 'transaction_type'))) {
            $data['futures_questionnaire'] = $this->orm->table('oc_futures_questionnaire')
                ->where('customer_id', $customerId)
                ->exists();
        } else {
            $data['futures_questionnaire'] = true;
        }
        if ($deliveryType == 2) {
            // 判断是否是批量云送仓订单
            if ($data['is_batch_cwf']) {
                // 获取信息
                $cloudLogisticInfo = OrderCloudLogistics::query()->alias('ocl')
                    ->leftJoinRelations(['fileExplains as fe'])
                    ->where('fe.cwf_file_upload_id', $data['cwf_file_upload_id'])
                    ->select(['fe.id as explain_id', 'fe.cwf_file_upload_id', 'fe.b2b_item_code', 'fe.ship_to_qty'])
                    ->selectRaw("ocl.*")
                    ->get();
                $infos = [];
                foreach ($cloudLogisticInfo as $items) {
                    $infos[$items->id]['main_info'] = $items;
                    $infos[$items->id]['line_info'][] = [
                        'explain_id' => $items->explain_id,
                        'cwf_file_upload_id' => $items->cwf_file_upload_id,
                        'b2b_item_code' => $items->b2b_item_code,
                        'ship_to_qty' => $items->ship_to_qty,
                    ];
                }
                $data['batch_cloud_logistics_infos'] = $infos;
            } else {
                if (!$this->session->get('cwf_id')) {
                    return $this->response->redirectTo($this->url->link('checkout/cart'));
                }
                // 获取运送仓的地址信息
                $data['cloud_logistics'] = $cartModel->getOrderCloudLogistics($this->session->get('cwf_id'));
            }

        }
        $futureAgreementIds = collect($data['products'])->where('transaction_type', ProductTransactionType::FUTURE)->where('product_type', ProductType::NORMAL)->pluck('agreement_id');
        $data['has_future_v3'] = FuturesMarginAgreement::query()->whereIn('id', $futureAgreementIds->toArray())->where('version', FuturesVersion::VERSION)->exists();
        // 资产风控
        $checkCanPayData = [];// 暂存请求数据
        foreach ($data['products'] as $product) {
            $checkCanPayData[$product['seller_id']][] = [
                'product_id' => $product['product_id'],
                'qty' => $product['quantity'],
                'items_cost' => $product['price_money'],
                'total' => $product['total_money'],
            ];
        }
        list($data['asset_control'], $assetControlSeller) = app(OrderRepository::class)->checkAssetControlByProducts($checkCanPayData);
        if (!$data['asset_control']) {
            $data['asset_control_screen_name_str'] = app(SellerRepository::class)->getSellerInfo($assetControlSeller)->implode('screenname', ',');
        }

        // 批量云送仓数据展示更改，整体的产品需要展示成按照云送仓订单显示
        $this->response->setOutput($this->load->view('checkout/pre_order', $data));
    }

    // 获取订单参加优惠券的总货值
    public function getSubtotal($products)
    {
        $products = collect($products)->where('product_type', 0);
        $subTotal = 0;
        foreach ($products as $item) {
            $price = $item['current_price'];
            if ($item['type_id'] == ProductTransactionType::SPOT) {
                $price = $item['spot_price'];
            }
            $subTotal += $price * $item['quantity'];
        }
        return $subTotal;
    }

    /**
     * @throws Exception
     * @deprecated 此次改由前端计算优惠券 taixing
     */
    public function changeCoupon()
    {
        $deliveryType = $this->request->attributes->get('delivery_type');
        $couponId = $this->request->attributes->get('coupon_id', 0);
        $buyNowData = $this->request->attributes->get('buy_now_data', '');
        // 云送仓批量导单数据更新
        $cwf_file_upload_id = request('cwf_file_upload_id');
        if (!$buyNowData && $cwf_file_upload_id && $deliveryType) {
            $buyNowData = app(CloudWholesaleFulfillmentRepository::class)->getBatchCWFUploadInfo($cwf_file_upload_id);
        }
        $originalProducts = $this->modelPreOrder->getPreOrderCache('', $buyNowData);
        $products = $this->modelPreOrder->handleProductsData($originalProducts, $this->customer->getId(), $deliveryType, $this->customer->isCollectionFromDomicile(), $this->customer->getCountryId());
        $this->load->model('account/cart/cart');
        $total = $this->model_account_cart_cart->orderTotalShow($products, true, ['coupon_ids' => (empty($couponId) ? [] : [$couponId])]);

        return $this->jsonSuccess($total);
    }

    /**
     * 检查预下单页面的商品
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws Exception
     */
    public function checkPreOrder()
    {
        $deliveryType = $this->request->post('delivery_type');
        $buy_now_data = $this->request->attributes->get('buy_now_data');
        $cwf_file_upload_id = request('cwf_file_upload_id');
        // 根据cwf file upload id 获取
        if($cwf_file_upload_id){
            $purchaseOrderInfo = CloudWholesaleFulfillmentFileExplain::query()->alias('fe')
                ->leftJoin('oc_order as oo', 'oo.order_id', '=','fe.order_id')
                ->where('fe.cwf_file_upload_id',$cwf_file_upload_id)
                ->where('oo.customer_id',customer()->getId())
                ->select(['oo.order_status_id', 'fe.order_id'])
                ->get()
                ->first();
            if ($purchaseOrderInfo && $purchaseOrderInfo->order_id) {
                if ($purchaseOrderInfo->order_status_id != 0) {
                    //跳转回云送仓页面
                    return $this->jsonFailed('', ['delivery_type' => $deliveryType, 'url' => $this->url->link('account/sales_order/sales_order_management', ['tab' => 2])]);
                } else {
                    // 已存在 跳转支付页面
                    return $this->jsonFailed('cwf to be paid.', ['delivery_type' => $deliveryType, 'url' => $this->url->link('checkout/confirm/toPay', ['order_id' => $purchaseOrderInfo->order_id, 'order_source' => 'sale'])]);
                }
            }
        }

        $originalProducts = $this->modelPreOrder->getPreOrderCache($this->request->post('cart_id_str'), $buy_now_data);
        $isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
        $countryId = $this->customer->getCountryId();
        $customerId = $this->customer->getId();
        $couponId = $this->request->post('coupon_id', 0);
        $products = $this->modelPreOrder->handleProductsData($originalProducts, $customerId, $deliveryType, $isCollectionFromDomicile, $countryId);
        $productIds = array_column($products, 'product_id');
        $this->load->language('checkout/cwf_info');
        $this->load->model('account/product_quotes/margin');
        $this->load->model('futures/agreement');
        $this->load->model('customerpartner/DelicacyManagement');
        $delicacy = $this->model_customerpartner_DelicacyManagement->checkIsDisplay_batch($productIds, $customerId);
        $checkCanPayData = [];// 暂存请求数据
        foreach ($products as $item) {
            // 上架且可独立售卖且Buyer可见&&seller账户未禁用
            if (!in_array($item['product_id'], $delicacy) || !isset($item['quantity']) || $item['store_status'] == 0) {
                return $this->jsonFailed(sprintf($this->language->get('check_error_available'), $item['sku']));
            }
            // 对商品锁定库存校验
            if (!$this->modelPreOrder->isEnoughProductStock($item['product_id'], $item['quantity'], $item['stock_quantity'], $item['product_type'], $item['agreement_id'], $item['transaction_type'])) {
                return $this->jsonFailed(sprintf($this->language->get('check_error_quantity'), $item['sku']), ['delivery_type' => $deliveryType]);
            }
            // 议价验证
            if ($item['transaction_type'] == $this->modelPreOrder::TRANSACTION_SPOT) {
                try {
                    $this->modelPreOrder->validateSpot($item['agreement_id'], $item['quantity']);
                } catch (Exception $e) {
                    if ($e->getCode() === 10001 && $this->customer->innerAutoBuyAttr1() && !empty($item['cart_id'])) {
                        $this->modelPreOrder->resetCartSpotQuantity($item['cart_id'], $item['agreement_id']);
                    }

                    return $this->jsonFailed($e->getMessage());
                }
            }
            // 现货尾款验证
            if ($item['transaction_type'] == $this->modelPreOrder::TRANSACTION_MARGIN
                && $item['product_type'] != $this->modelPreOrder::PRODUCT_MARGIN
                && !app(MarginRepository::class)->checkAgreementIsValid(intval($item['agreement_id']), intval($item['product_id']))) {
                return $this->jsonFailed("{$item['sku']} is not available in the desired quantity or not in stock!", ['error_type' => 'margin_invalid']);
            }

            // 判断是不是期货头款
            if ($item['product_type'] == $this->modelPreOrder::PRODUCT_FUTURE) {
                //期货二期，判断是否有足够的期货合约可用数量
                if (!$this->model_futures_agreement->isEnoughContractQty($item['agreement_id'])) {
                    return $this->jsonFailed(sprintf($this->language->get('check_contract_error_quantity'), $this->model_futures_agreement->getAgreementNoByAgreementId($item['agreement_id'])));
                }
                //期货二期，判断是否有足够的合约保证金
                $contractRes = $this->model_futures_agreement->isEnoughContractMargin($item['agreement_id']);
                if (!$contractRes['status']) {
                    return $this->jsonFailed(sprintf($this->language->get('error_futures_low_deposit'), $contractRes['agreement_no']));
                }
            }
            // 判断是不是保证金头款
            if (
                $item['product_type'] == $this->modelPreOrder::PRODUCT_MARGIN
                && !app(MarginRepository::class)->checkMarginIsFuture2Margin($item['agreement_id'])
            ) {
                //保证金头款需要验证上架数量以及在库数量
                $this->load->language('account/product_quotes/margin');
                $marginRepository = app(MarginRepository::class);
                $agreementDetail = $marginRepository->getMarginAgreementInfo($item['agreement_id']);
                $this->load->model('common/product');
                $agreementProductAvailableQty = $this->model_common_product->getProductAvailableQuantity(
                    (int)$agreementDetail['product_id']
                );
                if (
                    ($agreementDetail['num'] > $agreementProductAvailableQty) // 校验在库
                    || ($agreementDetail['num'] > $agreementDetail['available_qty']) // 校验上架
                ) {
                    return $this->jsonFailed(sprintf($this->language->get("error_under_stock"), $agreementDetail['sku']));
                }

                // 现货保证金交易，商品属于 Onsite Seller,需要校验Onsite Seller的 应收款（+抵押物，目前Onsite Seller还没有抵押物）金额是否充足
                $precision = JAPAN_COUNTRY_ID == $this->customer->getCountryId() ? 0 : 2;
                $needActiveAmount = round($agreementDetail['unit_price'] * $agreementDetail['payment_ratio'] / 100, $precision) * $agreementDetail['num'];
                $checkOnsiteSellerAmount = $this->checkOnsiteSellerActiveAmount($agreementDetail['seller_id'], $needActiveAmount);
                if (! $checkOnsiteSellerAmount) {
                    return $this->jsonFailed($this->language->get('error_onsite_seller_active_amount'), [], 40001);
                }
            }
            $checkCanPayData[$item['seller_id']][] = [
                'product_id' => $item['product_id'],
                'qty' => $item['quantity'],
                'items_cost' => $item['current_price'],
                'total' => $item['total'],
            ];
        }
        // seller 风控校验
        list($assetControl, $assetControlSeller) = app(OrderRepository::class)->checkAssetControlByProducts($checkCanPayData);
        if (!$assetControl) {
            $screenName = app(SellerRepository::class)->getSellerInfo($assetControlSeller)->implode('screenname', ',');
            return $this->jsonFailed(__('触发风控提醒', ['screenName' => $screenName], 'controller/seller_asset'));
        }
        // 如果是运送仓类型，校验体积
        // 校验云送仓的体积,不足2立方米，不允许发货
        if ($deliveryType == 2) {
            if ($cwf_file_upload_id) {
                $checkVolume = app(CloudWholesaleFulfillmentRepository::class)->checkVolumeByUploadId($cwf_file_upload_id);
            } else {
                $checkVolume = $this->checkVolume($products);
            }
            if (!$checkVolume['success']) {
                return $this->jsonFailed($checkVolume['msg'], ['delivery_type' => $deliveryType]);
            }

        }
        // 优惠券校验
        if ($couponId && !app(CouponService::class)->checkCouponCanUsed($couponId)) {
            return $this->jsonFailed('Promotions attached to this order are now expired. Please resubmit your order.');
        }

        return $this->jsonSuccess();
    }

    /**
     * 现货保证金交易，商品属于 Onsite Seller,需要校验Onsite Seller的 应收款（+抵押物，目前Onsite Seller还没有抵押物）金额是否充足
     *
     * @param int $sellerId sellerId
     * @param int|float $needAmount 需要的保证金金额
     * @return bool
     */
    private function checkOnsiteSellerActiveAmount(int $sellerId, $needAmount)
    {
        /** @var Customer $sellerInfo */
        $sellerInfo = Customer::where('customer_id', $sellerId)->where('status', YesNoEnum::YES)->first();
        if (! $sellerInfo) {
            return false;
        }

        // 该Seller是属于 Onsite Seller 时，进行保证金金额验证
        if ($sellerInfo->accounting_type == CustomerAccountingType::GIGA_ONSIDE && $sellerInfo->getIsPartnerAttribute()) {
            $sellerActiveAmount = app(ContractRepository::class)->getSellerActiveAmount($sellerId, $sellerInfo->accounting_type);
            if (bccomp($sellerActiveAmount, $needAmount) < 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * 云送仓类型，校验体积
     * @param array $products
     * @return array
     */
    public function checkVolume($products)
    {
        $volumeAll = 0;
        $productIds = array_column($products, 'product_id');
        $cwf_freight = $this->freight->getFreightAndPackageFeeByProducts($productIds);
        $json['success'] = true;
        foreach ($products as $item) {
            //尺寸运费信息是否存在
            if ($item['combo_flag']) {
                foreach ($cwf_freight[$item['product_id']] as $fre_k => $fre_v) {
                    if ($fre_v['volume_inch'] == 0 || $fre_v['freight'] == 0) {
                        $json['success'] = false;
                        $json['msg'] = sprintf($this->language->get('check_error_size_error'), $item['sku']);
                    }
                    //云送仓购物车产品体积合并
                    //102497 换成立方英尺
                    $volumeAll += $fre_v['volume_inch'] * $fre_v['qty'] * $item['quantity'];
                }
            } else {
                if (!isset($cwf_freight[$item['product_id']]) || $cwf_freight[$item['product_id']]['volume_inch'] == 0 || $cwf_freight[$item['product_id']]['freight'] == 0) {
                    $json['success'] = false;
                    $json['msg'] = sprintf($this->language->get('check_error_size_error'), $item['sku']);
                }
                //云送仓购物车产品体积合并
                //102497 换成立方英尺
                $volumeAll += $cwf_freight[$item['product_id']]['volume_inch'] * $item['quantity'];
            }
        }
        if (bccomp($volumeAll, CLOUD_LOGISTICS_VOLUME_LOWER) === -1) {
            $json['success'] = false;
            $json['msg'] = sprintf($this->language->get('volume_require_msg'));
        }
        return $json;
    }


    /**
     * 判断是不是欧洲账户
     * @return bool
     */
    public function isEuropeanAccount()
    {
        if (!empty($this->customer->getCountryId()) && $this->country->isEuropeCountry($this->customer->getCountryId())) {
            $isEuropean = true;
        } else {
            $isEuropean = false;
        }
        return $isEuropean;
    }

    public function framework()
    {
        $this->load->language('checkout/cart');
        $this->document->setTitle('My Purchase List');
        $data['breadcrumbs'] = [
            [
                'href' => $this->url->link('common/home'),
                'text' => $this->language->get('text_home')
            ],
            [
                'href' => $this->url->link('checkout/cart'),
                'text' => $this->language->get('shopping_cart')
            ],
            [
                'href' => 'javascript:void(0)',
                'text' => $this->language->get('purchase_title')
            ]
        ];
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');
        return $data;
    }

}
