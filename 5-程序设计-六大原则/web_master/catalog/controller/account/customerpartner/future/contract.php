<?php

use App\Catalog\Controllers\AuthController;
use App\Enums\Future\FutureMarginContractDeliveryType;
use App\Enums\Future\FutureMarginContractLogType;
use App\Enums\Future\FutureMarginContractStatus;
use App\Enums\Future\FuturesMarginPayRecordType;
use App\Models\Customer\Customer;
use App\Repositories\Futures\ContractRepository;
use App\Repositories\Seller\SellerRepository;
use App\Enums\Warehouse\ReceiptOrderStatus;
use App\Widgets\VATToolTipWidget;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * 期货合约
 * Class ControllerAccountCustomerpartnerFutureContract
 */
class ControllerAccountCustomerpartnerFutureContract extends AuthController
{
    /**
     * @var array
     */
    protected $data = [];

    /**
     * @var ModelFuturesContract
     */
    protected $modelFuturesContract;

    /**
     * @var ModelFuturesAgreement
     */
    protected $modelFuturesAgreement;

    /**
     * @var mixed
     */
    protected $sellerId;

    /**
     * @var mixed
     */
    protected $currencyCode;

    /**
     * 美国入库单预计到港时间（ETA）与预计上架时间（Estimated receiving time）差14天
     * 英国入库单预计到港时间（ETA）与预计上架时间（Estimated receiving time）差14天
     * 德国入库单预计到港时间（ETA）与预计上架时间（Estimated receiving time）差14天
     * 日本入库单预计到港时间（ETA）与预计上架时间（Estimated receiving time）差21天
     * @var int[]
     */
    private $currencyEstimateReceivingDaysMap = [
        'GBP' => 14,
        'USD' => 14,
        'EUR' => 14,
        'JPY' => 21,
    ];

    public function __construct(Registry $registry, ModelFuturesContract $modelFuturesContract, ModelFuturesAgreement $modelFuturesAgreement)
    {
        parent::__construct($registry);

        $this->modelFuturesContract = $modelFuturesContract;
        $this->modelFuturesAgreement = $modelFuturesAgreement;
        $this->sellerId = $this->customer->getId();
        $this->currencyCode = $this->session->get('currency');

        $this->setLanguages('futures/contract');
    }

    /**
     * @return int
     */
    private function currencyEstimateReceivingDays()
    {
        return $this->currencyEstimateReceivingDaysMap[$this->currencyCode];
    }

    /**
     * 新建期货协议
     * @param ModelCatalogInformation $modelCatalogInformation
     * @return string
     * @throws Exception
     */
    public function add(ModelCatalogInformation $modelCatalogInformation)
    {
        $this->setDocumentInfo($this->language->get('text_list_create_contract'));

        $this->data['is_japan'] = $this->customer->isJapan();
        $this->data['account_type'] = $this->customer->getAccountType();
        $this->data['contract_no_pre_date'] = date('Ymd');
        $this->data['deposit_percentages'] = join(',', $this->depositPercentages($this->data['is_japan']));
        $this->data['payment_ratio'] = $this->data['is_japan'] ? floor($this->config->get('default_future_margin')) : sprintf("%.2f", round($this->config->get('default_future_margin'), 2));
        $this->data['exist_futures_questionnaire'] = $this->modelFuturesContract->existFuturesQuestionnaire($this->sellerId);
        $this->data['currency_symbol_left'] = $this->currency->getSymbolLeft($this->currencyCode);
        $this->data['currency_symbol_right'] = $this->currency->getSymbolRight($this->currencyCode);
        $this->data['currency_code'] = $this->currencyCode;
        $this->data['max_payment_ratio'] = $this->data['is_japan'] ? floor($this->config->get('max_future_margin')) : sprintf("%.2f", round($this->config->get('max_future_margin'), 2));
        $this->data['min_payment_ratio'] = $this->data['is_japan'] ? floor($this->config->get('min_future_margin')) : sprintf("%.2f", round($this->config->get('min_future_margin'), 2));
        $this->data['estimate_receiving_days'] = $this->currencyEstimateReceivingDays();

        $amount = $this->sellerAmount($this->data['account_type']);
        $this->data['amount'] = $this->data['is_japan'] ? round($amount, 0) : sprintf("%.2f",  round($amount, 2));

        $this->initPage();
        $this->data['breadcrumbs'][] = [
            'text' => $this->language->get('text_list_create_contract'),
            'href' => 'javascript:void(0);',
            'separator' => $this->language->get('text_separator'),
        ];
        if (!$this->data['exist_futures_questionnaire']) {
            $information = $modelCatalogInformation->getInformation($this->config->get('futures_information_id_seller'));
            $information['description'] =  html_entity_decode($information['description'], ENT_QUOTES, 'UTF-8');
            $this->data['information'] = $information;
        }
        // 是否显示云送仓提醒
        $this->data['is_show_cwf_notice'] = app(SellerRepository::class)->isShowCwfNotice();

        return $this->response->setOutput($this->render('account/customerpartner/futures/add_contract', $this->data));
    }

    // 补充政策信息
    public function policyFilePrompt()
    {
        return $this->jsonSuccess($this->config->get('supplement_policy_file_prompt'));
    }

    /**
     * @return JsonResponse
     */
    public function getLatestSettingValue()
    {
        $data = [
            'payment_ratio' => $this->customer->isJapan() ? floor($this->config->get('default_future_margin')) : sprintf("%.2f", round($this->config->get('default_future_margin'), 2)),
            'max_payment_ratio' => $this->customer->isJapan() ? floor($this->config->get('max_future_margin')) : sprintf("%.2f", round($this->config->get('max_future_margin'), 2)),
            'min_payment_ratio' => $this->customer->isJapan() ? floor($this->config->get('min_future_margin')) : sprintf("%.2f", round($this->config->get('min_future_margin'), 2)),
            'deposit_percentages' => join(',', $this->depositPercentages($this->customer->isJapan())),
        ];

        return $this->response->json(['code' => 1, 'msg' => 'success', 'data' => $data]);
    }

    /**
     * 获取存储百分比
     * @param false $isJapan
     * @return array
     */
    private function depositPercentages($isJapan = false)
    {
        $depositPercentagesStr = $this->config->get('future_margin_deposit_percentages') ?: '';
        $depositPercentages = explode(',', $depositPercentagesStr);
        sort($depositPercentages);
        return array_unique(array_map(function ($item) use ($isJapan) {
            return $isJapan ? floor($item) : sprintf("%.2f", round($item, 2));
        }, $depositPercentages));
    }

    /**
     * seller的可用金额 区分外部和内部 外部使用应收款 内部授信额度
     * @param int $accountType
     * @return float|int|mixed
     */
    private function sellerAmount(int $accountType)
    {
        $amount = app(ContractRepository::class)->getSellerActiveAmount((int)$this->sellerId, $accountType);

        return $amount;
    }

    /**
     * seller的产品
     * @param ModelFuturesProduct $modelFuturesProduct
     * @return JsonResponse
     * @throws Exception
     */
    public function sellerProducts(ModelFuturesProduct $modelFuturesProduct)
    {
        list($codeOrMpnFilter,) = explode('/', trim($this->request->query->get('code_mpn', '')));
        $products = $modelFuturesProduct->validProductsByCustomerIdAndCodeMpn($this->sellerId, $codeOrMpnFilter, 3);

        return $this->response->json(['code' => 1, 'msg' => 'success', 'data' => ['products' => $products]]);
    }

    /**
     * 获取产品详情
     * @param ModelFuturesProduct $modelFuturesProduct
     * @param ModelAccountCustomerpartnerRebates $modelAccountCustomerpartnerRebates
     * @param ModelAccountCustomerpartnerMargin $modelAccountCustomerpartnerMargin
     * @param ModelAccountwkquotesadmin $model_account_wk_quotes_admin
     * @param ModelAccountCustomerpartnerFutures $modelAccountCustomerpartnerFutures
     * @param ModelToolImage $model_tool_image
     * @param ModelCatalogProduct $modelCatalogProduct
     * @param ModelCommonProduct $modelCommonProduct
     * @return string
     * @throws Exception
     */
    public function productDetail(
        ModelFuturesProduct $modelFuturesProduct,
        ModelAccountCustomerpartnerRebates $modelAccountCustomerpartnerRebates,
        ModelAccountCustomerpartnerMargin $modelAccountCustomerpartnerMargin,
        ModelAccountwkquotesadmin $model_account_wk_quotes_admin,
        ModelAccountCustomerpartnerFutures $modelAccountCustomerpartnerFutures,
        ModelToolImage $model_tool_image,
        ModelCatalogProduct $modelCatalogProduct,
        ModelCommonProduct $modelCommonProduct
    )
    {
        $productId = $this->request->query->get('product_id', '');
        if (empty($productId)) {
            return $this->render('account/customerpartner/futures/common/product_detail');
        }
        $product = $modelFuturesProduct->productById($this->sellerId, $productId);
        if (empty($product)) {
            return $this->render('account/customerpartner/futures/common/product_detail');
        }

        //货币小数位数
        $precision = $this->currency->getDecimalPlace($this->currencyCode);

        $time = date('Y-m-d H:i:s', strtotime('-180 days'));
        $openPrices = $modelFuturesProduct->historyOpenPricesByProductId($productId, $time);

        $openPriceDates = [];
        $openPriceDetail = [];
        $lastPrice = 0;

        $isUSA = $this->customer->isUSA();
        foreach ($openPrices as $openPrice) {
            if ($openPrice['min_price'] == $lastPrice) {
                continue;
            }

            if (!$isUSA) {
                $orderCreatedAt = $modelFuturesProduct->orderCreatedAtByProductIdDatePrice($productId, $openPrice['format_date'], $openPrice['min_price']);
                $openPriceTime = strtotime(changeOutPutByZone($orderCreatedAt, $this->session));
            } else {
                $openPriceTime = strtotime($openPrice['format_date']);
            }

            $openPriceDates[] = date('m.d', $openPriceTime);
            $openPriceDetail[] = round($openPrice['min_price'], $precision);
            $lastPrice = $openPrice['min_price'];
        }

        $allPrices = [];
        //open
        $currentPrice = $this->currency->format(round($product['price'], $precision), $this->currencyCode);
        $allPrices[] = $product['price'];

        //rebate
        $rebates = $modelAccountCustomerpartnerRebates->get_rebates_template_display_batch($productId);
        $rebatePrices = [];
        foreach ($rebates as $rebate) {
            $price = [];
            foreach ($rebate['child'] as $rebateChild) {
                if ($rebateChild['product_id'] == $productId) {
                    $price[] = $rebateChild['price'] - $rebateChild['rebate_amount'];
                }
            }
            $minPrice = min($price) < 0 ? 0 : min($price);
            $maxPrice = max($price) < 0 ? 0 : max($price);
            $allPrices[] = $minPrice;
            $allPrices[] = $maxPrice;
            if ($minPrice == $maxPrice) {
                $rebate_price = $this->currency->format(round($minPrice, $precision), $this->currencyCode);
            } else {
                $rebate_price = $this->currency->format(round($minPrice, $precision), $this->currencyCode) . ' - ' . round($maxPrice, $precision);
            }
            $rebatePrices[] = [
                'price' => $rebate_price,
                'msg' => $rebate['qty'] . ' PCS in ' . $rebate['day'] . ' Days',
            ];
        }

        //margin
        $margins = $modelAccountCustomerpartnerMargin->getMarginTemplateForProduct($productId);
        $marginPrices = [];
        foreach ($margins as $margin) {
            $allPrices[] = $margin['price'];
            $marginPrices[] = [
                'price' => $this->currency->format(round($margin['price'], $precision), $this->currencyCode),
                'msg' => ($margin['min_num'] == $margin['max_num'] ? $margin['min_num'] : $margin['min_num'] . ' - ' . $margin['max_num']) . ' PCS'
            ];
        }

        //spot
        $spotPrices = [];
        if ($this->config->get('module_marketplace_status') && $this->config->get('total_wk_pro_quote_status')) {
            $quotes = $model_account_wk_quotes_admin->getQuoteDetailsByProductId($productId);
            foreach ($quotes as $quote) {
                $allPrices[] = $quote['home_pick_up_price'];
                $spotPrices[] = [
                    'price' => $this->currency->format(round($quote['home_pick_up_price'], $precision), $this->currencyCode),
                    'msg' => ($quote['max_quantity'] == $quote['min_quantity'] ? $quote['min_quantity'] : $quote['min_quantity'] . ' - ' . $quote['max_quantity']) . ' PCS'
                ];
            }
        }

        //有效提单
        $receiptOrders = $modelAccountCustomerpartnerFutures->getReceiptOrderByProductId($productId, [ReceiptOrderStatus::TO_BE_RECEIVED]);
        usort($receiptOrders, function ($a, $b) {
            $a_time = abs(strtotime($a['expected_date']) - time());
            $b_time = abs(strtotime($b['expected_date']) - time());
            if ($a_time == $b_time) return 0;
            return $a_time < $b_time ? -1 : 1;
        });

        $currencyEstimateReceivingDays = $this->currencyEstimateReceivingDays();
        foreach ($receiptOrders as &$receiptOrder) {
            $expectedDate = date('Y-m-d 12:00:00', strtotime(changeOutPutByZone(substr($receiptOrder['expected_date'], 0, 10), $this->session, 'Y-m-d H:i:s')));
            $receiptOrder['expected_date'] = date('Y-m-d', strtotime($expectedDate));
            $receiptOrder['arrived_date'] = date('Y-m-d', strtotime($expectedDate) + $currencyEstimateReceivingDays  * 86400);
        }

        $alarmPrice = $modelCommonProduct->getAlarmPrice($productId);

        $openPriceDetail = empty($openPriceDetail) ? [0] : $openPriceDetail;
        $data = [
            'isDeCountry' => $this->customer->getCountryId() == DE_COUNTRY_ID ? true : false, #31737 是否为DE
            'product_id' => $productId,
            'name' => $product['name'],
            'image' => $model_tool_image->resize($product['image'], 45, 45),
            'item_code' => $product['sku'],
            'mpn' => $product['mpn'],
            'tags' => $modelCatalogProduct->getProductTagHtmlForThumb($productId),
            'product_link' => $this->url->to(['product/product', 'product_id' => $productId]),
            'open_price' => max($openPriceDetail) != min($openPriceDetail) ? $this->currency->formatCurrencyPrice(min($openPriceDetail), $this->currencyCode) . ' - ' . $this->currency->formatCurrencyPrice(max($openPriceDetail), $this->currencyCode) : $this->currency->formatCurrencyPrice(min($openPriceDetail), $this->currencyCode),
            'open_price_detail' => [
                'dates' => json_encode($openPriceDates),
                'prices' => json_encode($openPriceDetail),
                'year' => date('Y', strtotime('-180 days')) < date('Y') ? date('Y') - 1 . ' - ' . date('Y') : date('Y'),
            ],
            'reference_price' => max($allPrices) != min($allPrices) ? $this->currency->formatCurrencyPrice(min($allPrices), $this->currencyCode) . ' - ' . $this->currency->formatCurrencyPrice(max($allPrices), $this->currencyCode) : $this->currency->formatCurrencyPrice(min($allPrices), $this->currencyCode),
            'reference_price_detail' => [
                'current_price' => $currentPrice,
                'rebate_price' => $rebatePrices,
                'margin_price' => $marginPrices,
                'spot_price' => $spotPrices,
            ],
            'receipt_orders' => $receiptOrders,
            'alarm_price' => $alarmPrice
        ];

        return $this->render('account/customerpartner/futures/common/product_detail', $data);
    }

    /**
     * 验证交付日期
     * @return JsonResponse
     * @throws Exception
     */
    public function verifyDeliveryDate()
    {
        $productId = $this->request->query->get('product_id', '');
        $deliveryDate = $this->request->query->get('delivery_date', '');
        if (empty($productId) || empty($deliveryDate)) {
            return $this->response->json(['code' => 0, 'msg' => 'error', 'data' => ['error_status' => 4]]);
        }

        if (substr(changeOutPutByZone(date('Y-m-d H:i:s'), $this->session, 'Y-m-d'), 0, 10) > $deliveryDate) {
            return $this->response->json(['code' => 0, 'msg' => 'error', 'data' => ['error_status' => 1]]);
        }

        $longestDeliveryDays = $this->config->get('max_delivery_day') ?? 90;
        if (substr(changeOutPutByZone(date('Y-m-d H:i:s', strtotime("+{$longestDeliveryDays} days")), $this->session, 'Y-m-d'), 0, 10) < $deliveryDate) {
            return $this->response->json(['code' => 0, 'msg' => 'error', 'data' => ['error_status' => 2, 'longest_delivery_days' => $longestDeliveryDays]]);
        }

        $contract = $this->modelFuturesContract->contractBySellerIdAndProductIdAndDeliveryDate($this->sellerId, $productId, $deliveryDate);
        if (!empty($contract)) {
            return $this->response->json(['code' => 0, 'msg' => 'error', 'data' => ['error_status' => 3, 'contract_id' => $contract['id']]]);
        }
        $minDeliveryDay = $this->config->get('supplement_policy_file_min_delivery_day');
        if ($this->config->get('is_need_policy_file')) {
            if (substr(changeOutPutByZone(date('Y-m-d H:i:s', strtotime("+{$minDeliveryDay} days")), $this->session, 'Y-m-d'), 0, 10) < $deliveryDate) {
                return $this->response->json(['code' => 0, 'msg' => 'error', 'data' => ['error_status' => 4, 'min_delivery_day' => $minDeliveryDay]]);
            }
        }

        return $this->response->json(['code' => 1, 'msg' => 'success', 'data' => []]);
    }

    /**
     * 添加合约
     * @return JsonResponse
     */
    public function insert()
    {
        if ($this->request->serverBag->get('REQUEST_METHOD') != 'POST') {
            return $this->response->json(['code' => 0, 'msg' => 'error', 'data' => ['error_status' => 0]]);
        }

        $defaultFutureMargin = $this->customer->isJapan() ? floor($this->config->get('default_future_margin')) : round($this->config->get('default_future_margin'), 2);
        $depositPercentages = $this->depositPercentages($this->customer->isJapan());

        $productId = $this->request->input->get('product_id', '');
        $contractNo = $this->request->input->get('contract_no', date('Ymd') . mt_rand(100000, 999999));
        $paymentRatio = $this->request->input->get('payment_ratio', $defaultFutureMargin);
        $isBid = $this->request->input->get('is_bid', 0);
        $deliveryDate = $this->request->input->get('delivery_date', '');
        $num = $this->request->input->get('num', 9999);
        $minNum = $this->request->input->get('min_num', 1);
        $deliveryType = $this->request->input->get('delivery_type', 0);
        $marginUnitPrice = $this->request->input->get('margin_unit_price', '');
        $lastUnitPrice = $this->request->input->get('last_unit_price', '');
        $status = in_array($this->customer->isJapan() ? floor($paymentRatio) : sprintf("%.2f", round($paymentRatio, 2)), $depositPercentages) ? 1 : 2;

        if (empty($productId) || empty($paymentRatio) || empty($deliveryDate) || !in_array($deliveryType, [1, 2, 3]) || (empty($lastUnitPrice) && empty($marginUnitPrice)) || $num > 9999 || $num <= 0 || $minNum > $num || $minNum <= 0) {
            return $this->response->json(['code' => 0, 'msg' => 'error', 'data' => ['error_status' => 6]]);
        }

        if ($this->modelFuturesContract->existContractByContractNo($contractNo)) {
            return $this->response->json(['code' => 0, 'msg' => 'error', 'data' => ['error_status' => 7]]);
        }

        if (substr(changeOutPutByZone(date('Y-m-d H:i:s'), $this->session, 'Y-m-d'), 0, 10) > $deliveryDate) {
            return $this->response->json(['code' => 0, 'msg' => 'error', 'data' => ['error_status' => 1]]);
        }

        $longestDeliveryDays = $this->config->get('max_delivery_day') ?? 90;
        if (substr(changeOutPutByZone(date('Y-m-d H:i:s', strtotime("+{$longestDeliveryDays} days")), $this->session, 'Y-m-d'), 0, 10) < $deliveryDate) {
            return $this->response->json(['code' => 0, 'msg' => 'error', 'data' => ['error_status' => 2, 'longest_delivery_days' => $longestDeliveryDays]]);
        }

        $contract = $this->modelFuturesContract->contractBySellerIdAndProductIdAndDeliveryDate($this->sellerId, $productId, $deliveryDate);
        if (!empty($contract)) {
            return $this->response->json(['code' => 0, 'msg' => 'error', 'data' => ['error_status' => 3, 'contract_id' => $contract['id']]]);
        }

        $expandAmount = round(max([$marginUnitPrice, $lastUnitPrice]) * $paymentRatio * 0.01, $this->customer->isJapan() ? 0 : 2) * $num;
        $amount = $this->sellerAmount($this->customer->getAccountType());
        if ($amount < $expandAmount) {
            return $this->response->json(['code' => 0, 'msg' => 'error', 'data' => ['error_status' => 4]]);
        }

        $res = $this->modelFuturesContract->insertContract($this->sellerId, $productId, [
            'contract_no' => $contractNo,
            'payment_ratio' => $paymentRatio,
            'delivery_date' => $deliveryDate,
            'is_bid' => $isBid,
            'num' => $num,
            'min_num' => $minNum,
            'delivery_type' => $deliveryType,
            'margin_unit_price' => $marginUnitPrice,
            'last_unit_price' => $lastUnitPrice,
            'status' => $status,
            'available_balance' => $expandAmount,
            'pay_type' => $this->customer->getAccountType() == 1 ? 1 : 3,
            'customer_name' => $this->customer->getFirstName() . $this->customer->getLastName(),
            'old_payment_ratio' => $defaultFutureMargin,
            'collateral_amount' => app(ContractRepository::class)->getSellerAvailableCollateralAmount(intval($this->sellerId)), // 抵押物金额
        ]);

        if (!$res) {
            return $this->response->json(['code' => 0, 'msg' => 'error', 'data' => ['error_status' => 5]]);
        }

        $amount = $this->sellerAmount($this->customer->getAccountType());
        $amount = $this->customer->isJapan() ? round($amount, 0) : sprintf("%.2f",  round($amount, 2));
        return $this->response->json(['code' => 1, 'msg' => 'success', 'data' => ['amount' => $amount]]);
    }

    /**
     * 期货合约列表
     * @param ModelToolImage $modelToolImage
     * @param ModelCatalogProduct $modelCatalogProduct
     * @return string
     * @throws Exception
     */
    public function list(ModelToolImage $modelToolImage, ModelCatalogProduct $modelCatalogProduct)
    {
        $this->setDocumentInfo($this->language->get('text_list_title'));
        $this->initPage();

        $precision = $this->currency->getDecimalPlace($this->currencyCode);

        $queries = $this->request->query->all();
        $queries['page'] = $queries['page'] ?? 1;
        $queries['page_limit'] = $queries['page_limit'] ?? 15;
        $queries['sort'] = $queries['sort'] ?? 'desc';
        $queries['sort_column'] = $queries['sort_column'] ?? 'id';
        $queries['status'] = $queries['status'] ?? FutureMarginContractStatus::getValues();

        $contracts = $this->modelFuturesContract->sellerContracts($this->sellerId, $queries);
        $contractIds = array_column($contracts, 'id');
        $contractIdNumMap = empty($contractIds) ? [] : $this->modelFuturesAgreement->agreementNumByContractIdsAndStatus($contractIds, [1, 2, 3, 7]);

        $disableContractIds = array_keys(array_column($contracts, 'status', 'id'), 2);
        $lastMarginApplies = empty($disableContractIds) ? [] : $this->modelFuturesContract->lastMarginAppliesByContractIds($disableContractIds);
        $lastMarginApplyContractIdStatusMap = array_column($lastMarginApplies, 'status', 'contract_id');


        foreach ($contracts as &$contract) {
            $contract['image'] = $modelToolImage->resize($contract['image'], 45, 45);
            $contract['agreement_num'] = $contract['purchased_num'];
            $contract['delivery_date'] = date('Y-m-d', strtotime($contract['delivery_date']));
            $contract['margin_unit_price'] = $this->currency->formatCurrencyPrice(round($contract['margin_unit_price'], $precision), $this->currencyCode);
            $contract['last_unit_price'] = $this->currency->formatCurrencyPrice(round($contract['last_unit_price'], $precision), $this->currencyCode);
            if ($contract['delivery_type'] == 1) {
                $price = $contract['last_unit_price'];
            } elseif ($contract['delivery_type'] == 2) {
                $price = $contract['margin_unit_price'];
            } else {
                if ($contract['last_unit_price'] > $contract['margin_unit_price']) {
                    $price = $contract['margin_unit_price'] . ' - ' . $contract['last_unit_price'];
                } elseif ($contract['last_unit_price'] < $contract['margin_unit_price']) {
                    $price = $contract['last_unit_price'] . ' - ' . $contract['margin_unit_price'];
                } else {
                    $price = $contract['last_unit_price'];
                }
            }
            $contract['price'] = $price;
            $contract['status_name'] = FutureMarginContractStatus::getDescription($contract['status']);
            $contract['modified_time'] = $contract['update_time'];

            //操作
            $existAgreement = isset($contractIdNumMap[$contract['id']]);
            $isEdit = false;
            $isDelete = false;
            $isTerminate = false;
            switch ($contract['status']) {
                case 1:
                    $isEdit = true;
                    $isDelete = !$existAgreement;
                    $isTerminate = $existAgreement;
                    break;
                case 2:
                    $isEdit = $lastMarginApplyContractIdStatusMap[$contract['id']] == 2;
                    $isDelete = !$existAgreement;
                    $isTerminate = $existAgreement;
                    break;
                case 3:
                    $isTerminate = true;
                    break;
            }
            $contract['tags'] = $modelCatalogProduct->getProductTagHtmlForThumb($contract['product_id']);
            $contract['is_edit'] = $isEdit;
            $contract['is_delete'] = $isDelete;
            $contract['is_terminate'] = $isTerminate;
            $contract['detail_link'] = $this->url->to(["account/customerpartner/future/contract/tab", 'id' => $contract['id']]);
            $contract['product_detail_link'] = $this->url->to(['product/product', "product_id" => $contract['product_id']]);
        }
        $this->data['contracts'] = $contracts;

        $total = $this->modelFuturesContract->sellerContractsTotal($this->sellerId, $queries);
        $this->data['total'] = $total;
        $this->data['total_pages'] = ceil($total / $queries['page_limit']);
        $this->data = array_merge($this->data, $queries);
        return $this->response->setOutput($this->render('account/customerpartner/futures/contract_list', $this->data));
    }

    /**
     * 下载合约列表
     */
    public function download()
    {
        $precision = $this->currency->getDecimalPlace($this->currencyCode);
        $queries = $this->request->query->all();
        unset($queries['page']);
        unset($queries['page_limit']);

        $contracts = $this->modelFuturesContract->sellerContracts($this->sellerId, $queries);
        $contractIds = array_column($contracts, 'id');

        $soldContractIdNumMap = empty($contractIds) ? [] : $this->modelFuturesAgreement->agreementNumByContractIdsAndStatus($contractIds, [7]);

        $formatContracts = [];
        foreach ($contracts as $contract) {
            $formatContract['contract_no'] = $contract['contract_no'] . "\t";
            $formatContract['item_code_mpn'] = $contract['sku'] . '/' . $contract['mpn'];
            $formatContract['delivery_date'] = date('Y-m-d', strtotime($contract['delivery_date']));
            $formatContract['num'] = "\t" . ($soldContractIdNumMap[$contract['id']] ?? 0) . '/' . ($contract['num']) . "\t";
            $marginUnitPrice = $this->currency->formatCurrencyPrice(round($contract['margin_unit_price'], $precision), $this->currencyCode);
            $lastUnitPrice = $this->currency->formatCurrencyPrice(round($contract['last_unit_price'], $precision), $this->currencyCode);
            if ($contract['delivery_type'] == 1) {
                $price = $lastUnitPrice;
            } elseif ($contract['delivery_type'] == 2) {
                $price = $marginUnitPrice;
            } else {
                if ($lastUnitPrice > $marginUnitPrice) {
                    $price = $marginUnitPrice . ' - ' . $lastUnitPrice;
                } elseif ($lastUnitPrice < $marginUnitPrice) {
                    $price = $lastUnitPrice . ' - ' . $marginUnitPrice;
                } else {
                    $price = $marginUnitPrice;
                }
            }
            $formatContract['unit_price'] = $price;
            $formatContract['status_name'] = FutureMarginContractStatus::getDescription($contract['status']);
            $formatContract['modified_time'] = $contract['update_time'];
            $formatContracts[] = $formatContract;
        }

        $head = [$this->language->get('text_list_filter_id'), 'Item Code / MPN', $this->language->get('text_list_filter_delivery_date'), 'Agreement(s) QTY / Contract QTY', $this->language->get('text_table_price'), $this->language->get('text_table_status'), $this->language->get('text_table_modified_time')];
        $fileName = 'Contracts_' . date('Ymd') . '.csv';

        outputCsv($fileName, $head, $formatContracts, $this->session);
    }

    /**
     * 合约删除
     * @return JsonResponse
     * @throws Exception
     */
    public function delete()
    {
        if ($this->request->serverBag->get('REQUEST_METHOD') != 'POST') {
            return $this->response->json(['code' => 0, 'msg' => 'error', 'data' => ['error_status' => 1]]);
        }

        $contractIds = $this->request->input->get('ids', '');
        if (empty($contractIds)) {
            return $this->response->json(['code' => 0, 'msg' => 'error', 'data' => ['error_status' => 2]]);
        }

        $contractIds = !is_array($contractIds) ? [$contractIds] : $contractIds;
        $contractIdNumMap = $this->modelFuturesAgreement->agreementNumByContractIdsAndStatus($contractIds, [1, 2, 3, 7]);
        if (!empty($contractIdNumMap)) {
            $canNotDeleteContractIds = array_keys($contractIdNumMap);
            $contractIds = array_diff($contractIds, $canNotDeleteContractIds);
        }
        if (count($contractIds) == 0) {
            return $this->response->json(['code' => 0, 'msg' => 'error', 'data' => ['error_status' => 3]]);
        }

        //货币小数位数
        $precision = $this->currency->getDecimalPlace($this->currencyCode);

        try {
            $this->orm->getConnection()->beginTransaction();

            $this->modelFuturesContract->refundSellerAvailableBalance($this->sellerId, $contractIds, $this->customer->getFirstName() . $this->customer->getLastName(), 'delete', $precision);
            $this->modelFuturesContract->batchUpdateContracts($this->sellerId, $contractIds, ['is_deleted' => 1, 'available_balance' => 0, 'collateral_balance' => 0]);

            $this->orm->getConnection()->commit();
        } catch (Exception $e) {
            $this->orm->getConnection()->rollBack();
            return $this->response->json(['code' => 0, 'msg' => 'error', 'data' => ['error_status' => 4]]);
        }

        return $this->response->json(['code' => 1, 'msg' => 'success', 'data' => ['num' => count($contractIds)]]);
    }

    /**
     * 验证是否可删除
     * @return JsonResponse
     */
    public function verifyDelete()
    {
        $contractIds = $this->request->input->get('ids', '');
        if (empty($contractIds)) {
            return $this->response->json(['code' => 1, 'msg' => 'success', 'data' => []]);
        }

        $contractIds = !is_array($contractIds) ? [$contractIds] : $contractIds;
        $contractIdNumMap = $this->modelFuturesAgreement->agreementNumByContractIdsAndStatus($contractIds, [1, 2, 3, 7]);
        if (!empty($contractIdNumMap)) {
            $canNotDeleteContractIds = array_keys($contractIdNumMap);
            $contractIds = array_diff($contractIds, $canNotDeleteContractIds);
        }
        if (count($contractIds) == 0) {
            return $this->response->json(['code' => 0, 'msg' => 'error', 'data' => []]);
        }

        return $this->response->json(['code' => 1, 'msg' => 'success', 'data' => []]);
    }

    /**
     * 验证是否可终止
     * @return JsonResponse
     */
    public function verifyTerminate()
    {
        $contractIds = $this->request->input->get('ids', '');
        if (empty($contractIds)) {
            return $this->response->json(['code' => 1, 'msg' => 'success', 'data' => []]);
        }

        $contractIds = !is_array($contractIds) ? [$contractIds] : $contractIds;
        $notApprovedContractAgreementNos = $this->modelFuturesAgreement->agreementNoByContractIdsAndStatus($contractIds, [1, 2]);
        if (!empty($notApprovedContractAgreementNos)) {
            return $this->response->json(['code' => 0, 'msg' => 'error', 'data' => ['no' => join(", ", $notApprovedContractAgreementNos)]]);
        }

        return $this->response->json(['code' => 1, 'msg' => 'success', 'data' => []]);
    }

    /**
     * 合约终止
     * @return JsonResponse
     * @throws Exception
     */
    public function terminate()
    {
        if ($this->request->serverBag->get('REQUEST_METHOD') != 'POST') {
            return $this->response->json(['code' => 0, 'msg' => 'error', 'data' => ['error_status' => 1]]);
        }

        $contractIds = $this->request->input->get('ids', '');
        if (empty($contractIds)) {
            return $this->response->json(['code' => 0, 'msg' => 'error', 'data' => ['error_status' => 2]]);
        }

        $contractIds = !is_array($contractIds) ? [$contractIds] : $contractIds;
//        判断可删除的合约不能终止
//        $contractIdNumMap = $this->modelFuturesAgreement->agreementNumByContractIdsAndStatus($contractIds, [1, 2, 3, 7]);
//        if (count($contractIdNumMap) != count($contractIds)) {
//            return $this->response->json(['code' => 0, 'msg' => 'error', 'data' => ['error_status' => 3]]);
//        }

        //判断是否还有未审核的协议
        $notApprovedContractAgreementNos = $this->modelFuturesAgreement->agreementNoByContractIdsAndStatus($contractIds, [1, 2]);
        if (!empty($notApprovedContractAgreementNos)) {
            return $this->response->json(['code' => 0, 'msg' => 'error', 'data' => ['error_status' => 5, 'no' => join(", ", $notApprovedContractAgreementNos)]]);
        }

        //货币小数位数
        $precision = $this->currency->getDecimalPlace($this->currencyCode);

        try {
            $this->orm->getConnection()->beginTransaction();

            $this->modelFuturesContract->refundSellerAvailableBalance($this->sellerId, $contractIds, $this->customer->getFirstName() . $this->customer->getLastName(), 'terminate', $precision);

            $this->orm->getConnection()->commit();
        } catch (Exception $e) {
            $this->orm->getConnection()->rollBack();
            return $this->response->json(['code' => 0, 'msg' => 'error', 'data' => ['error_status' => 4]]);
        }

        return $this->response->json(['code' => 1, 'msg' => 'success', 'data' => []]);
    }

    /**
     * 期货合约详情
     * @return string
     */
    public function tab()
    {
        $this->data['contract_id'] = $this->request->query->get('id', '');

        $contract = $this->modelFuturesContract->contractById($this->data['contract_id']);
        $isEdit = in_array($contract['status'], [1, 2]);
        $lastMarginApply = $this->modelFuturesContract->lastMarginApplyByContractId($this->data['contract_id']);
        if ($lastMarginApply) {
            $this->data['ratio_apply_status'] = $lastMarginApply['status'];
            if ($lastMarginApply['status'] == 0) {
                $isEdit = false;
            }
        }

        $this->data['tab_title'] = $isEdit ? $this->language->get('text_edit_contract_title') : $this->language->get('text_view_contract_title');
        $this->setDocumentInfo($this->data['tab_title']);
        $this->initPage();
        $this->data['breadcrumbs'][] = [
            'text' => $isEdit ? $this->language->get('text_edit_contract_title') : $this->language->get('text_view_contract_title'),
            'href' => 'javascript:void(0);',
            'separator' => $this->language->get('text_separator'),
        ];

        return $this->response->setOutput($this->render('account/customerpartner/futures/contract_tab', $this->data));
    }

    /**
     * 期货合约详情编辑页面
     * @return string
     */
    public function edit()
    {
        $contractId = $this->request->query->get('contract_id', '');
        $precision = $this->currency->getDecimalPlace($this->currencyCode);

        $this->data['is_japan'] = $this->customer->isJapan();
        // 是否显示云送仓提醒
        $this->data['is_show_cwf_notice'] = app(SellerRepository::class)->isShowCwfNotice();

        $contract = $this->modelFuturesContract->contractById($contractId);
        if (empty($contract) || $contract['seller_id'] != $this->sellerId) {
            return $this->render('account/customerpartner/futures/edit_contract', $this->data);
        }

        $contract['delivery_date'] = date('Y-m-d', strtotime($contract['delivery_date']));
        $contract['margin_unit_price'] = $contract['margin_unit_price'] != 0 ? ($this->data['is_japan'] ? round($contract['margin_unit_price'], $precision) : sprintf("%.2f", round($contract['margin_unit_price'], $precision))) : '';
        $contract['last_unit_price'] = $contract['last_unit_price'] != 0 ? ($this->data['is_japan'] ? round($contract['last_unit_price'], $precision) : sprintf("%.2f", round($contract['last_unit_price'], $precision))) : '';
        $isEdit = in_array($contract['status'], [1, 2]);
        $lastMarginApply = $this->modelFuturesContract->lastMarginApplyByContractId($contractId);
        if ($lastMarginApply) {
            $this->data['ratio_apply_status'] = $lastMarginApply['status'];
            if ($lastMarginApply['status'] == 0) {
                $isEdit = false;
            }
        }

        $this->data['account_type'] = $this->customer->getAccountType();
        $this->data['contract_id'] = $contractId;
        $this->data['is_edit'] = $isEdit;

        $contract['payment_ratio'] = $this->data['is_japan'] ? floor($contract['payment_ratio']) : sprintf("%.2f", round($contract['payment_ratio'], 2));

        if ($isEdit) {
            $amount = $this->sellerAmount($this->data['account_type']);
            $this->data['amount'] = $this->data['is_japan'] ? round($amount, 0) : sprintf("%.2f",  round($amount, 2));
        }

        $this->data['min_payment_ratio'] = $this->data['is_japan'] ? floor($this->config->get('min_future_margin')) : sprintf("%.2f", round($this->config->get('min_future_margin'), 2));
        $this->data['max_payment_ratio'] = $this->data['is_japan'] ? floor($this->config->get('max_future_margin')) : sprintf("%.2f", round($this->config->get('max_future_margin'), 2));
        $this->data['sold_contract_num'] = $contract['purchased_num'];
        $this->data['remain_contract_num'] = $contract['status'] == 4 ? 0 : $contract['num'] - $this->data['sold_contract_num'];
        $this->data['contract_info'] = $contract;
        $this->data['history_available_balance'] = $this->modelFuturesContract->totalExpandAmountByContractIds([$contractId], $this->sellerId, $this->data['account_type'] == 1 ? FuturesMarginPayRecordType::LINE_OF_CREDIT : FuturesMarginPayRecordType::oAccountingPayType());
        if ($contract['status'] != 4) {
            $useAvailableBalance  = round($this->data['history_available_balance'], $precision);
        } else {
            $useAvailableBalance = $this->modelFuturesContract->agreementSellerUnitAmountByContractIdAndStatus($contractId, [7], 0, $precision) + $this->modelFuturesContract->agreementSellerUnitAmountByContractIdAndStatus($contractId, [3, 7], 1, $precision);
        }
        $this->data['use_available_balance'] =  $this->data['is_japan'] ? $useAvailableBalance : sprintf("%.2f", $useAvailableBalance);
        $this->data['deposit_percentages'] = join(',', $this->depositPercentages($this->data['is_japan']));
        $this->data['payment_ratio'] =  $this->data['is_japan'] ? floor($this->config->get('default_future_margin')) : sprintf("%.2f", round($this->config->get('default_future_margin'), 2));
        $this->data['currency_symbol_left'] = $this->currency->getSymbolLeft($this->currencyCode);
        $this->data['currency_symbol_right'] = $this->currency->getSymbolRight($this->currencyCode);
        $this->data['currency_code'] = $this->currencyCode;
        $this->data['estimate_receiving_days'] = $this->currencyEstimateReceivingDays();

        return $this->render('account/customerpartner/futures/edit_contract', $this->data);
    }

    /**
     * 处理编辑合约
     * @return JsonResponse
     * @throws Exception
     */
    public function handleEditContract()
    {
        if ($this->request->serverBag->get('REQUEST_METHOD') != 'POST') {
            return $this->response->json(['code' => 0, 'msg' => 'error', 'data' => ['error_status' => 0]]);
        }

        $contractId = $this->request->input->get('id', '');
        $paymentRatio = $this->request->input->get('payment_ratio', '');
        $isBid = $this->request->input->get('is_bid', 0);
        $minNum = $this->request->input->get('min_num', 1);
        $deliveryType = $this->request->input->get('delivery_type', 0);
        $marginUnitPrice = $this->request->input->get('margin_unit_price', '');
        $lastUnitPrice = $this->request->input->get('last_unit_price', '');

        $contract = $this->modelFuturesContract->contractById($contractId);
        if (empty($contract) || $contract['seller_id'] != $this->sellerId) {
            return $this->response->json(['code' => 0, 'msg' => 'error', 'data' => ['error_status' => 1]]);
        }
        if (in_array($contract['status'], [3, 4])) {
            return $this->response->json(['code' => 0, 'msg' => 'error', 'data' => ['error_status' => 2]]);
        }

        if (!empty($minNum) && ($minNum > ($contract['num'] - $contract['purchased_num']))) {
            return $this->response->json(['code' => 0, 'msg' => 'error', 'data' => ['error_status' => 5]]);
        }

        $editParams = [];
        if (!empty($paymentRatio) && $paymentRatio != $contract['payment_ratio']) {
            $editParams['payment_ratio'] = $paymentRatio;
        }
        if ($isBid != $contract['is_bid']) {
            $editParams['is_bid'] = $isBid;
        }
        if (!empty($minNum) && $minNum != $contract['min_num']) {
            $editParams['min_num'] = $minNum;
        }
        if (!empty($deliveryType) && $deliveryType != $contract['delivery_type']) {
            $editParams['delivery_type'] = $deliveryType;
            switch ($deliveryType) {
                case 1:
                    $editParams['last_unit_price'] = $lastUnitPrice;
                    $editParams['margin_unit_price'] = 0;
                    break;
                case 2:
                    $editParams['last_unit_price'] = 0;
                    $editParams['margin_unit_price'] = $marginUnitPrice;
                    break;
                case 3:
                    $editParams['last_unit_price'] = $lastUnitPrice;
                    $editParams['margin_unit_price'] = $marginUnitPrice;
            }
        }
        if (!empty($marginUnitPrice) && $marginUnitPrice != $contract['margin_unit_price']) {
            $editParams['margin_unit_price'] = $marginUnitPrice;
        }
        if (!empty($lastUnitPrice) && $lastUnitPrice != $contract['last_unit_price']) {
            $editParams['last_unit_price'] = $lastUnitPrice;
        }
        if (empty($editParams) && $contract['status'] != FutureMarginContractStatus::DISABLE) {
            return $this->response->json(['code' => 1, 'msg' => 'success', 'data' => []]);
        }

        $amountParams['pay_type'] = $this->customer->getAccountType() == 1 ? FuturesMarginPayRecordType::LINE_OF_CREDIT : FuturesMarginPayRecordType::SELLER_BILL;
        $historyAvailableBalance = $this->modelFuturesContract->totalExpandAmountByContractIds([$contractId], $this->sellerId, $this->customer->getAccountType() == 1 ? FuturesMarginPayRecordType::LINE_OF_CREDIT : FuturesMarginPayRecordType::oAccountingPayType());
        $availableBalance = round($paymentRatio * max([$marginUnitPrice, $lastUnitPrice]) * 0.01, $this->customer->isJapan() ? 0 : 2) * $contract['num'];
        $amountParams['remain_available_balance'] = $availableBalance - $historyAvailableBalance;
        if ($availableBalance > $historyAvailableBalance) {
            $amount = $this->sellerAmount($this->customer->getAccountType());
            if ($amount < $amountParams['remain_available_balance']) {
                return $this->response->json(['code' => 0, 'msg' => 'error', 'data' => ['error_status' => 3]]);
            }
        }

        // 抵押物金额
        $amountParams['collateral_amount'] = app(ContractRepository::class)->getSellerAvailableCollateralAmount(intval($this->sellerId));

        $operator = $this->customer->getFirstName() . $this->customer->getLastName();
        $depositPercentages = $this->depositPercentages($this->customer->isJapan());
        $editParams['status'] = (in_array($this->customer->isJapan() ? floor($paymentRatio) : sprintf("%.2f", round($paymentRatio, 2)), $depositPercentages) || $paymentRatio == $contract['payment_ratio']) ? 1 : 2;
        $res = $this->modelFuturesContract->editContract($contract, $editParams, $operator, $amountParams, $this->customer->isJapan(), $depositPercentages);
        if (!$res) {
            return $this->response->json(['code' => 0, 'msg' => 'error', 'data' => ['error_status' => 4]]);
        }

        return $this->response->json(['code' => 1, 'msg' => 'success', 'data' => []]);
    }

    /**
     * 合约详情关联协议
     * @throws Exception
     */
    public function associate()
    {
        $this->data['contract_id'] = $this->request->query->get('contract_id');
        $this->data['agreement_status'] = $this->request->query->get('agreement_status', 0);
        return $this->render('account/customerpartner/futures/associate_contract', $this->data);
    }

    /**
     * 合约详情关联协议列表数据
     * @return string
     */
    public function agreements()
    {
        $contractId = $this->request->query->get('contract_id', ' ');
        $deliveryStatus = $this->request->query->get('delivery_status', 0);
        $queries['page'] = $this->request->query->get('page', 1);
        $queries['page_limit'] = $this->request->query->get('page_limit', 15);

        //货币小数位数
        $precision = $this->currency->getDecimalPlace($this->currencyCode);

        $contract = $this->modelFuturesContract->contractById($contractId);
        $this->data['delivery_date'] = date('Y-m-d',strtotime($contract['delivery_date']));
        if (empty($contract) || $contract['seller_id'] != $this->sellerId) {
            return $this->render('account/customerpartner/futures/common/agreement', $this->data);
        }

        $this->data['is_delivery_date'] = date('Y-m-d', strtotime($contract['delivery_date'])) == date('Y-m-d', strtotime(changeOutPutByZone(date('Y-m-d H:i:s'), $this->session)));

        switch ($deliveryStatus) {
            case 1:
                $status = [1, 9];
                break;
            case 2:
                $status = [4, 6, 8];
                break;
            case 3:
                $status = [2];
                break;
            default:
                $status = [];
        }
        $agreements = $this->modelFuturesContract->agreements($contractId, $status, $queries);

        $domicileGroupIds = COLLECTION_FROM_DOMICILE;
        $formatAgreements = [];

        $buyerIds = collect($agreements)->pluck('buyer_id')->toArray();
        $buyerCustomerModelMap = Customer::query()->with('buyer')->whereIn('customer_id', $buyerIds)->get()->keyBy('customer_id');
        foreach ($agreements as $agreement) {
            $agreement = (array)$agreement;
            $formatAgreement = [];
            $formatAgreement['agreement_id'] = $agreement['id'];
            $formatAgreement['agreement_no'] = $agreement['agreement_no'];
            $formatAgreement['nickname'] = $agreement['nickname'];
            $formatAgreement['user_number'] = $agreement['user_number'];
            $formatAgreement['num'] = $agreement['num'];
            $formatAgreement['buyer_type'] = in_array($agreement['customer_group_id'], $domicileGroupIds);
            $formatAgreement['seller_available_balance'] = $this->currency->formatCurrencyPrice(round($agreement['unit_price'] * $agreement['seller_payment_ratio'] * 0.01, $precision) * $agreement['num'], $this->currencyCode);
            $formatAgreement['return_available_balance_time'] = !empty($agreement['pay_record_id']) ? $agreement['pay_record_update_time'] : '';
            $formatAgreement['is_breached'] = in_array($agreement['delivery_status'], [2, 4]);
            $formatAgreement['delivery_status_origin'] = $agreement['delivery_status'];
            $formatAgreement['delivery_status'] = in_array($agreement['delivery_status'], [4, 6, 8, 9]) ? 2 : ($agreement['delivery_status'] == 2 ? 3 : 1);
            $formatAgreement['ex_vat'] = VATToolTipWidget::widget(['customer' => $buyerCustomerModelMap->get($agreement['buyer_id']), 'is_show_vat' => true])->render();

            if ($agreement['delivery_status'] == 2) {
                $compensateStatus = $this->language->get('text_agreement_table_refund_status_compensation_for_buyer');
            } else {
                if (!empty($agreement['pay_record_id'])) {
                    $compensateStatus = $this->language->get('text_agreement_table_refund_status_refund_seller');
                } else {
                    $compensateStatus = $this->language->get('text_agreement_table_refund_status_pending_refund_seller');
                }
            }

            $formatAgreement['compensate_status'] = $compensateStatus;
            $formatAgreement['is_agreement_apply'] = empty($agreement['agreement_apply_id']) ? false : true;
            $formatAgreement['agreement_detail_link'] = $this->url->to(['account/product_quotes/futures/sellerFuturesBidDetail', 'id' => $agreement['id']]);

            $formatAgreements[] = $formatAgreement;
        }
        $this->data['agreements'] = $formatAgreements;

        $total = $this->modelFuturesContract->agreementsTotal($contractId, $status);
        $this->data['total'] = $total;
        $this->data['total_pages'] = ceil($total / $queries['page_limit']);
        $queries['delivery_status'] = $deliveryStatus;
        $queries['contract_id'] = $contractId;
        $queries['contract_status'] = $contract['status'];
        $this->data = array_merge($this->data, $queries);

        return $this->render('account/customerpartner/futures/common/agreement', $this->data);
    }

    /**
     * 处理交付操作 只允许确认交付， 方法可提供提前取消交付
     * @param ModelAccountProductQuotesMarginContract $modelAccountProductQuotesMarginContract
     * @param ModelCatalogFuturesProductLock $modelCatalogFuturesProductLock
     * @param ModelCommonProduct $modelCommonProduct
     * @return JsonResponse
     * @throws Exception
     */
    public function handleDelivery(
        ModelAccountProductQuotesMarginContract $modelAccountProductQuotesMarginContract,
        ModelCatalogFuturesProductLock $modelCatalogFuturesProductLock,
        ModelCommonProduct $modelCommonProduct
    )
    {
        if ($this->request->serverBag->get('REQUEST_METHOD') != 'POST') {
            return $this->response->json(['code' => 0, 'msg' => 'error', 'data' => ['error_status' => 0]]);
        }

        $method = $this->request->input->get('method', '');
        if (!in_array($method, ['confirm'])) {
            return $this->response->json(['code' => 0, 'msg' => 'error', 'data' => ['error_status' => 1]]);
        }

        $agreementIds = $this->request->input->get('agreement_ids', '');
        $contractId = $this->request->input->get('contract_id', '');
        $remark = $this->request->input->get('remark', '');
        if (empty($agreementIds) || empty($contractId)) {
            return $this->response->json(['code' => 0, 'msg' => 'error', 'data' => ['error_status' => 2]]);
        }

        $contract = $this->modelFuturesContract->contractById($contractId);
        if (empty($contract) || $contract['seller_id'] != $this->sellerId) {
            return $this->response->json(['code' => 0, 'msg' => 'error', 'data' => ['error_status' => 3]]);
        }

        $deliveryDate = date('Y-m-d', strtotime($contract['delivery_date']));
        $currentDate = date('Y-m-d', strtotime(changeOutPutByZone(date('Y-m-d H:i:s'), $this->session)));
        $isDeliveryDate = $deliveryDate == $currentDate;
        if ($isDeliveryDate && $method == 'cancel') {
            return $this->response->json(['code' => 0, 'msg' => 'error', 'data' => ['error_status' => 4]]);
        }
        if ($deliveryDate < $currentDate) {
            return $this->response->json(['code' => 0, 'msg' => 'error', 'data' => ['error_status' => 5]]);
        }

        $agreementIds = !is_array($agreementIds) ? [$agreementIds] : $agreementIds;
        $agreements = $this->modelFuturesContract->agreements($contractId, [1], ['agreement_ids' => $agreementIds]);
        if (empty($agreements)) {
            return $this->response->json(['code' => 0, 'msg' => 'error', 'data' => ['error_status' => 6]]);
        }

        $operator = $this->customer->getFirstName() . $this->customer->getLastName();
        $execErrorNum = 0;
        $execErrorMsg = [];
        if (!$isDeliveryDate) {
            if (empty($remark)) {
                $remark = 'N/A';
            }

            if ($method == 'cancel') {
                //提前取消交付
                $futuresAgreementApplyType = 2;
                $futuresAgreementLogType = 10;
                $remark = '已向Buyer发起取消交付申请，理由：' . $remark;
            } else {
                //提前确认交付
                $futuresAgreementApplyType = 1;
                $futuresAgreementLogType = 9;
                $remark = $this->language->get('text_future_seller_apply_early_delivery_remark') . $remark;
            }
            foreach ($agreements as $agreement) {
                $agreementArr = (array)$agreement;
                if ($this->modelFuturesAgreement->existUncheckedAgreementApply($agreementArr['id'], $this->sellerId, $futuresAgreementApplyType)) {
                    continue;
                }
                $info['delivery_status'] = null;
                $info['apply_type'] = $futuresAgreementApplyType;
                $info['add_or_update'] = 'add';
                $info['remark'] = $remark;

                $log['info'] = [
                    'agreement_id' => $agreementArr['id'],
                    'customer_id' => $this->sellerId,
                    'type' => $futuresAgreementLogType,
                    'operator' => $operator,
                ];

                $log['agreement_status'] = [$agreementArr['agreement_status'], $agreementArr['agreement_status']];
                $log['delivery_status'] = [$agreementArr['delivery_status'], $agreementArr['delivery_status']];

                try {
                    $this->orm->getConnection()->beginTransaction();
                    $this->modelFuturesAgreement->updateFutureAgreementAction($agreement, $this->sellerId, $info, $log);
                    // 如果有申诉的申请，驳回申述申请
                    $this->modelFuturesAgreement->rejectSellerAppeal($agreement);
                    $this->orm->getConnection()->commit();
                } catch (Exception $e) {
                    $this->orm->getConnection()->rollBack();
                    $execErrorMsg[] = $e->getMessage();
                    $execErrorNum++;
                }
            }
        } else {
            //当天确认交付
            foreach ($agreements as $agreement) {
                $agreementArr = (array)$agreement;
                if ($this->modelFuturesAgreement->existDeliveryAgreementApply($agreementArr['id'], $this->sellerId)) {
                    continue;
                }
                try {
                    // 期货的只要校验库存已经锁库存就可以了
                    if (!$modelCommonProduct->checkProductQtyIsAvailable(intval($agreementArr['product_id']), intval($agreementArr['num']))) {
                        throw new Exception(sprintf($this->language->get('text_agreement_delivery_low_stock_quantity'), $agreementArr['agreement_no']));
                    }

                    $this->orm->getConnection()->beginTransaction();
                    $apply_id = $this->modelFuturesAgreement->addFutureApply([
                        'agreement_id' => $agreementArr['id'],
                        'customer_id' => $this->sellerId,
                        'apply_type' => 5,
                        'status' => 1,
                    ]);
                    $this->modelFuturesAgreement->addMessage([
                        'agreement_id' => $agreementArr['id'],
                        'customer_id' => $this->sellerId,
                        'apply_id' => $apply_id,
                        'message' => sprintf($this->language->get('text_future_seller_apply_delivery_remark'), $agreementArr['num'], $agreementArr['agreement_no']),
                    ]);

                    $data = [
                        'update_time' => date('Y-m-d H:i:s'),
                        'delivery_status' => 6,  // To be paid
                        'confirm_delivery_date' => date('Y-m-d H:i:s'),
                        'delivery_date' => date('Y-m-d H:i:s'),
                    ];
                    if (in_array($agreementArr['delivery_type'], FutureMarginContractDeliveryType::getIncludeMarginUnit()) && !$agreementArr['margin_agreement_id']) {
                        // 生成现货协议
                        $marginAgreement = $this->modelFuturesAgreement->addNewMarginAgreement($agreement, $this->customer->getCountryId());
                        // 生成现货头款产品
                        $advanceProductId = $this->modelFuturesAgreement->copyMarginProduct($marginAgreement, 1);
                        // 创建现货保证金记录
                        $marginProcess = [
                            'margin_id' => $marginAgreement['agreement_id'],
                            'margin_agreement_id' => $marginAgreement['agreement_no'],
                            'advance_product_id' => $advanceProductId,
                            'process_status' => 1,
                            'create_time' => date('Y-m-d H:i:s'),
                            'create_username' => $this->sellerId,
                            'program_code' => 'V1.0'
                        ];
                        $modelAccountProductQuotesMarginContract->addMarginProcess($marginProcess);
                        // 更新期货交割表
                        $data['margin_agreement_id'] = $marginAgreement['agreement_id'];
                        $this->modelFuturesAgreement->updateDelivery($agreementArr['id'], $data);
                        $modelCatalogFuturesProductLock->TailIn($agreementArr['id'],$agreementArr['margin_apply_num'], $agreementArr['id'], 0);
                        $modelCatalogFuturesProductLock->TailOut($agreementArr['id'], $agreementArr['margin_apply_num'], $agreementArr['id'], 6);
                        // 期货保证金比例大于现货，直接变为completed状态逻辑
                        $orderId = $this->modelFuturesAgreement->autoFutureToMarginCompleted($agreementArr['id']);
                    } else {
                        $modelCatalogFuturesProductLock->TailIn($agreementArr['id'], $agreementArr['num'], $agreementArr['id'], 0);
                        $this->modelFuturesAgreement->updateDelivery($agreementArr['id'], $data);
                        // 需要更新delivery表中的关于期货部分的数量
                    }

                    $communicationInfo = [
                        'from' => $agreementArr['seller_id'],
                        'to' => $agreementArr['buyer_id'],
                        'country_id' => $this->customer->getCountryId(),
                        'status' => 1,
                        'communication_type' => 9,
                        'apply_type' => 5,
                    ];
                    $this->modelFuturesAgreement->addFuturesAgreementCommunication($agreementArr['id'],9, $communicationInfo);

                    $log = [
                        'agreement_id' => $agreementArr['id'],
                        'customer_id' => $this->sellerId,
                        'type' => 13,
                        'operator' => $operator,
                    ];
                    $this->modelFuturesAgreement->addAgreementLog($log,
                        [$agreementArr['agreement_status'], $agreementArr['agreement_status']],
                        [$agreementArr['delivery_status'], $data['delivery_status']]
                    );

                    $this->orm->getConnection()->commit();
                    if (isset($orderId)) {
                        $this->modelFuturesAgreement->autoFutureToMarginCompletedAfterCommit($orderId);
                    }
                } catch (Exception $e) {
                    $this->orm->getConnection()->rollBack();
                    $execErrorMsg[] = $e->getMessage();
                    $execErrorNum++;
                }
            }
        }
        if ($execErrorNum == count($agreements)) {
            return $this->response->json(['code' => 0, 'msg' => 'error', 'data' => ['error_status' => 7, 'error_msg' => $execErrorMsg[0] ?? '']]);
        }

        return $this->response->json(['code' => 1, 'msg' => 'success', 'data' => ['success_num' => count($agreements) - $execErrorNum]]);
    }

    /**
     * 合约详情关联协议统计
     * @return string
     */
    public function agreementStat()
    {
        $contractId = $this->request->query->get('contract_id');

        $contract = $this->modelFuturesContract->contractById($contractId);
        if ($contract['seller_id'] != $this->sellerId) {
            return $this->render('account/customerpartner/futures/common/agreement_stat', $this->data);
        }

        $this->data['contract_id'] = $contractId;
        $leftDays = ceil((strtotime(substr($contract['delivery_date'], 0, 10) . ' 23:59:59') - strtotime(changeOutPutByZone(date('Y-m-d H:i:s'), $this->session))) / 86400);
        $this->data['left_days'] = $leftDays < 0 ? 0 : $leftDays;
        $this->data['contract_status'] = $contract['status'];

        $nonDeliveryAgreementCount = $this->modelFuturesContract->agreementQuantityStat($contractId, [1, 9]);
        $deliveryAgreementCount = $this->modelFuturesContract->agreementQuantityStat($contractId, [4, 6, 8]);
        $cancelDeliveryAgreementCount = $this->modelFuturesContract->agreementQuantityStat($contractId, [2]);
        $this->data['all_delivery'] = [
            'delivery_status' => 0,
            'count' => $nonDeliveryAgreementCount + $deliveryAgreementCount + $cancelDeliveryAgreementCount,
        ];
        $this->data['non_delivery'] = [
            'delivery_status' => 1,
            'count' => $nonDeliveryAgreementCount,
        ];
        $this->data['delivery'] = [
            'delivery_status' => 2,
            'count' => $deliveryAgreementCount,
        ];
        $this->data['cancel_delivery'] = [
            'delivery_status' => 3,
            'count' => $cancelDeliveryAgreementCount,
        ];

        return $this->render('account/customerpartner/futures/common/agreement_stat', $this->data);
    }

    /**
     * 合约详情变更记录页面
     * @return string
     */
    public function log()
    {
        $this->data['contract_id'] = $this->request->query->get('contract_id');

        return $this->render('account/customerpartner/futures/contract_log', $this->data);
    }

    /**
     * 合约详情变更记录页面数据
     * @return JsonResponse
     */
    public function getContractLogs()
    {
        $contractId = $this->request->query->get('contract_id');
        $page = $this->request->query->get('page', 1);
        $pageLimit = $this->request->query->get('page_limit', 5);

        $contract = $this->modelFuturesContract->contractById($contractId);
        if ($contract['seller_id'] != $this->sellerId) {
            $data = [
                "is_end" => true,
                "html" => $this->load->view('account/customerpartner/futures/common/contract_log', ['logs' => []]),
            ];
            return $this->response->json($data);
        }

        //货币小数位数
        $precision = $this->currency->getDecimalPlace($this->currencyCode);

        $logs = $this->modelFuturesContract->contractLogs($contractId, $page, $pageLimit);
        foreach ($logs as &$log) {
            $log['type_name'] = FutureMarginContractLogType::getDescription($log['type']);
            $log['operator_type'] = (empty($log['customer_id']) || $log['type'] == FutureMarginContractLogType::AUTO_TERMINATE) ? 'Marketplace' : 'Seller';

            $content = json_decode($log['content'], true);
            if (strstr($content['status'], '->')) {
                list($from, $to) = explode("->", $content['status'], 2);
                $log['status'] = FutureMarginContractStatus::getDescription($from) . '->' . FutureMarginContractStatus::getDescription($to);
                if ($log['type'] == FutureMarginContractLogType::VERDICT) {
                    $log['type_name'] .= $to == FutureMarginContractStatus::DISABLE ? $this->language->get('text_log_verdict_rejected') : $this->language->get('text_log_verdict_approved');
                }
            } else {
                $log['status'] = FutureMarginContractStatus::getDescription($content['status']);
                if ($log['type'] == FutureMarginContractLogType::VERDICT) {
                    $log['type_name'] .= $content['status'] == FutureMarginContractStatus::DISABLE ? $this->language->get('text_log_verdict_rejected') : $this->language->get('text_log_verdict_approved');
                }
            }

            if (strstr($content['is_bid'], '->')) {
                list($from, $to) = explode("->", $content['is_bid'], 2);
                $log['is_bid'] = ($from ? 'Yes' : 'No') . '->' . ($to ? 'Yes' : 'No');
            } else {
                $log['is_bid'] = $content['is_bid'] ? 'Yes' : 'No';
            }
            $log['min_num'] = $content['min_num'];
            if (strstr($content['last_unit_price'], '->')) {
                list($lastPriceFrom, $lastPriceTo) = explode("->", $content['last_unit_price'], 2);
                $lastPrice = $this->currency->formatCurrencyPrice(round($lastPriceFrom, $precision), $this->currencyCode) . '->' . $this->currency->formatCurrencyPrice(round($lastPriceTo, $precision), $this->currencyCode);
            } else {
                $lastPrice = $this->currency->formatCurrencyPrice(round($content['last_unit_price'], $precision), $this->currencyCode);
            }
            if (strstr($content['margin_unit_price'], '->')) {
                list($marginPriceFrom, $marginPriceTo) = explode("->", $content['margin_unit_price'], 2);
                $marginPrice = $this->currency->formatCurrencyPrice(round($marginPriceFrom, $precision), $this->currencyCode) . '->' . $this->currency->formatCurrencyPrice(round($marginPriceTo, $precision), $this->currencyCode);
            } else {
                $marginPrice = $this->currency->formatCurrencyPrice(round($content['margin_unit_price'], $precision), $this->currencyCode);
            }
            if (strval($content['delivery_type']) === '1') {
                $log['unit_price'] = $lastPrice;
            } elseif (strval($content['delivery_type']) === '2') {
                $log['unit_price'] = $marginPrice;
            } else {
                $log['unit_price'] = $lastPrice . "<br/>" .  $marginPrice;
            }
            if (strstr($content['delivery_type'], '->')) {
                list($from, $to) = explode("->", $content['delivery_type'], 2);
                $log['delivery_type'] = FutureMarginContractDeliveryType::getDescription($from) . '->' . FutureMarginContractDeliveryType::getDescription($to);
            } else {
                $log['delivery_type'] = FutureMarginContractDeliveryType::getDescription($content['delivery_type']);
            }
            if (strstr($content['payment_ratio'], '->')) {
                list($from, $to) = explode("->", $content['payment_ratio'], 2);
                $log['payment_ratio'] =  (($precision == 0 ? round($from, 0) : sprintf("%.2f", $from))) . '%' . '->' .  (($precision == 0 ? round($to, 0) : sprintf("%.2f", $to))) . '%';
            } else {
                $log['payment_ratio'] = ($precision == 0 ? round($content['payment_ratio'], 0) : sprintf("%.2f", $content['payment_ratio'])) . '%';
            }
        }

        $total = $this->orm->table('oc_futures_contract_log')->where('contract_id', $contractId)->count();
        $data = [
            "is_end" => ceil($total / $pageLimit) <= $page,
            "html" => $this->load->view('account/customerpartner/futures/common/contract_log', ['logs' => $logs]),
        ];
        return $this->response->json($data);
    }

    private function initPage()
    {
        $this->setLanguages('account/customerpartner/futures');

        $this->data['separate_view'] = true;
        $this->data['separate_column_left'] = $this->renderController('account/customerpartner/column_left');
        $this->data['footer'] = $this->renderController('account/customerpartner/footer');
        $this->data['header'] = $this->renderController('account/customerpartner/header');

        $this->data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_market_business'),
                'href' => 'javascript:void()',
                'separator' => $this->language->get('text_separator'),
            ],
            [
                'text' => $this->language->get('text_list_title'),
                'href' => $this->url->to('account/customerpartner/future/contract/list'),
                'separator' => $this->language->get('text_separator'),
            ],
        ];
    }
}
