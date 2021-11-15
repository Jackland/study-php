<?php

use App\Components\Storage\StorageCloud;
use App\Enums\Future\FuturesMarginApplyType;
use App\Enums\Future\FuturesMarginDeliveryStatus;
use App\Enums\Product\ProductTransactionType;
use App\Helper\CountryHelper;
use App\Logging\Logger;
use App\Models\Customer\Customer;
use App\Models\Futures\FuturesAgreementFile;
use App\Models\Margin\MarginAgreement;
use App\Repositories\Futures\AgreementApplyepository;
use App\Repositories\Futures\AgreementFileRepository;
use App\Repositories\Marketing\MarketingDiscountRepository;
use App\Repositories\Product\ProductPriceRepository;
use App\Repositories\ProductLock\ProductLockRepository;
use App\Repositories\Seller\SellerRepository;
use App\Services\Future\AgreementApply;
use App\Widgets\VATToolTipWidget;
use Carbon\Carbon;
use Catalog\model\futures\credit;
use Catalog\model\futures\agreementMargin;
use \Framework\Http\Request;

/**
 * Class ControllerAccountProductQuotesFutures
 * @property ModelAccountProductQuotesMarginAgreement $model_account_product_quotes_margin_agreement
 * @property ModelAccountProductQuotesMarginContract $model_account_product_quotes_margin_contract
 * @property ModelCustomerpartnerMaster $model_customerpartner_master
 * @property ModelFuturesAgreement $model_futures_agreement
 * @property ModelFuturesTemplate $model_futures_template
 * @property ModelCatalogFuturesProductLock $model_catalog_futures_product_lock
 * @property ModelCatalogInformation $model_catalog_information
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelCommonProduct $model_common_product
 * @property ModelFuturesContract $model_futures_contract
 * @property ModelMessageMessage $model_message_message
 */
class ControllerAccountProductQuotesFutures extends Controller
{

    public function __construct(Registry $registry)
    {

        parent::__construct($registry);

        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/account', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }

    }

    /*
     * Bid List页 期货保证金协议列表页 buyer
     * */
    public function tab_futures()
    {

        $agreement_no = trim($this->request->query->get('agreement_no', ''));
        $sku = trim($this->request->query->get('item_code', ''));
        $agreement_status = $this->request->query->getInt('agreement_status');
        $delivery_status = $this->request->query->getInt('delivery_status');
        $status = $this->request->query->getInt('status');
        $store_name = trim($this->request->query->get('store_name', ''));
        $delivery_date_from = $this->request->query->get('delivery_date_from', '');
        $delivery_date_to = $this->request->query->get('delivery_date_to', '');
        $date_from = $this->request->query->get('date_from', '');
        $date_to = $this->request->query->get('date_to', '');
        $page_num = max(1, $this->request->query->getInt('page_num'));
        $page_limit = max(1, $this->request->query->getInt('page_limit', 15));
        $sort = $this->request->query->get('sort', 'update_time');//默认按照协议更新时间降序排列
        $order = $this->request->query->get('order', 'DESC');

        $this->language->load('account/product_quotes/wk_product_quotes');
        $this->language->load('futures/agreement');
        $this->load->model('futures/agreement');
        $customerId = $this->customer->getId();

        $data = $filterData = [
            'agreement_no' => $agreement_no,
            'sku' => $sku,
            'status' => $status,
            'agreement_status' => $agreement_status,
            'delivery_status' => $delivery_status,
            'store_name' => $store_name,
            'delivery_date_from' => $delivery_date_from,
            'delivery_date_to' => $delivery_date_to,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'start' => ($page_num - 1) * $page_limit,
            'limit' => $page_limit,
            'sort' => $sort,
            'order' => $order
        ];

        $list = $this->model_futures_agreement->agreementListForBuyer($customerId, $filterData);
        $total = $list['total'];
        $data['agreement_list'] = $list['agreement_list'];

        $data['agreement_status_list'] = $this->model_futures_agreement->agreementStatusList();
        $data['delivery_status_list'] = $this->model_futures_agreement->deliveryStatusList();
        unset($data['delivery_status_list'][7]);
        $data['to_be_processed_count'] = $this->model_futures_agreement->toBeProcessedCount($customerId);//待处理的协议
        $data['to_be_delivered_count'] = $this->model_futures_agreement->toBeDeliveredCount($customerId);//等待入仓
        $data['to_be_paid_count'] = $this->model_futures_agreement->toBePaidCount($customerId);//等待交割
        $data['due_soon_count'] = $this->model_futures_agreement->dueSoonCount($customerId);//即将到交货日期和即将超时的协议
        $data['futures_tab_mark_count'] = $data['to_be_processed_count'] + $data['to_be_delivered_count'] + $data['to_be_paid_count'] + $data['due_soon_count'];

        $data['agreement_detail_url'] = $this->url->link('account/product_quotes/futures/detail', '', true);
        $data['product_detail_url'] = $this->url->link('product/product', '&product_id=', true);


        //分页
        $total_pages = ceil($total / $page_limit);
        $data['total_pages'] = $total_pages;
        $data['page_num'] = $page_num;
        $data['total'] = $total;
        $data['page_limit'] = $page_limit;
        $data['results'] = sprintf($this->language->get('text_pagination'), ($total) ? (($page_num - 1) * $page_limit) + 1 : 0, ((($page_num - 1) * $page_limit) > ($total - $page_limit)) ? $total : ((($page_num - 1) * $page_limit) + $page_limit), $total, $total_pages);


        $this->response->setOutput($this->load->view('account/product_quotes/tab_new_futures', $data));
    }

    /*
     * Bid List页 期货保证金协议详情页 buyer
     * @throws Exception
     * @version 期货保证金一期
     * */
    public function detail()
    {
        $agreementId = intval($this->request->query->getInt('id'));

        $this->language->load('account/product_quotes/wk_product_quotes');
        $this->language->load('futures/agreement');
        $this->language->load('futures/template');
        $this->load->model('futures/agreement');
        $this->load->model('futures/template');
        $this->load->model('catalog/information');

        $data['agreement'] = $this->model_futures_agreement->agreementInfoForBuyer($agreementId);
        if ($data['agreement']['buyer_id'] != $this->customer->getId()) {
            return $this->response->redirectTo($this->url->link('account/product_quotes/wk_quote_my', ['tab' => 3]));
        }
        if ($data['agreement']['contract_id']) {//实际是二期协议，则跳转至二期详情页
            return $this->buyerFuturesBidDetail();
        }
        // 距离交付日期还剩几天话术
        $data['days_tip'] = $this->getDeliveryDaysTip($data['agreement']['expected_delivery_date']);
        $data['message'] = $this->model_futures_agreement->messageInfo($agreementId);
        $data['customer_id'] = $this->customer->getId();
        $data['country_id'] = $this->customer->getCountryId();
        $data['nickname'] = $this->customer->getNickName();
        $data['is_futures'] = $this->model_futures_template->isFutures($data['agreement']['product_id']);
        $data['margin_payment_ratio'] = MARGIN_PAYMENT_RATIO;//现货保证金定金支付比例


        $informationInfo = $this->model_catalog_information->getInformation($this->config->get('futures_information_id_buyer'));
        if ($informationInfo) {
            $data['clause_url'] = $this->url->link('information/information', ['information_id' => $informationInfo['information_id']]);
            $data['clause_title'] = $informationInfo['title'];
        }
        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/home'),
                'separator' => false
            ],
            [
                'text' => $this->language->get('heading_title_my'),
                'href' => $this->url->link('account/product_quotes/wk_quote_my', ['tab' => 3]),
                'separator' => $this->language->get('text_separator')
            ],
            [
                'text' => $this->language->get('text_detail_title'),
                'href' => $this->url->link('account/product_quotes/futures/detail', ['id' => $agreementId]),
            ]
        ];
        $this->document->setTitle($this->language->get('text_detail_title'));

        $data['header'] = $this->load->controller('common/header');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('account/product_quotes/futures/detail', $data));
    }


    /**
     * Bid List页 期货保证金协议详情页 buyer
     * @version 期货保证金二期，期货协议详情
     */
    public function buyerFuturesBidDetail()
    {
        $agreement_id = $this->request->query->getInt('id');
        $ado          = trim($this->request->query->get('ado', ''));

        $this->language->load('account/product_quotes/wk_product_quotes');
        $this->language->load('futures/agreement');
        $this->load->model('futures/agreement');

        // 加载页面布局
        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/home'),
                'separator' => false
            ],
            [
                'text' => $this->language->get('heading_title_my'),
                'href' => $this->url->link('account/product_quotes/wk_quote_my', '&tab=3'),
                'separator' => $this->language->get('text_separator')
            ],
            [
                'text' => $this->language->get('text_detail_title'),
                'href' => $this->url->link('account/product_quotes/futures/buyerFuturesBidDetail', '&id=' . $agreement_id),
            ]
        ];
        $this->document->setTitle($this->language->get('text_detail_title'));

        $data['agreement'] = $this->model_futures_agreement->getAgreementById($agreement_id);
        if (!$data['agreement']->contract_id) {//实际是一期协议，则跳转至一期详情页
            return $this->detail();
        }
        $data['id'] = $agreement_id;
        $data['ado']= $ado;


        $data['header'] = $this->load->controller('common/header');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('account/product_quotes/futures/buyer_futures_bid_detail', $data));
    }

    /**
     * 期货二期，协议详情-期货协议
     * @throws Exception
     */
    public function buyerFuturesAgreementDetail()
    {
        $agreement_id = $this->request->query->getInt('id');
        $ado          = trim($this->request->query->get('ado', ''));
        $buyer_id = $this->customer->getId();

        if (JAPAN_COUNTRY_ID == $this->customer->getCountryId()){
            $format = '%d';
            $precision = 0;
        }else{
            $format = '%.2f';
            $precision = 2;
        }
        $currency_code = $this->session->get('currency', 'USA');
        $country = $this->session->get('country');
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);

        $this->language->load('futures/agreement');
        $this->load->model('futures/agreement');
        $this->load->model('customerpartner/master');
        $this->load->model('catalog/product');
        $this->load->model('catalog/information');
        $data['precision'] = $precision;
        $data['country_id'] = $this->customer->getCountryId();
        $data['messages']= [];
        $data['partner'] = [];
        $data['is_japan'] = 0;
        $data['all_earnest'] = '';
        $data['all_seller_earnest'] = '';
        $data['unit_price_show']    = '';
        $data['all_earnest_show']   = '';
        $data['agreement_status_info'] = [];
        $data['delivery_status_info']  = [];

        $data['agreement'] = $this->model_futures_agreement->getAgreementById($agreement_id);
        if ($data['agreement']) {
            if($data['agreement']->ignore){
                $data['agreement']->agreement_status = ModelFuturesAgreement::IGNORE_STATUS;
            }
            if ($data['agreement']->delivery_date) {
                $data['agreement']->show_delivery_date = dateFormat($fromZone, $toZone, $data['agreement']->delivery_date, 'Y-m-d');
            } elseif ($data['agreement']->expected_delivery_date) {
                $data['agreement']->show_delivery_date = $data['agreement']->expected_delivery_date;
            } else {
                $data['agreement']->show_delivery_date = 'N/A';
            }
            $data['agreement']->agreement_id = $agreement_id;
            $data['agreement']->tag = $this->model_catalog_product->getProductTagHtmlForThumb($data['agreement']->product_id);
            // 获取消息
            $data['messages'] = $this->handleMessage($this->model_futures_agreement->getApprovalMessages($agreement_id));
            // 获取seller的信息
            $seller_id = $data['agreement'] ? $data['agreement']->seller_id : 0;
            $partner = $this->model_customerpartner_master->getProfile($seller_id);
            $data['partner'] = $partner;
            $data['is_japan'] = JAPAN_COUNTRY_ID == $this->customer->getCountryId() ? 1 : 0;
            // 协议定金
            if ($data['is_japan']) {
                $data['agreement']->unit_price = round($data['agreement']->unit_price);
                $data['all_earnest'] = round($data['agreement']->unit_price * $data['agreement']->buyer_payment_ratio / 100) * $data['agreement']->num;
            } else {
                $data['all_earnest'] = sprintf('%.2f', round($data['agreement']->unit_price * $data['agreement']->buyer_payment_ratio / 100, 2) * $data['agreement']->num);
            }

            if ($data['is_japan']) {
                $data['all_seller_earnest'] = round($data['agreement']->unit_price * $data['agreement']->seller_payment_ratio / 100) * $data['agreement']->num;
            } else {
                $data['all_seller_earnest'] = sprintf('%.2f', round($data['agreement']->unit_price * $data['agreement']->seller_payment_ratio / 100, 2) * $data['agreement']->num);
            }
            // 协议单价
            $data['unit_price_show'] = $this->currency->format($data['agreement']->unit_price, $currency_code);
            $data['all_earnest_show'] = $this->currency->format($data['all_earnest'], $currency_code);
            // buyer 支付的协议定金
            // 获取状态 name 和 color
            $data['agreement_status_info'] = ModelFuturesAgreement::AGREEMENT_STATUS[$data['agreement']->agreement_status];
            $data['delivery_status_info'] = ModelFuturesAgreement::DELIVERY_STATUS[$data['agreement']->delivery_status] ?? ['name' => 'N/A', 'color' => 'grey'];
            if (in_array($data['agreement']->agreement_status, [1, 2, 3])) {
                //获取合约可用数量
                $contract_remain_num = intval($this->model_futures_agreement->getContractRemainQty($data['agreement']->contract_id));
                if (in_array($data['agreement']->agreement_status, [1, 2])) {//1 Applied , 2 Pending
                    $can_edit_num = $contract_remain_num;

                } elseif (in_array($data['agreement']->agreement_status, [3])) {//3 Approved
                    $can_edit_num = $contract_remain_num + $data['agreement']->num;
                }
                if ($can_edit_num >= $data['agreement']->min_num) {
                    $data['agreement']->max_num = $can_edit_num;
                } else {
                    $data['agreement']->max_num = $data['agreement']->min_num;
                }
            }
        }
        $data['customerId'] = $buyer_id;
        // 防止重复提交
        $data['csrf_token'] = time() . mt_rand(100000, 999999);
        // 防止把期货一期的去掉了
        $this->session->set('futures_csrf_token', $data['csrf_token']);
        $data['symbolLeft']  = $this->currency->getSymbolLeft($currency_code);
        $data['symbolRight'] = $this->currency->getSymbolRight($currency_code);
        $data['url_edit_agreement'] = $this->url->link('account/product_quotes/futures/editAgreement', '', true);
        $data['url_bid_list'] = str_replace('&amp;', '&', $this->url->link('account/product_quotes/wk_quote_my', '&tab=3', true));
        $data['ado']          = $ado;
        $information_info = $this->model_catalog_information->getInformation($this->config->get('futures_information_id_buyer'));
        if ($information_info) {
            $data['clause_url'] = $this->url->link('information/information', 'information_id=' . $information_info['information_id'], true);
            $data['clause_title'] = $information_info['title'];
        }
        $this->response->setOutput($this->load->view('account/product_quotes/futures/buyer_futures_agreement_detail', $data));
    }

    /**
     * 期货二期，协议详情-期货保证金
     * @throws Exception
     */
    public function buyerFuturesDepositOrderDetail()
    {
        $agreement_id = $this->request->query->getInt('id');
        $currency_code = $this->session->get('currency');
        // 获取
        $this->language->load('futures/agreement');
        $this->load->model('futures/agreement');
        $this->load->model('customerpartner/master');
        $agreementObj = $this->model_futures_agreement->getAgreementById($agreement_id);
        $purchase_order_info = (object)[];
        if ($agreementObj) {
            $seller_id = intval($agreementObj->seller_id);
            $partner = $this->model_customerpartner_master->getProfile($seller_id);
            $advance_product_id = $this->model_futures_agreement->getFuturesAdvanceProductId($agreement_id);
            // 获取期货保证金订单详情
            $purchase_order_info = $this->model_futures_agreement->getPurchaseOrderInfoByProductId($advance_product_id, $this->customer->getCountryId());
            if ($purchase_order_info) {
                $purchase_order_info->seller_id  = $seller_id;
                $purchase_order_info->screenname = $partner['screenname'];
            }
        }
        $data['purchase_order_info'] = $purchase_order_info;
        $data['symbolLeft']  = $this->currency->getSymbolLeft($currency_code);
        $data['symbolRight'] = $this->currency->getSymbolRight($currency_code);
        $this->response->setOutput($this->load->view('account/product_quotes/futures/buyer_futures_deposit_order_detail', $data));
    }

    /**
     * 期货二期，协议详情-入仓
     * @throws Exception
     */
    public function buyerFuturesTransactionModeDetail()
    {
        $agreement_id = $this->request->query->getInt('id');
        $this->language->load('futures/agreement');
        $this->load->model('futures/agreement');
        $country_id = $this->customer->getCountryId();
        $data['customerId'] = $this->customer->getId();
        $data['agreement'] = $this->model_futures_agreement->getFutureTransactionModeDetail($agreement_id, $country_id);
        // 获取消息
        $data['messages'] = $this->buyerHandleMessage($this->model_futures_agreement->getApplyMessagesForBuyer($agreement_id, $this->customer->getId()));
        $this->response->setOutput($this->load->view('account/product_quotes/futures/buyer_futures_transaction_mode_detail', $data));
    }

    /**
     * 期货二期，协议详情-交割
     */
    public function buyerFuturesPurchaseRecordDetail()
    {
        $agreement_id = $this->request->query->getInt('id');
        $this->language->load('futures/agreement');
        $this->load->model('futures/agreement');
        $country_id = $this->customer->getCountryId();
        // 获取交割数据的详情
        $data['record'] = $this->model_futures_agreement->getBuyerFuturesPurchaseRecordDetailMainById($agreement_id);

        $data['is_europe'] = in_array($country_id, EUROPE_COUNTRY_ID);
        $data['agreement_id'] = $agreement_id;
        $this->response->setOutput($this->load->view('account/product_quotes/futures/buyer_futures_purchase_record_detail', $data));
    }


    /**
     * 期货二期，协议详情-交割数据期货订单列表
     */
    public function buyerFuturesPurchaseRecordDetailList()
    {
        $agreement_id = $this->request->query->getInt('id');
        $page = max(1, $this->request->query->getInt('page'));
        $page_limit = 15;
        $this->language->load('futures/agreement');
        $this->load->model('futures/agreement');
        $country_id = $this->customer->getCountryId();
        $data['is_europe'] = in_array($country_id, EUROPE_COUNTRY_ID);

        // 获取交割数据期货订单列表
        $lists = $this->model_futures_agreement->getBuyerFuturesPurchaseRecordDetailListById($agreement_id, $page);
        unset($value);
        foreach ($lists as $key => &$value) {
            $new_timezone = changeOutPutByZone($value['date_modified'], $this->session);
            $value['update_day'] = substr($new_timezone, 0, 10);
            $value['update_hour'] = substr($new_timezone, 11);
        }
        unset($value);


        if (count($lists) < $page_limit) {
            $is_end = 1;
            if ($lists) {
                $htmls = $this->load->controller('account/product_quotes/futures/buyerFuturesPurchaseRecordDetailListHtml', $lists, $page_limit);
            } else {
                $htmls = '';
            }
        } else {
            $is_end = 0;
            $htmls = $this->load->controller('account/product_quotes/futures/buyerFuturesPurchaseRecordDetailListHtml', $lists, $page_limit);
        }


        $data['is_end'] = $is_end;
        $data['htmls'] = $htmls;
        return $this->response->json($data);
    }

    /**
     * 期货二期
     * @param $lists
     * @return string
     */
    public function buyerFuturesPurchaseRecordDetailListHtml($lists)
    {
        $this->language->load('common/cwf');
        $country_id = $this->customer->getCountryId();
        $data['is_europe'] = in_array($country_id, EUROPE_COUNTRY_ID);
        $data['lists'] = $lists;
        return $this->load->view('account/product_quotes/futures/buyer_futures_purchase_record_detail_list', $data);
    }


    public function sellerFuturesPurchaseRecordDetailList()
    {
        $agreement_id = intval($this->request->get['id']);
        $page = intval($this->request->get['page']);
        $page_limit = 15;
        $this->load->model('futures/agreement');
        $country_id = $this->customer->getCountryId();
        $data['is_europe'] = in_array($country_id, EUROPE_COUNTRY_ID);

        // 获取交割数据期货订单列表
        $lists = $this->model_futures_agreement->getSellerFuturesPurchaseRecordDetailListById($agreement_id, $page, $page_limit);
        unset($value);
        foreach ($lists as $key => &$value) {
            $new_timezone = changeOutPutByZone($value['date_modified'], $this->session);
            $value['update_day'] = substr($new_timezone, 0, 10);
            $value['update_hour'] = substr($new_timezone, 11);
        }
        unset($value);

        if (count($lists) < $page_limit) {
            $is_end = 1;
            if ($lists) {
                $htmls = $this->load->controller('account/product_quotes/futures/sellerFuturesPurchaseRecordDetailListHtml', $lists);
            } else {
                $htmls = '';
            }
        } else {
            $is_end = 0;
            $htmls = $this->load->controller('account/product_quotes/futures/sellerFuturesPurchaseRecordDetailListHtml', $lists);
        }


        $data['is_end'] = $is_end;
        $data['htmls'] = $htmls;
        $this->response->returnJson($data);
    }

    public function sellerFuturesPurchaseRecordDetailListHtml($lists)
    {
        $this->language->load('common/cwf');
        $country_id = $this->customer->getCountryId();
        $data['is_europe'] = in_array($country_id, EUROPE_COUNTRY_ID);
        $data['lists'] = $lists;
        return $this->load->view('customerpartner/future/seller_futures_purchase_record_detail_list', $data);
    }


    /*
     * 产品详情页 Place Bid 提交申请 buyer
     * */
    public function addAgreement()
    {
        $postData = $this->request->post();
        $validateResult = $this->validatePostData($postData);
        if (!$validateResult['status']) {
            $this->response->setOutput(json_encode(['error' => $validateResult['msg']]));
        } else {

            $this->language->load('futures/template');
            $this->load->model('futures/agreement');
            $this->load->model('futures/contract');

            $contract = $this->model_futures_contract->contractById($postData['contract_id'], 1);
            if (JAPAN_COUNTRY_ID == $this->customer->getCountryId()) {
                $format = '%d';
                $precision = 0;
            } else {
                $format = '%.2f';
                $precision = 2;
            }
            if (!$contract) {
                return $this->response->setOutput(json_encode(['error' => $this->language->get('error_futures_seller')]));
            }
            // 协议数不能小于合约最低购买数量
            if ($contract['min_num'] > $postData['qty']) {
                return $this->response->setOutput(json_encode(['error' => $this->language->get('error_futures_bid_qty_not_exceed_total_quantity_available')]));
            }
            $contract_remain_num = $this->model_futures_agreement->getContractRemainQty($postData['contract_id']);
            // 判断合约剩余数量够不够
            if (($contract_remain_num - $postData['qty']) < 0) {
                return $this->response->setOutput(json_encode(['error' => $this->language->get('error_futures_bid_qty_not_exceed_total_quantity_available')]));
            }
            $deposit = bcmul($postData['price'], $contract['payment_ratio'] / 100, $precision) * $postData['qty'];
            // 判断合约的剩余保证金是否足够
            if (bccomp($deposit, $contract['available_balance']) == 1) {
                return $this->response->setOutput(json_encode(['error' => $this->language->get('error_futures_low_deposit')]));
            }

            //校验buyer可否购买该产品
            $product = $this->model_futures_agreement->checkProduct($contract['product_id']);

            if ($product && $contract) {
                $sellerInfo = $this->model_futures_agreement->getSellerInfo($contract['product_id']);
                $buyerId = $this->customer->getId();
                if ($sellerInfo) {

                    $postData['product_id'] = $contract['product_id'];
                    $postData['buyer_payment_ratio'] = $contract['payment_ratio'];
                    $postData['seller_payment_ratio'] = $contract['payment_ratio'];
                    $postData['expected_delivery_date'] = $contract['delivery_date'];
                    $data = [];
                    $data['delivery_type'] = $postData['delivery_type'];
                    // 判断是尾款交割还是现货交割
                    if ($postData['delivery_type'] == 1) {
                        if (empty($postData['is_bid'])) {
                            //region #31737 免税buyer 价格显示修改
                            $lastUnitVatPrice = app(ProductPriceRepository::class)
                                ->getProductActualPriceByBuyer($sellerInfo['customer_id'], customer()->getModel(), $contract['last_unit_price']);
                            //endregion

                            $afterDiscountPrice = app(MarketingDiscountRepository::class)->getPriceAfterDiscount($buyerId, $contract['product_id'], $lastUnitVatPrice, $postData['qty'], ProductTransactionType::FUTURE);
                            $discountInfo = app(MarketingDiscountRepository::class)->getMaxDiscount($buyerId, $contract['product_id'], $postData['qty'], ProductTransactionType::FUTURE);
                            $postData['discount'] = $discountInfo->discount ?? null; //产品折扣
                            $postData['price'] = $afterDiscountPrice;
                            $postData['discount_price'] = $lastUnitVatPrice - $afterDiscountPrice;
                        }

                        $perDeposit = sprintf($format, round($postData['price'] * $contract['payment_ratio'] / 100, $precision));
                        $data['last_purchase_num'] = $postData['qty'];
                        $data['last_unit_price'] = bcsub($postData['price'], $perDeposit, $precision);
                    } else {
                        // 用现货的20%,减去期货保证金支付比例
                        // 101306 期货二期 期货保证金支付比例大于现货的比例，则现货的支付比例为期货的支付比例
                        $marginPaymentRatio = MARGIN_PAYMENT_RATIO;
                        if ($postData['buyer_payment_ratio'] * 0.01 > $marginPaymentRatio) {
                            $marginPaymentRatio = $postData['buyer_payment_ratio'] * 0.01;
                        }
                        if (empty($postData['is_bid'])) {
                            //region #31737 免税buyer 价格显示修改
                            $marginUnitVatPrice = app(ProductPriceRepository::class)
                                ->getProductActualPriceByBuyer($sellerInfo['customer_id'], customer()->getModel(), $contract['margin_unit_price']);
                            //endregion

                            $afterDiscountPrice = app(MarketingDiscountRepository::class)->getPriceAfterDiscount($buyerId, $contract['product_id'], $marginUnitVatPrice, $postData['qty'], ProductTransactionType::FUTURE);
                            $discountInfo = app(MarketingDiscountRepository::class)->getMaxDiscount($buyerId, $contract['product_id'], $postData['qty'], ProductTransactionType::FUTURE);
                            $postData['discount'] = $discountInfo->discount ?? null; //产品折扣
                            $postData['price'] = $afterDiscountPrice;
                            $postData['discount_price'] = $marginUnitVatPrice - $afterDiscountPrice;
                        }

                        $perDeposit = sprintf($format, round($postData['price'] * $contract['payment_ratio'] / 100, $precision));
                        $data['margin_apply_num'] = $postData['qty'];
                        $data['margin_unit_price'] = $postData['price'];
                        $margin_deposit_paid_amount = sprintf($format, round($perDeposit * $data['margin_apply_num'], $precision));//已付定金总金额
                        $margin_unit_deposit = sprintf($format, round($data['margin_unit_price'] * $marginPaymentRatio, $precision));//现货定金单价
                        $data['margin_last_price'] = sprintf($format, round($data['margin_unit_price'] - $margin_unit_deposit, $precision));//现货尾款单价
                        $deposit_amount = sprintf($format, round($margin_unit_deposit * $data['margin_apply_num'], $precision));//转现货定金总金额
                        $data['margin_deposit_amount'] = sprintf($format, round($deposit_amount - $margin_deposit_paid_amount, $precision));//补足款
                        $margin_last_price_amount = sprintf($format, round($data['margin_last_price'] * $data['margin_apply_num'], $precision));
                        $data['margin_agreement_amount'] = sprintf($format, round(floatval($data['margin_deposit_amount']) + floatval($margin_last_price_amount), $precision));//现货协议总金额
                        $data['margin_days'] = 30;

                    }
                    $res = $this->model_futures_agreement->submitAgreement($postData, $data, $sellerInfo['customer_id'], $buyerId);
                    // 添加生成的期货头款到购物车
                    if (!$postData['is_bid'] && $res) {
                        $json['cart_id'] = $this->addToCart($res['product_id'], $res['agreement_id']);
                    }

                    if ($res) {
                        // 如果是bid协议，发送站内信
                        if ($postData['is_bid']) {
                            $this->model_futures_agreement->addFuturesAgreementCommunication($res['agreement_id'], 2, ['from' => 0, 'to' => $sellerInfo['customer_id'], 'country_id' => $this->customer->getCountryId()]);
                        }
                        $json['success'] = $this->language->get("text_add_success");
                    } else {
                        $json['error'] = 'Failed';
                    }
                } else {
                    $json['error'] = 'Failed';
                }
            } else {
                $json['error'] = $this->language->get("error_futures_seller");
            }

            $this->response->setOutput(json_encode($json));
        }

    }


    /**
     * 添加期货头款到购物车
     *
     */
    public function addToCart($product_id, $agreement_id)
    {
        $this->request->input->set('product_id', $product_id);
        $this->request->input->set('quantity', 1);
        $this->request->input->set('transaction_type', $agreement_id . '_3');
        $this->load->controller('checkout/cart/add');
        $this->load->model('futures/agreement');
        return $this->model_futures_agreement->getCartIdByproductId($this->customer->getId(), $product_id);
    }

    private function validatePostData($post_data)
    {
        $this->language->load('futures/template');

        if (!isset($post_data['agreement_id'])) {

            if (!isset($post_data['contract_id']) || empty($post_data['contract_id'])
                || !is_numeric($post_data['contract_id'])) {
                return ['status' => false, 'msg' => $this->language->get('error_contract_id')];
            }

            if (!isset($post_data['qty']) || empty($post_data['qty'])
                || !is_numeric($post_data['qty']) || $post_data['qty'] > 9999) {
                return ['status' => false, 'msg' => $this->language->get('error_futures_bid_qty')];
            }
            if (isset($post_data['is_bid']) && $post_data['is_bid']) {

                if (!isset($post_data['price']) || $post_data['price'] < 0) {
                    return ['status' => false, 'msg' => $this->language->get('error_futures_bid_price')];
                }

                $isJapan = JAPAN_COUNTRY_ID == $this->customer->getCountryId() ? 1 : 0;
                if (!$isJapan && !preg_match('/^[0-9]+(.[0-9]{1,2})?$/', $post_data['price'])) {
                    return ['status' => false, 'msg' => $this->language->get('error_futures_bid_price')];
                }
                if ($isJapan && !is_numeric($post_data['price'])) {
                    return ['status' => false, 'msg' => $this->language->get('error_futures_bid_price_japan')];
                }
            }


        } else {

            if (isset($post_data['qty']) && (!is_numeric($post_data['qty']) || $post_data['qty'] > 9999)) {
                return ['status' => false, 'msg' => $this->language->get('error_futures_bid_qty')];
            }

            if (isset($post_data['price'])) {
                if ($post_data['price'] < 0) {
                    return ['status' => false, 'msg' => $this->language->get('error_futures_bid_price')];
                }
                $isJapan = JAPAN_COUNTRY_ID == $this->customer->getCountryId() ? 1 : 0;
                if (!$isJapan && !preg_match('/^[0-9]+(.[0-9]{1,2})?$/', $post_data['price'])) {
                    return ['status' => false, 'msg' => $this->language->get('error_futures_bid_price')];
                }
                if ($isJapan && !is_numeric($post_data['price'])) {
                    return ['status' => false, 'msg' => $this->language->get('error_futures_bid_price_japan')];
                }
            }

        }

        return ['status' => true, 'msg' => 'success'];
    }

    /*
     * Bid List页 取消协议申请 buyer
     * */
    public function cancelAgreement()
    {
        $agreement_id = $this->request->input->getInt('agreement_id');
        $flag = false;
        $this->language->load('futures/agreement');
        $this->load->model('futures/agreement');
        $customer_id = $this->customer->getId();
        $operator    = $this->customer->getFirstName() . $this->customer->getLastName();
        $agreementInfo = $this->model_futures_agreement->getAgreementById($agreement_id);
        if ($agreement_id && $agreementInfo) {
            if ($agreementInfo->contract_id == 0) {
                //期货保证金一期
                $flag = $this->model_futures_agreement->cancelAgreement($agreementInfo);

                if ($flag) {
                    $idata = ['agreement_id' => intval($agreement_id),
                        'customer_id' => intval($this->customer->getId()),
                        'message' => $this->language->get('text_buyer_cancel_agreement'),];
                    $this->model_futures_agreement->addMessage($idata);

                    //给seller退定金
                    $this->model_futures_agreement->sellerMarginBack($agreement_id);
                    //下架尚未付款的期货头款商品
                    $this->model_futures_agreement->handleAdvanceProduct($agreement_id);
                    $this->response->success([], sprintf($this->language->get('text_cancel_success'), $agreementInfo->agreement_no));
                } else {
                    $this->response->failed($this->language->get('text_cancel_failed'));
                }
            } else {
                //期货保证金二期
                $flag = $this->model_futures_agreement->cancelAgreement($agreementInfo);
                if ($flag) {

                    //下架尚未付款的期货头款商品
                    $this->model_futures_agreement->handleAdvanceProduct($agreement_id);

                    $log_type = 0;
                    switch ($agreementInfo->agreement_status){
                        case 1://Applied -> Cancled
                            $log_type = 5;
                            break;
                        case 2://Pending -> Cancled
                            $log_type = 44;
                            break;
                        case 3://Approved -> Cancled
                            $log_type = 45;
                            break;
                    }

                    //记录操作日志、添加Message、发站内信
                    $info=[];
                    $info['delivery_status'] = null;
                    $info['apply_status'] = null;
                    $info['add_or_update'] = 'add';
                    $info['remark'] = $this->language->get('text_buyer_cancel_agreement');
                    $info['communication'] = false;//是否发站内信
                    $info['from'] = $agreementInfo->buyer_id;
                    $info['to'] = $agreementInfo->seller_id;
                    $info['country_id'] = $this->customer->getCountryId();
                    $info['status'] = null;//1 同意 0 拒绝
                    $info['communication_type'] = 5;//Buyer取消期货交易，向Seller发送取消期货协议的站内信；
                    $info['apply_type'] = null;

                    $log=[];
                    $log['info'] = [
                        'agreement_id' => $agreementInfo->id,
                        'customer_id' => $customer_id,
                        'type' => $log_type,
                        'operator' => $operator,
                    ];
                    $log['agreement_status'] = [$agreementInfo->agreement_status, 5];
                    $log['delivery_status']  = [$agreementInfo->delivery_status, $info['delivery_status']];
                    $this->model_futures_agreement->updateFutureAgreementAction(
                        $agreementInfo,
                        $customer_id,
                        $info,
                        $log
                    );


                    $this->model_futures_agreement->cancelAgreementAfter($agreementInfo);

                    $this->response->success([], sprintf($this->language->get('text_cancel_success'), $agreementInfo->agreement_no));
                } else {
                    $this->response->failed($this->language->get('text_cancel_failed'));
                }
            }
        } else {
            $this->response->failed($this->language->get('text_cancel_failed'));
        }
    }


    /*
     * Bid List页 忽略协议 buyer
     * */
    public function ignoreAgreement()
    {
        $agreement_id = $this->request->input->getInt('agreement_id');
        $agreement_no = trim($this->request->input->get('agreement_no', ''));
        $this->language->load('futures/agreement');
        $this->load->model('futures/agreement');

        $flag = false;
        $customer_id = $this->customer->getId();
        $operator    = $this->customer->getFirstName() . $this->customer->getLastName();
        $agreementInfo = $this->model_futures_agreement->getAgreementById($agreement_id);
        if ($agreement_id && $agreementInfo) {
            $flag = $this->model_futures_agreement->ignoreAgreement($agreementInfo);
        }

        if ($flag) {
            if($agreementInfo->contract_id != 0){
                //期货保证金二期

                $log_type = 0;
                switch ($agreementInfo->agreement_status){
                    case 4://Rejected
                        $log_type = 39;
                        break;
                    case 6://Time out
                        $log_type = 46;
                        break;
                }

                //记录操作日志、添加Message、发站内信
                $info=[];
                $info['delivery_status'] = null;
                $info['apply_status'] = null;
                $info['add_or_update'] = 'add';
                $info['remark'] = null;//是否记录Message
                $info['communication'] = false;//是否发站内信
                $info['from'] = $agreementInfo->buyer_id;
                $info['to'] = $agreementInfo->seller_id;
                $info['country_id'] = $this->customer->getCountryId();
                $info['status'] = null;//1 同意 0 拒绝
                $info['communication_type'] = null;
                $info['apply_type'] = null;

                $log=[];
                $log['info'] = [
                    'agreement_id' => $agreementInfo->id,
                    'customer_id' => $customer_id,
                    'type' => $log_type,
                    'operator' => $operator,
                ];
                $log['agreement_status'] = [$agreementInfo->agreement_status, $agreementInfo->agreement_status];
                $log['delivery_status']  = [$agreementInfo->delivery_status, $info['delivery_status']];
                $this->model_futures_agreement->updateFutureAgreementAction(
                    $agreementInfo,
                    $customer_id,
                    $info,
                    $log
                );
            }


            $this->response->success([], sprintf($this->language->get('text_ignore_success'), $agreement_no));
        } else {
            $this->response->failed();
        }
    }

    /*
     * Features Detail 协议详情页 buyer Applied状态下修改协议
     * */
    public function editAgreement()
    {
        $postData = $this->request->post;
        $validateResult = $this->validatePostData($postData);
        if (!$validateResult['status']) {
            $this->response->failed($validateResult['msg']);

        } else {
            $this->load->model('futures/agreement');
            $flag = $this->model_futures_agreement->editAgreement($postData);
            if ($flag) {
                $this->response->success([], 'The message is sent successfully.');
            } else {
                $this->response->failed('The status of agreement has been changed. This agreement cannot be edited.');
            }
        }
    }

    /*
     * Features Detail 协议详情页 buyer rejected、Time Out 状态下重新申请协议
     * */
    public function reapplyAgreement()
    {
        $postData = $this->request->post;
        $validateResult = $this->validatePostData($postData);
        if (!$validateResult['status']) {
            $this->response->failed($validateResult['msg']);

        } else {

            $this->language->load('futures/template');
            $this->load->model('futures/agreement');
            $this->load->model('futures/template');

            $flag = false;
            $errMsg = 'Failed';
            $info = $this->model_futures_agreement->getAgreementById($postData['agreement_id']);

            $isFutures = $this->model_futures_template->isFutures($info->product_id);
            //判断该商品是否可进行期货交易
            if ($isFutures) {
                if (JAPAN_COUNTRY_ID == $this->customer->getCountryId()) {
                    $format = '%d';
                    $precision = 0;
                } else {
                    $format = '%.2f';
                    $precision = 2;
                }
                //校验seller可否支付得起定金
                $perDeposit = sprintf($format, round($postData['price'] * $info->seller_payment_ratio / 100, $precision));
                $sellerDeposit = sprintf($format, round($perDeposit * $postData['qty'], $precision));
                $checkSeller = $this->model_futures_agreement->checkSellerDeposit($info->seller_id, $sellerDeposit, $info->product_id, $postData['qty']);
                if (!$checkSeller) {
                    $errMsg = $this->language->get("error_futures_seller");
                } else {
                    $flag = $this->model_futures_agreement->reapplyAgreement($postData);
                }
            } else {
                $errMsg = $this->language->get("error_futures_seller");
            }

            if ($flag) {
                $this->response->success([], 'successfully');
            } else {
                $this->response->failed($errMsg);
            }
        }
    }

    /*
     * buyer 拒绝履约
     * @version 期货一期
     * */
    public function terminated()
    {
        $agreement_id = $this->request->input->getInt('agreement_id');
        $con = db()->getConnection();
        try {
            $con->beginTransaction();
            $flag = false;
            if ($agreement_id) {
                $this->load->model('futures/agreement');
                $flag = $this->model_futures_agreement->terminated($agreement_id);
            }
            if ($flag) {
                //给seller退定金
                $this->model_futures_agreement->sellerMarginBack($agreement_id);
                //解除库存锁定  5-终止期货协议
                app(ProductLockRepository::class)->releaseFuturesLockQty($agreement_id, 5);
            }
            $con->commit();
        } catch (Exception $e) {
            $con->rollBack();
            $flag = false;
            Logger::error($e);
        }
        if ($flag) {
            $this->response->success([], 'Future goods agreement was successfully terminated.');
        } else {
            $this->response->failed();
        }
    }


    /*
     * Buyer下载
     * */
    public function downloadAgreementList()
    {

        $agreement_no = trim($this->request->query->get('agreement_no', ''));
        $sku = trim($this->request->query->get('item_code', ''));
        $agreement_status = $this->request->query->getInt('agreement_status');
        $delivery_status = $this->request->query->getInt('delivery_status');
        $status = $this->request->query->getInt('status');
        $store_name = trim($this->request->query->get('store_name', ''));
        $delivery_date_from = $this->request->query->get('delivery_date_from', '');
        $delivery_date_to = $this->request->query->get('delivery_date_to', '');
        $date_from = $this->request->query->get('date_from', '');
        $date_to = $this->request->query->get('date_to', '');

        $this->language->load('futures/agreement');
        $this->load->model('futures/agreement');
        $customerId = $this->customer->getId();
        $currency_code = $this->session->get('currency');
        $filterData = [
            'agreement_no' => $agreement_no,
            'sku' => $sku,
            'status' => $status,
            'agreement_status' => $agreement_status,
            'delivery_status' => $delivery_status,
            'store_name' => $store_name,
            'delivery_date_from' => $delivery_date_from,
            'delivery_date_to' => $delivery_date_to,
            'date_from' => $date_from,
            'date_to' => $date_to,
        ];
        $list = $this->model_futures_agreement->agreementListForBuyer($customerId, $filterData);

        $country = $this->session->get('country', 'USA');
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        $time = dateFormat($fromZone, $toZone, date("Ymd", time()), 'Ymd');
        $filename = 'Future Goods Bids' . $time . '.csv';
        $head = [
            $this->language->get('column_agreement_no'),
            $this->language->get('column_store'),
            $this->language->get('column_item_code'),
            $this->language->get('column_purchased_qty'),
            $this->language->get('column_agreement_qty'),
            $this->language->get('column_unit_price'),
            $this->language->get('column_amount_of_agreement'),
            $this->language->get('column_delivery_date'),
            $this->language->get('column_delivery_type'),
            $this->language->get('column_delivery_status'),
            $this->language->get('column_last_modified'),
            $this->language->get('column_agreement_status'),
        ];
        $line = [];
        foreach ($list['agreement_list'] as $key => $value) {
            $line[] = [
                "\t" . $value['agreement_no'],
                html_entity_decode($value['screenname']),
                "\t" . $value['sku'],
                $value['purchase_num_show'],
                $value['num'],
                $this->currency->format($value['unit_price'], $currency_code, false, true),
                $this->currency->format($value['amount'], $currency_code, false, true),
                "\t" . $value['show_delivery_date'],
                $value['delivery_type_name'],
                $value['delivery_status_name'],
                "\t" . $value['update_time'],
                $value['agreement_status_name'],
            ];
        }

        outputCsv($filename, $head, $line, $this->session);
    }


    /*
     * Features Detail 选择交割方式
     * */
    public function selectDelivery()
    {
        $postData = $this->request->post;
        $validateResult = $this->validateDeliveryData($postData);
        if ($validateResult['status']) {
            $this->load->model('futures/agreement');
            $flag = $this->model_futures_agreement->applyDelivery($postData);

            if ($flag) {
                $this->response->success([], 'successfully');
            } else {
                $this->response->failed();
            }
        } else {
            $this->response->failed($validateResult['msg']);
        }

    }

    /*
     * 校验交割数据
     * */
    private function validateDeliveryData($postData)
    {
        $agreementId = intval($postData['agreement_id']);
        $deliveryType = intval($postData['delivery_type']);
        $lastPurchaseNum = intval($postData['last_purchase_num']);
        $marginApplyNum = intval($postData['margin_apply_num']);
        $marginUnitPrice = $postData['margin_unit_price'];

        $this->load->model('futures/agreement');
        $this->load->language('futures/agreement');

        $info = $this->model_futures_agreement->getAgreementById($agreementId);
        if (empty($info) || !in_array($deliveryType, [1, 2, 3])) {
            return ['status' => false, 'msg' => 'parameter error'];
        }
        if (3 == $deliveryType && $lastPurchaseNum + $marginApplyNum != $info->num) {
            return ['status' => false, 'msg' => $this->language->get('tip_delivery_qty_count')];
        }
        if (1 == $deliveryType && $lastPurchaseNum != $info->num) {
            return ['status' => false, 'msg' => $this->language->get('tip_delivery_qty_1')];
        }
        if ($deliveryType > 1) {//转现货 或 组合交割
            if ($marginUnitPrice < 0) {
                return ['status' => false, 'msg' => $this->language->get('tip_delivery_price')];
            }
            $isJapan = JAPAN_COUNTRY_ID == $this->customer->getCountryId() ? 1 : 0;
            if (!$isJapan && !preg_match('/^[0-9]+(.[0-9]{1,2})?$/', $marginUnitPrice)) {
                return ['status' => false, 'msg' => $this->language->get('tip_delivery_price')];
            }
            if ($isJapan && !is_numeric($marginUnitPrice)) {
                return ['status' => false, 'msg' => $this->language->get('tip_delivery_price_japan')];
            }
            $product = $this->model_futures_agreement->getProductById($info->product_id);
            if ($product->price < $marginUnitPrice) {
                return ['status' => false, 'msg' => $this->language->get('tip_delivery_price_too_much')];
            }
            if ($marginUnitPrice < $info->unit_price) {
                return ['status' => false, 'msg' => $this->language->get('tip_delivery_margin_price')];
            }
        }

        return ['status' => true, 'msg' => 'success'];
    }


    /**
     * seller的bid list
     * @throws Exception
     */
    public function sellerBidList()
    {
        $this->language->load('account/product_quotes/wk_product_quotes');
        $this->language->load('futures/agreement');
        $this->load->model('futures/agreement');
        $data = $this->request->get;
        $customerId = $this->customer->getId();
        $data['sort_update_time'] = $this->request->get('sort_update_time','desc');
        $data['column'] = $this->request->get('column','agreement_id');
        $data['sort_agreement_id'] = $this->request->get('sort_agreement_id','desc');
        $data['page'] = get_value_or_default($this->request->get, 'page', 1);
        $data['page_limit'] = get_value_or_default($this->request->request, 'page_limit', 15);
        $list = $this->model_futures_agreement->agreementListForSeller($customerId, $data);
        $data['page_view'] = $this->load->controller('common/pagination', $list);
        $data['agreement_list'] = $list['agreement_list'];
        $data['agreement_status_list'] = ModelFuturesAgreement::AGREEMENT_STATUS;
        unset($data['agreement_status_list'][8]);
        $data['delivery_status_list'] = $this->model_futures_agreement->deliveryStatusList();
        unset($data['delivery_status_list'][7]);
        //等待seller处理的协议
        $data['to_be_processed_count'] = $this->model_futures_agreement->sellerBeProcessedCount($customerId);
        //等待入仓
        $data['to_be_delivered_count'] = $this->model_futures_agreement->sellerBeDeliveredCount($customerId, 1);
        //待审批的协议
        //等待Buyer支付尾款或支付转现货保证金的协议
        $data['to_be_paid_count'] = $this->model_futures_agreement->sellerBeDeliveredCount($customerId, 6);
        //$data['to_be_approval_count'] = $this->model_futures_agreement->sellerBeDeliveredCount($customerId, 5);
        // 即将到交货日期前七天和即将超时的协议1小时
        $data['to_be_expired_count'] = $this->model_futures_agreement->sellerAgreementExpiredCount($customerId);
        // 是否显示云送仓提醒
        $data['is_show_cwf_notice'] = app(SellerRepository::class)->isShowCwfNotice();

        $this->response->setOutput($this->load->view('customerpartner/future/bid_new_list', $data));
    }

    /**
     * Bid List页 期货保证金协议详情页 seller
     * @throws Exception
     * @version 期货保证金二期，期货协议详情
     */
    public function sellerFuturesBidDetail()
    {
        $agreement_id = intval($this->request->get['id']);
        $this->load->model('futures/agreement');
        // 加载页面布局
        $data = $this->framework($agreement_id, 2);
        $data['agreement'] = $this->model_futures_agreement->getAgreementById($agreement_id);

        if (!$data['agreement']->contract_id) {//实际是一期协议，则跳转至一期详情页
            return $this->sellerBidDetail();
        }
        $data['id'] = $agreement_id;
        $this->response->setOutput($this->load->view('customerpartner/future/seller_futures_bid_detail', $data));
    }


    /**
     * [sellerFuturesAgreementDetail description] 期货二期期货协议tab页
     * @throws Exception
     */
    public function sellerFuturesAgreementDetail()
    {
        $agreement_id = intval($this->request->get['id']);
        $currency_code = $this->session->get('currency');
        $country = $this->session->get('country', 'USA');
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        $seller_id = $this->customer->getId();
        $this->load->model('futures/agreement');
        $this->load->model('catalog/product');
        $this->load->model('common/product');
        // 加载页面布局
        $data = $this->framework($agreement_id, 2);
        // 打开页面变成pending状态
        $this->model_futures_agreement->checkAgreementStatus($agreement_id);
        $data['agreement'] = $this->model_futures_agreement->getAgreementById($agreement_id);
        if ($data['agreement']->delivery_date) {
            $data['agreement']->show_delivery_date = dateFormat($fromZone, $toZone, $data['agreement']->delivery_date, 'Y-m-d');
        } elseif ($data['agreement']->expected_delivery_date) {
            $data['agreement']->show_delivery_date = $data['agreement']->expected_delivery_date;
        } else {
            $data['agreement']->show_delivery_date = 'N/A';
        }
        $data['agreement']->tag = $this->model_catalog_product->getProductTagHtmlForThumb($data['agreement']->product_id);
        // 获取消息
        $data['messages'] = $this->handleMessage($this->model_futures_agreement->getApprovalMessages($agreement_id));
        // 获取buyer的信息
        $data['buyer'] = $this->model_message_message->getCustomerInfoById($data['agreement']->buyer_id);
        $data['ex_vat'] = VATToolTipWidget::widget(['customer' => Customer::query()->find($data['agreement']->buyer_id), 'is_show_vat' => true])->render();
        $data['username'] = addslashes($data['buyer']->username);
        $data['buyer_type'] = $this->model_futures_agreement->isCollectionFromDomicile($data['agreement']->buyer_id);
        $data['img_tips'] = $data['buyer_type'] ? 'Pick up Buyer' : 'Dropshiping Buyer';
        $data['is_japan'] = JAPAN_COUNTRY_ID == $this->customer->getCountryId() ? 1 : 0;
        //协议定金
        if ($data['is_japan']) {
            $data['all_earnest'] = round($data['agreement']->unit_price * $data['agreement']->buyer_payment_ratio / 100) * $data['agreement']->num;
        } else {
            $data['all_earnest'] = sprintf('%.2f', round($data['agreement']->unit_price * $data['agreement']->buyer_payment_ratio / 100, 2) * $data['agreement']->num);
        }

        if ($data['is_japan']) {
            $data['all_seller_earnest'] = round($data['agreement']->unit_price * $data['agreement']->seller_payment_ratio / 100) * $data['agreement']->num;
        } else {
            $data['all_seller_earnest'] = sprintf('%.2f', round($data['agreement']->unit_price * $data['agreement']->seller_payment_ratio / 100, 2) * $data['agreement']->num);
        }
        // 协议单价
        $data['unit_price_show'] = $this->currency->format($data['agreement']->unit_price, $currency_code);
        $data['all_earnest_show'] = $this->currency->format($data['all_earnest'], $currency_code);
        // buyer 支付的协议定金
        // 获取状态 name 和 color
        $data['agreement_status_info'] = ModelFuturesAgreement::AGREEMENT_STATUS[$data['agreement']->agreement_status];
        $data['delivery_status_info'] = ModelFuturesAgreement::DELIVERY_STATUS[$data['agreement']->delivery_status] ?? ['name' => 'N/A', 'color' => 'grey'];
        $data['customerId'] = $seller_id;
        // 防止重复提交
        $data['csrf_token'] = time() . mt_rand(100000, 999999);
        // 防止把期货一期的去掉了
        session()->set('futures_csrf_token', $data['csrf_token']);
        // 一件代发buyer 报警
        $need_alarm = 0;
        if (
            $this->customer->isNonInnerAccount()
            && !in_array($data['agreement']->customer_group_id, COLLECTION_FROM_DOMICILE)
        ) {
            $alarm_price = $this->model_common_product->getAlarmPrice($data['agreement']->product_id);
            if (bccomp($data['agreement']->unit_price, $alarm_price, 4) === -1) {
                $need_alarm = 1;
            }
        }
        $data['need_alarm'] = $need_alarm;
        //  验证是否协议金额是否足够
        $data['amount_status'] = ($this->model_futures_agreement->verifyAmountIsEnough($data['agreement'],$data['is_japan'])) ? '1' : '0';
        // 是否显示云送仓提醒
        $data['is_show_cwf_notice'] = app(SellerRepository::class)->isShowCwfNotice();

        $this->response->setOutput($this->load->view('customerpartner/future/seller_futures_agreement_detail', $data));
    }

    /**
     * [sellerFuturesDepositOrderDetail description] 期货二期期货保证金tab页
     * @throws Exception
     */
    public function sellerFuturesDepositOrderDetail()
    {
        $agreement_id = intval($this->request->get['id']);
        // 获取
        $this->load->model('futures/agreement');
        $advance_product_id = $this->model_futures_agreement->getFuturesAdvanceProductId($agreement_id);
        // 获取期货保证金订单详情
        $data['purchase_order_info'] = $this->model_futures_agreement->getPurchaseOrderInfoByProductId($advance_product_id, $this->customer->getCountryId());
        $this->response->setOutput($this->load->view('customerpartner/future/seller_futures_deposit_order_detail', $data));
    }

    /**
     * [sellerFuturesTransactionModeDetail description] 期货二期入仓tab页1
     */
    public function sellerFuturesTransactionModeDetail()
    {
        $agreement_id = intval($this->request->get['id']);
        $this->load->model('futures/agreement');
        $country_id = $this->customer->getCountryId();
        $data['customerId'] = $this->customer->getId();
        $data['agreement'] = $this->model_futures_agreement->getFutureTransactionModeDetail($agreement_id, $country_id);
        $data['left_days'] = $this->model_futures_agreement->getLeftDay($data['agreement']);
        $data['apply_exist'] = app(AgreementApplyepository::class)->isAgreementApplyExist($agreement_id);
        // 获取消息
        $data['messages'] = $this->handleMessage($this->model_futures_agreement->getApplyMessages($agreement_id));
        $this->response->setOutput($this->load->view('customerpartner/future/seller_futures_transaction_mode_detail', $data));
    }

    /**
     * [sellerFuturesPurchaseRecordDetail description] 期货二期交割tab页
     */
    public function sellerFuturesPurchaseRecordDetail()
    {
        $agreement_id = intval($this->request->get['id']);
        $this->load->model('futures/agreement');
        $country_id = $this->customer->getCountryId();
        // 获取交割数据的详情
        $data['record'] = $this->model_futures_agreement->getSellerFuturesPurchaseRecordDetailMainById($agreement_id);
        $data['is_europe'] = in_array($country_id, EUROPE_COUNTRY_ID);
        $data['agreement_id'] = $agreement_id;
        $this->response->setOutput($this->load->view('customerpartner/future/seller_futures_purchase_record_detail', $data));
    }

    public function sellerFuturesApprovalInfo()
    {
        $agreement_id = get_value_or_default($this->request->get, 'id', 0);
        $action_id = get_value_or_default($this->request->get, 'action_id', 0);
        //查询协议终止的内容
        $this->load->model('futures/agreement');
        $data['agreement'] = $this->model_futures_agreement->getAgreementById($agreement_id);
        $data['apply'] = $this->model_futures_agreement->getLastCustomerApplyInfo($data['agreement']->buyer_id, $agreement_id, [0]);
        $data['action_id'] = $action_id;
        $this->response->setOutput($this->load->view('customerpartner/future/seller_futures_approval_info', $data));
    }


    /**
     * bid detail 页面
     * 期货保证金协议详情
     * @version 期货一期
     * @throws Exception
     */
    public function sellerBidDetail()
    {
        $agreement_id = intval($this->request->get['id']);
        $sell_id = $this->customer->getId();
        $this->load->model('futures/agreement');
        $this->load->model('common/product');
        // 加载页面布局
        $data = $this->framework($agreement_id, 1);
        $data['agreement'] = $this->model_futures_agreement->getAgreementById($agreement_id);
        if($data['agreement']->contract_id){//实际是二期协议，则跳转至二期详情页
            return $this->sellerFuturesBidDetail();
        }
        // 距离交付日期还剩几天话术
        $data['days_tip'] = $this->getDeliveryDaysTip($data['agreement']->expected_delivery_date);
        $data['customerId'] = $sell_id;
        $data['is_partner'] = $this->customer->isPartner();
        $data['liquidation_day'] = $this->config->get('liquidation_day');
        // 获取授信额度余额
        $data['credit'] = credit::getLineOfCredit($sell_id);
        // 获取剩余的抵押物：有效提单 需求号101222要求去掉有效提单抵押
        //$data['expected_qty'] = $this->getReceiptsOrder($data['agreement']->product_id);
        // 获取剩余的现货抵押物货值
        //        $data['product_amount'] = $this->getProductAmountRatio($sell_id);
        // 获取seller的应收款
        $data['seller_bill_total'] = $this->getSellerBillTotal($sell_id);
        // 获取消息
        $data['messages'] = $this->handleMessage($this->model_futures_agreement->getMessages($agreement_id));
        // 获取seller期货保证金支付方式
        if ($data['agreement']->agreement_status > 2) {
            $data['margin_pay'] = ModelFuturesAgreement::SELLER_MARGIN_PAY[$this->model_futures_agreement->getMarginPayRecord($agreement_id)->type];
        }
        // 获取buyer用户信息
        $this->load->model('message/message');
        $data['buyer'] = $this->model_message_message->getCustomerInfoById($data['agreement']->buyer_id);
        $data['ex_vat'] = VATToolTipWidget::widget(['customer' => Customer::query()->find($data['agreement']->buyer_id), 'is_show_vat' => true])->render();
        $data['buyer_type'] = $this->model_futures_agreement->isCollectionFromDomicile($data['agreement']->buyer_id);
        // 获取产品信息  打包费按照buyer的类型拆分 2020-06-30 16:34:44 by lester.you
        $data['product'] = $this->model_futures_agreement->getProductAndNewPackageFeeById($data['agreement']->product_id, $data['buyer_type']);
        // 获取协议显示状态
        $data['status'] = $this->getAgreementStatus($data['agreement']->agreement_status, $data['agreement']->delivery_status);
        // 获取协议的数量和价格是否在模板范围的状态
        $data['is_in_template'] = $this->isAgreementIntemplate($data['agreement']->product_id, $data['agreement']);
        $data['is_japan'] = JAPAN_COUNTRY_ID == $this->customer->getCountryId() ? 1 : 0;
        $data['is_usa'] = AMERICAN_COUNTRY_ID == $this->customer->getCountryId() ? 1 : 0;
        $data['account_type'] = $this->customer->getAccountType();
        $data['date'] = date('Y-m-d H:i:s');
        $data['is_outer'] = $this->customer->isNonInnerAccount() ? 1 : 0;
        $data['alarm_price'] = $this->model_common_product->getAlarmPrice((int)$data['agreement']->product_id);
        // 一件代发buyer 报警
        $need_alarm = 0;
        if (
            $this->customer->isNonInnerAccount()
            && !in_array($data['agreement']->customer_group_id, COLLECTION_FROM_DOMICILE)
        ) {
            $alarm_price = $this->model_common_product->getAlarmPrice($data['agreement']->product_id);
            if (bccomp($data['agreement']->unit_price, $alarm_price, 4) === -1) {
                $need_alarm = 1;
            }
        }
        $data['need_alarm'] = $need_alarm;
        // 获取现货协议编码
        if (ModelFuturesAgreement::DELIVERY_TYPE_FUTURES != $data['agreement']->delivery_type && $data['agreement']->margin_agreement_id) {
            $data['margin_agreement_code'] = $this->model_futures_agreement->getMarginCode($data['agreement']->margin_agreement_id);
        }
        if ($data['is_japan']) {
            $data['unit_earnest'] = round($data['agreement']->unit_price * $data['agreement']->seller_payment_ratio / 100);
        } else {
            $data['unit_earnest'] = sprintf('%.2f', round($data['agreement']->unit_price * $data['agreement']->seller_payment_ratio / 100, 2));
        }
        $this->load->model('catalog/information');
        $information_info = $this->model_catalog_information->getInformation($this->config->get('futures_information_id_seller'));
        if ($information_info) {
            $data['clause_url'] = $this->url->link('information/information', 'information_id=' . $information_info['information_id'], true);
            $data['clause_title'] = $information_info['title'];
        }
        // 防止重复提交
        $data['csrf_token'] = time() . mt_rand(100000, 999999);
        session()->set('csrf_token', $data['csrf_token']);
        // 是否显示云送仓提醒
        $data['is_show_cwf_notice'] = app(SellerRepository::class)->isShowCwfNotice();

        $this->response->setOutput($this->load->view('customerpartner/future/bid_detail', $data));
    }


    public function framework($agreement_id, $version=1)
    {
        $this->language->load('futures/agreement');
        $this->document->setTitle($this->language->get('text_detail_title'));
        $breadcrumbLast = [
            1 => $this->url->to(['account/product_quotes/futures/sellerBidDetail', 'id' => $agreement_id]),//Seller页面 期货一期的详情
            2 => $this->url->to(['account/product_quotes/futures/sellerFuturesBidDetail', 'id' => $agreement_id]),//Seller页面 期货二期的详情
        ];
        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/home'),
                'separator' => false
            ],
            [
                'text' => $this->language->get('column_seller_center'),
                'href' => $this->url->link('customerpartner/seller_center/index', '', true),
                'separator' => $this->language->get('text_separator')
            ],
            [
                'text' => $this->language->get('column_product_bidding'),
                'href' => 'javascript:void(0);',
                'separator' => $this->language->get('text_separator')
            ],
            [
                'text' => $this->language->get('column_bid_list'),
                'href' => $this->url->to(['account/customerpartner/wk_quotes_admin','tab'=> 'futures' ]),
                'separator' => $this->language->get('text_separator')
            ],
            [
                'text' => $this->language->get('column_future_detail'),
                'href' => $breadcrumbLast[$version],
                'separator' => $this->language->get('text_separator')
            ]
        ];
        $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');
        $data['footer'] = $this->load->controller('account/customerpartner/footer');
        $data['header'] = $this->load->controller('account/customerpartner/header');
        return $data;
    }

    public function getDeliveryDaysTip($end_date)
    {
        if ($end_date) {
            $start_date = new DateTime(date('Y-m-d H:i:s'));
            $country = session('country', 'USA');
            $countryZone = CountryHelper::getTimezoneByCode($country);
            $usaZone = CountryHelper::getTimezoneByCode('USA');
            $expected_delivery_date = dateFormat($countryZone, $usaZone, $end_date . ' 23:59:59');
            $end_date = new DateTime($expected_delivery_date);
            $days = $start_date <= $end_date ? $end_date->diff($start_date)->days + 1 : 0;
            if ($days <= 7 && $days > 0) {
                return sprintf($this->language->get('tip_in_delivery_days'), $days);
            } elseif ($days == 0) {
                return $this->customer->isPartner() ? $this->language->get('tip_out_delivery_days_seller') : $this->language->get('tip_out_delivery_days_buyer');
            }
        }
        return null;
    }

    /**
     * Seller下载bid
     * @throws Exception
     */
    public function downloadFutureBid()
    {
        $this->load->model('futures/agreement');
        $customerId = $this->customer->getId();
        $data = $this->request->get;
        $data['sort_update_time'] = $this->request->get('sort_update_time','desc');
        $data['column'] = $this->request->get('column','agreement_id');
        $data['sort_agreement_id'] = $this->request->get('sort_agreement_id','desc');
        $list = $this->model_futures_agreement->agreementListForSeller($customerId, $data);
        $currency_code = $this->session->get('currency');
        //12591 B2B记录各国别用户的操作时间
        $country = session('country', 'USA');
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        $time = dateFormat($fromZone, $toZone, date("Ymd", time()), 'Ymd');
        //12591 end
        $filename = 'FutureBid' . $time . '.csv';
        $head = [
            'Agreement ID',
            'Item Code/MPN',
            'Name',
            'Purchased Quantity',
            'Agreement Quantity',
            'Unit Price of Agreement',
            'Agreement Amount',
            'Delivery Date',
            'Delivery Methods',
            'Delivery Status',
            'Last Modified',
            'Agreement Status',
        ];
        $line = [];
        foreach ($list['agreement_list'] as $value) {
            $line[] = [
                "\t" . $value['agreement_no'],
                $value['sku'] . "({$value['mpn']})",
                "\t" . $value['nickname'] . "(".$value['user_number'].")",
                $value['purchase_num_show'],
                $value['num'],
                $this->currency->format($value['unit_price'], $currency_code, false, true),
                $this->currency->format($value['amount'], $currency_code, false, true),
                "\t" . $value['show_delivery_date'],
                $value['delivery_type_name'],
                $value['delivery_status_name'],
                "\t" . $value['update_time'],
                $value['agreement_status_name'],
            ];
        }
        //12591 B2B记录各国别用户的操作时间
        outputCsv($filename, $head, $line, $this->session);
        //12591 end
    }

    /**
     * 协议的数量和价格是否在模板范围内
     * @param int $product_id
     * @param $agreement
     * @return int
     * @throws Exception
     */
    protected function isAgreementIntemplate($product_id, $agreement)
    {
        $this->load->model('futures/template');
        $template = $this->model_futures_template->getFuturesTemplateForProduct($product_id);
        $status = 0;
        if (!empty($template['template_list'])) {
            foreach ($template['template_list'] as $item) {
                // 判断协议数量不在模板内
                if ($item['min_num'] > $agreement->num || $item['max_num'] < $agreement->num) {
                    $status = 1;
                } else {
                    // 判断协议价不等于模板价
                    if ($item['exclusive_price'] != $agreement->unit_price) {
                        $status = 2;
                    }
                    break;
                }
            }
        }
        return $status;
    }


    /**
     * 获取期货协议状态的显示文字
     * @param $agreement_status
     * @param $delivery_status
     * @return mixed
     */
    protected function getAgreementStatus($agreement_status, $delivery_status)
    {
        if ($delivery_status) {
            return ModelFuturesAgreement::DELIVERY_STATUS[$delivery_status]['name'];
        }
        return ModelFuturesAgreement::AGREEMENT_STATUS[$agreement_status]['name'];
    }


    /**
     * 获取剩余的抵押物：有效提单
     * @param int $product_id
     * @return mixed
     * @throws Exception
     */
    protected function getReceiptsOrder($product_id)
    {
        $this->load->model('futures/template');
        $expected_qty = $this->model_futures_template->getExpectedQty($product_id);
        if ($expected_qty) {
            $used = $this->model_futures_agreement->getMarginPayRecordProductSum($product_id);
            return $expected_qty - $used;
        }
        return 0;
    }

    /**
     * 获取seller应收款余额
     * @param int $sell_id
     * @return int
     */
    protected function getSellerBillTotal($sell_id)
    {
        $total = $this->model_futures_agreement->getSellerBill($sell_id);
        if ($total) {
            $sum = $this->model_futures_agreement->getMarginPayRecordSum($sell_id, 3);
            return $total - $sum;
        }
        return 0;
    }

    /**
     * 获取剩余的现货抵押物货值
     * @param int $sell_id
     * @return mixed
     * @throws Exception
     */
    protected function getProductAmountRatio($sell_id)
    {
        $this->load->model('account/customerpartner/estimate');
        $amount = $this->model_account_customerpartner_estimate->getEstimatedAmount($sell_id);
        $sum = $this->model_futures_agreement->getMarginPayRecordSum($sell_id, 4);
        return $amount - $sum;
    }

    /**
     * 处理消息
     * @param $messages
     * @return mixed
     * @throws Exception
     */
    private function handleMessage($messages)
    {
        if ($messages) {
            $this->load->model('message/message');
            foreach ($messages as $v) {
                if ($this->customer->isPartner()) {
                    $v->customer = $this->model_message_message->getCustomerInfoById($v->customer_id);
                } else {
                    $v->customer = $this->model_message_message->getCustomerPartnerInfoById($v->customer_id);
                }
                if ($v->apply_id) {
                    $v->files = app(AgreementFileRepository::class)->getFilesByMessageId($v->id);
                }
            }
        }
        return $messages;
    }

    private function buyerHandleMessage($messages)
    {
        if ($messages) {
            $this->load->model('message/message');
            foreach ($messages as $v) {
                if ($this->customer->isPartner()) {
                    $v->customer = $this->model_message_message->getCustomerInfoById($v->customer_id);
                } else {
                    if($v->apply_type == 1){
                        $v->customer = $this->model_message_message->getCustomerPartnerInfoById($v->a_customer_id);
                    } else {
                        $v->customer = $this->model_message_message->getCustomerPartnerInfoById($v->customer_id);
                    }
                }
            }
        }
        return $messages;
    }

    public function sellerFutureUpdateApply()
    {
        $this->load->model('futures/agreement');
        $post = $this->request->post;
        $record = [
            'update_time' => date('Y-m-d H:i:s'),
            'is_read' => 1,
        ];
        $this->model_futures_agreement->updateFutureApply($post['apply_id'], $record);
        $this->response->success([], 'Operation succeeded.');
    }

    public function sellerFutureUpdateDelivery()
    {
        $this->load->model('futures/agreement');
        $post = $this->request->post;
        $record = [
            'update_time' => date('Y-m-d H:i:s'),
            'cancel_appeal_apply' => 1,
        ];
        $this->model_futures_agreement->updateDelivery($post['agreement_id'], $record);
        $this->response->success([], 'Operation succeeded.');
    }

    public function sellerFuturesProcessApply()
    {
        $this->load->model('futures/agreement');
        $this->language->load('futures/agreement');
        $post = $this->request->post;
        $customer_id = $this->customer->getId();
        $agreement = $this->model_futures_agreement->getAgreementById($post['agreement_id']);
        $this->checkFuturesAgreement($agreement, $post, $customer_id);
        $msg = '';
        $operator = $this->customer->getFirstName() . $this->customer->getLastName();
        try {
            $this->orm->getConnection()->beginTransaction();
            $log['info'] = [
                'agreement_id' => $agreement->id,
                'customer_id' => $customer_id,
                'operator' => $operator,
            ];
            $info['delivery_status'] = null;
            $info['apply_type'] = $post['apply_status'];
            $info['add_or_update'] = 'add';
            $log['agreement_status'] = [$agreement->agreement_status, $agreement->agreement_status];
            $log['delivery_status'] = [$agreement->delivery_status, $agreement->delivery_status];
            switch ($post['apply_status']) {
                case 1:
                    //提前交付 -> 平台审批 apply表加一条数据 delivery 表 update time
                    if(trim($post['remark']) == ''){
                        $post['remark'] = 'N/A.';
                    }
                    $info['remark'] = sprintf($this->language->get('text_future_seller_apply_early_delivery_remark'), $post['remark']);
                    $log['info']['type'] = 9; // 提前交付
                    $this->model_futures_agreement->updateFutureAgreementAction(
                        $agreement,
                        $customer_id,
                        $info,
                        $log
                    );
                    // 如果有申诉的申请，驳回申述申请
                    $this->model_futures_agreement->rejectSellerAppeal($agreement);
                    $msg = $this->language->get('text_future_seller_apply_early_delivery_msg');
                    break;
                case 2:
                    //提前取消交付 -> buyer 审批 apply表加一条数据
                    //取消交付 seller 取消交付
                    //赔偿buyer的钱
                    break;
                case 3:
                    // buyer协商终止
                    break;
                case 4:
                    // 申诉
                    break;
                case 5:
                    //正常交付 尾款交割 | 转现货
                    $record = [
                        'agreement_id' => $agreement->id,
                        'customer_id' => $customer_id,
                        'apply_type' => 5,
                        'status' => 1,
                        //'remark'      => $post['remark'],
                    ];
                    $msg =  sprintf($this->language->get('text_future_seller_apply_delivery_msg'), $agreement->agreement_no);
                    $apply_id = $this->model_futures_agreement->addFutureApply($record);
                    if(!trim($post['remark'])){
                        $post['remark'] = sprintf($this->language->get('text_future_seller_apply_delivery_remark'), $agreement->num,$agreement->agreement_no);
                    }
                    $message = [
                        'agreement_id' => $post['agreement_id'],
                        'customer_id' => $customer_id,
                        'apply_id' => $apply_id,
                        'message' => $post['remark'],
                    ];
                    $this->model_futures_agreement->addMessage($message);
                    $data = [
                        'update_time' => date('Y-m-d H:i:s'),
                        'delivery_status' => 6,  // To be paid
                        'confirm_delivery_date' => date('Y-m-d H:i:s'),
                        'delivery_date' => date('Y-m-d H:i:s'),
                    ];
                    $this->load->model('common/product');
                    if (
                    !$this->model_common_product->checkProductQtyIsAvailable(
                        (int)$agreement->product_id,
                        (int)$agreement->num)
                    ) {
                        throw new Exception('Low stock quantity.');
                    }
                    // 如果是转现货交割则生成现货协议和现货头款产品
                    if (in_array($agreement->delivery_type, [2, 3]) && !$agreement->margin_agreement_id) {
                        // 生成现货协议
                        $margin_agreement = $this->model_futures_agreement->addNewMarginAgreement($agreement, $this->customer->getCountryId());
                        // 生成现货头款产品
                        $product_id_new = $this->model_futures_agreement->copyMarginProduct($margin_agreement, 1);
                        // 创建现货保证金记录
                        $this->addMarginProcess($margin_agreement, $product_id_new);
                        // 更新期货交割表
                        $data['margin_agreement_id'] = $margin_agreement['agreement_id'];
                        $this->model_futures_agreement->updateDelivery($agreement->id, $data);
                        $this->load->model('catalog/futures_product_lock');
                        $this->model_catalog_futures_product_lock->TailIn($agreement->id, $agreement->num, $agreement->id, 0);
                        $this->model_catalog_futures_product_lock->TailOut($agreement->id, $agreement->num, $agreement->id, 6);
                        $orderId = $this->model_futures_agreement->autoFutureToMarginCompleted($agreement->id);

                    } else {
                        // 期货的只要校验库存已经锁库存就可以了
                        $this->load->model('catalog/futures_product_lock');
                        $this->model_catalog_futures_product_lock->TailIn($agreement->id, $agreement->num, $agreement->id, 0);
                        // 需要更新delivery表中的关于期货部分的数量
                        $this->model_futures_agreement->updateDelivery($agreement->id, $data);
                    }
                    $communication_info['from'] = $agreement->seller_id;
                    $communication_info['to'] = $agreement->buyer_id;
                    $communication_info['country_id'] = $this->customer->getCountryId();
                    $communication_info['status'] = 1;
                    $communication_info['communication_type'] = 9;
                    $communication_info['apply_type'] = 5;

                    $this->model_futures_agreement->addFuturesAgreementCommunication($agreement->id,9,$communication_info);
                    $log = [
                        'agreement_id' => $agreement->id,
                        'customer_id' => $customer_id,
                        'type' => 13,
                        'operator' => $operator,
                    ];
                    $this->model_futures_agreement->addAgreementLog($log,
                        [$agreement->agreement_status, $agreement->agreement_status],
                        [$agreement->delivery_status, $data['delivery_status']]
                    );
                    break;
            }
            $this->orm->getConnection()->commit();
            if (isset($orderId)) {
                $this->model_futures_agreement->autoFutureToMarginCompletedAfterCommit($orderId);
            }
            $this->response->success([], $msg);
        } catch (Exception $e) {
            $this->orm->getConnection()->rollBack();
            $this->response->failed($e->getMessage());
        }
    }

    // seller申诉处理
    public function doSellerAppeal()
    {
        $this->language->load('futures/agreement');
        $this->load->model('futures/agreement');
        $customerId = $this->customer->getId();
        $post = $this->request->post();
        $post['agreement_id'] = explode(',', $post['agreement_id']);
        // 校验文件
        $validator = $this->request->validate([
            'files' => 'array|min:1|max:5',
            'files.*' => 'file|max:20480|extension:jpg,jpeg,png,pdf,PDF',
        ]);
        if ($validator->fails()) {
            return $this->jsonFailed($validator->errors()->first());
        }
        $res = false;
        foreach ($post['agreement_id'] as $agreementId) {
            $agreement = $this->model_futures_agreement->getAgreementById($agreementId);
            if (!$agreement) {
                return $this->jsonFailed('The agreement not found');
            }
            $day = $this->model_futures_agreement->getLeftDay($agreement);
            if ($day == 1) {
                return $this->jsonFailed($this->language->get('text_current_expired'));
            }
            if ($agreement->delivery_status != FuturesMarginDeliveryStatus::TO_BE_DELIVERED) {
                return $this->jsonFailed($this->language->get('text_seller_appeal_exp'));
            }
        }
        foreach ($post['agreement_id'] as $agreementId) {
            $res = app(AgreementApply::class)->addSellerAppeal($agreementId, $customerId, $post, $this->request->file('files'));
        }
        if (!$res) {
            return $this->jsonFailed($this->language->get('text_seller_appeal_failed'));
        }
        return $this->jsonSuccess($this->language->get('text_seller_appeal_success'));
    }

    // seller申诉view
    public function sellerFuturesAppeal()
    {
        return $this->render('account/product_quotes/futures/appeal');
    }

    public function checkFuturesAgreementNum()
    {
        $this->load->model('common/product');
        $this->load->model('futures/agreement');
        $agreement_id = $this->request->input->get('agreement_id');
        $agreement = $this->model_futures_agreement->getAgreementById($agreement_id);
        $ret = $this->model_common_product->checkProductQtyIsAvailable(intval($agreement->product_id), intval($agreement->num));
        if($ret){
            return $this->response->success([],$ret);
        }
        return $this->response->failed($ret);
    }

    // 下载文件
    public function downloadFile()
    {
        $id = $this->request->get('id');
        $file = FuturesAgreementFile::query()
            ->find($id);
        $storage = StorageCloud::root();
        if (!$storage->fileExists($file->file_path)) {
            return $this->redirect('error/not_found');
        }
        return $storage->browserDownload($file->file_path, $file->file_name);
    }

    /**
     * 同一个产品的多个协议判断是否满足库存
     * @param ModelFuturesAgreement $modelFuturesAgreement
     * @param ModelCommonProduct $modelCommonProduct
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws Exception
     */
    public function checkFuturesAgreementsNum(ModelFuturesAgreement $modelFuturesAgreement, ModelCommonProduct $modelCommonProduct)
    {
        $agreementIds = $this->request->input->get('agreement_ids');
        $agreements = $modelFuturesAgreement->getAgreementsByIds($agreementIds);
        if ($agreements->isEmpty()) {
            return $this->jsonSuccess();
        }

        $num = $agreements->sum('num');
        $productId = $agreements->first()->product_id;

        $ret = $modelCommonProduct->checkProductQtyIsAvailable(intval($productId), intval($num));
        if($ret){
            return $this->jsonSuccess();
        }

        return $this->jsonFailed();
    }


    /**
     * [sellerFuturesProcessBid description] 期货二期有部分代码不同需要更改sellerProcessBid 方法
     */
    public function sellerFuturesProcessBid()
    {
        $this->load->model('futures/agreement');
        $post = $this->request->post;
        $customer_id = $this->customer->getId();
        $operator = $this->customer->getFirstName() . $this->customer->getLastName();
        $agreement = $this->model_futures_agreement->getAgreementById($post['agreement_id']);
        // 需要校验token 以及协议内容是否变化
        $this->checkFuturesAgreement($agreement, $post, $customer_id);
        try {
            $this->orm->getConnection()->beginTransaction();
            $pdata = [
                'agreement_status' => $post['agreement_status'],
                'update_time' => date('Y-m-d H:i:s'),
                'is_lock' => $post['agreement_status'] == 3 ? 1 : 0, // 统计合约锁定数量
            ];
            // seller
            $log_type = 0;
            switch ($post['agreement_status']){// 3 & 4
                case 3://Approved
                    $log_type = 48;//详情页，同意
                    if($agreement->agreement_status == 1){//列表页，直接同意
                        $log_type = 3;
                    }
                    break;
                case 4://Rejected
                    $log_type = 49;//详情页，拒绝
                    if($agreement->agreement_status == 1){//列表页，直接拒绝
                        $log_type = 4;
                    }
                    break;
            }
            $log = [
                'agreement_id' => $post['agreement_id'],
                'customer_id' => $customer_id,
                'type' => $log_type,
                'operator' => $operator,
            ];
            $this->model_futures_agreement->addAgreementLog(
                $log,
                [$agreement->agreement_status, $post['agreement_status']],
                [0, 0]
            );
            $this->model_futures_agreement->updateAgreement($post['agreement_id'], $pdata);
            if ($post['agreement_status'] == 3) {
                // seller保证金支付业务 seller 的保证金已经在合约中扣掉了
                $this->chargeMoney(1, $agreement);
                // 生成buyer的期货保证金头款
                $product_id_new = $this->model_futures_agreement->copyFutureMaginProduct($post['agreement_id']);
                // 创建期货保证金记录
                $this->model_futures_agreement->addFutureMarginProcess([
                    'advance_product_id' => $product_id_new,
                    'agreement_id' => $post['agreement_id'],
                    'process_status' => 1
                ]);
            }
            // 添加到消息表中
            if(!trim($post['message'])){
                if($post['agreement_status'] == ModelFuturesAgreement::AGREEMENT_APPROVED){
                    $post['message'] = " The Seller has approved Buyer's request of future goods agreement.";
                }elseif($post['agreement_status'] == ModelFuturesAgreement::AGREEMENT_REJECTED){
                    $post['message'] = "The Seller has rejected Buyer's request of future goods agreement.";
                }
            }
            $this->addMessage($post);
            // 发送站内信
            $condition = [
                'from'   => $agreement->seller_id,
                'to'     => $agreement->buyer_id,
                'status' => $post['agreement_status'] == 3 ? 1 : 0,
                'country_id' => $this->customer->getCountryId(),
            ];
            $this->model_futures_agreement->addFuturesAgreementCommunication($post['agreement_id'],4,$condition);
            $this->orm->getConnection()->commit();
            // 防止用户重复提交 1详情提交 2列表提交
            if ($post['type'] == 1) {
                unset($this->session->data['futures_csrf_token']);
            }
            $agreement = $this->model_futures_agreement->getAgreementById($post['agreement_id']);
            if ($agreement->agreement_status == ModelFuturesAgreement::AGREEMENT_PENDING) {
                $msg = 'The message is sent successfully.';
            } elseif($agreement->agreement_status == ModelFuturesAgreement::AGREEMENT_APPROVED) {
                $msg = "The Buyer's request of future goods agreement have been approved.";
            }else{
                $msg = "The Buyer's request of future goods agreement has been rejected.";
            }
            $this->response->success([], $msg);
        } catch (Exception $e) {
            $this->orm->getConnection()->rollBack();
            $this->response->failed($e->getMessage());
        }
    }

    /**
     * [ChargeMoney description] 除了1之外，所有的判断违约操作都在定时任务中完成
     * @param $type 1 seller 同意bid
     * @param $agreement
     * @throws Exception
     */
    public function chargeMoney($type, $agreement)
    {
        $this->load->model('futures/contract');
        $this->load->model('futures/agreement');
        $contract_info = $this->model_futures_contract->firstPayRecordContracts($agreement->seller_id, [$agreement->contract_id]);
        $is_japan = JAPAN_COUNTRY_ID == $this->customer->getCountryId() ? 1 : 0;
        $point = $is_japan ? 0 : 2;
        $amount = round($agreement->unit_price * $agreement->seller_payment_ratio / 100, $point) * $agreement->num;
        switch ($type) {
            case 1:
                // seller 同意bid
                //agreementMargin::sellerPayFutureMargin($agreement->seller_id, $agreement->contract_id, $agreement->id, $amount, $contract_info[0]['pay_type']);
                break;
            case 2:
                // seller 交货前 seller协商 buyer同意
                //（协议开始时间至协议协商终止时间 / 协议开始时间至协议交货时间） * 每件产品的期货保证金 * 协议数量）
                $left_day = $this->model_futures_agreement->getLeftDay($agreement);
                $all_day = $this->model_futures_agreement->getLeftDay($agreement, $agreement->update_time);
                // seller 赔偿给buyer的钱
                $paid_amount = ($all_day - $left_day) / $all_day * ($agreement->unit_price * $agreement->seller_payment_ratio / 100) * $agreement->num;
                $paid_amount = round($paid_amount, $point);
                // seller 缴纳的平台费
                $platform_amount = round($paid_amount * 0.05, $point);
                $buyer_amount = round(($paid_amount - $platform_amount),$point);
                // 退还给buyer的钱 $paid_amount
                // seller 本金拿回
                agreementMargin::sellerBackFutureMargin($agreement->seller_id, $agreement->id, $amount, $contract_info[0]['pay_type']);
                // seller 赔付 buyer
                agreementMargin::sellerWithHoldFutureMargin($agreement->seller_id, $agreement->id, $buyer_amount, $contract_info[0]['pay_type']);
                // seller 赔付 平台费用
                agreementMargin::sellerPayFuturePlatform($agreement->seller_id, $agreement->id, $platform_amount, $contract_info[0]['pay_type']);
                // 授信额度退回
                if ($contract_info[0]['pay_type'] == 1) {
                    credit::insertCreditBill($agreement->seller_id, $amount, 2);
                }
                $this->model_futures_agreement->addCreditRecord($agreement, $amount, 1);
                $this->model_futures_agreement->addCreditRecord($agreement, $buyer_amount, 2);
                break;
            case 3:
                // seller 交货前 buyer协商 Seller同意
                //（协议开始时间至协议协商终止时间 / 协议开始时间至协议交货时间） * 每件产品的期货保证金 * 协议数量）
                $left_day = $this->model_futures_agreement->getLeftDay($agreement);
                $all_day = $this->model_futures_agreement->getLeftDay($agreement, $agreement->update_time);
                // buyer 赔偿给seller的钱
                $paid_amount = ($all_day - $left_day) / $all_day * ($agreement->unit_price * $agreement->seller_payment_ratio / 100) * $agreement->num;
                $paid_amount = round($paid_amount, $point);
                // buyer 缴纳的平台费
                $platform_amount = round($paid_amount * 0.05, $point);
                // 退还给buyer的钱
                $buyer_left_amount = round(($amount - $paid_amount - $platform_amount), $point);
                // seller 本金拿回
                agreementMargin::sellerBackFutureMargin($agreement->seller_id, $agreement->id, $amount, $contract_info[0]['pay_type']);
                agreementMargin::insertMarginPayRecord(5, 2, $agreement->seller_id, $agreement->id, $paid_amount, $contract_info[0]['pay_type']);
                $this->model_futures_agreement->addCreditRecord($agreement, $buyer_left_amount, 1);
                break;
            case 4:
                // 【交货日期后】Seller发起协商终止交货，Buyer同意
                $left_day = $this->model_futures_agreement->getConfirmLeftDay($agreement);
                $all_day = 7;
                $agreement->purchase_num = $this->model_futures_agreement->getAgreementCurrentPurchaseQuantity($agreement->id) ?? 0;
                // seller 赔偿给buyer的钱
                $paid_amount = ($all_day - $left_day) / $all_day * ($agreement->unit_price * $agreement->seller_payment_ratio / 100) * ($agreement->num -  $agreement->purchase_num);
                $paid_amount = round($paid_amount, $point);
                // seller 缴纳的平台费
                $platform_amount = round($paid_amount * 0.05, $point);
                $buyer_amount = round(($paid_amount - $platform_amount),$point);
                // 退还给buyer的钱 $paid_amount
                // seller 本金拿回
                agreementMargin::sellerBackFutureMargin($agreement->seller_id, $agreement->id, $amount, $contract_info[0]['pay_type']);
                // seller 赔付 buyer
                agreementMargin::sellerWithHoldFutureMargin($agreement->seller_id, $agreement->id, $buyer_amount, $contract_info[0]['pay_type']);
                // seller 赔付 平台费用
                agreementMargin::sellerPayFuturePlatform($agreement->seller_id, $agreement->id, $platform_amount, $contract_info[0]['pay_type']);
                // 授信额度退回
                if ($contract_info[0]['pay_type'] == 1) {
                    credit::insertCreditBill($agreement->seller_id, $amount, 2);
                }
                $this->model_futures_agreement->addCreditRecord($agreement, $amount, 1);
                $this->model_futures_agreement->addCreditRecord($agreement, $buyer_amount, 2);
                break;
            case 5:
                // 【交货日期后】Buyer发起协商终止交货，Seller同意
                // （交货日期至协议协商终止时间 / 尾款支付有效期7天） * 每件产品的期货保证金 * 协议剩余数量）
                $left_day = $this->model_futures_agreement->getConfirmLeftDay($agreement);
                $all_day = 7;
                $agreement->purchase_num = $this->model_futures_agreement->getAgreementCurrentPurchaseQuantity($agreement->id) ?? 0;


                $paid_amount = ($all_day - $left_day) / $all_day * ($agreement->unit_price * $agreement->seller_payment_ratio / 100) * ($agreement->num -  $agreement->purchase_num);
                $paid_amount = round($paid_amount, $point);
                // buyer 缴纳的平台费
                $platform_amount = round($paid_amount * 0.05, $point);
                // 退还给buyer的钱
                $buyer_left_amount = round(($amount - $paid_amount - $platform_amount), $point);
                // seller 本金拿回
                agreementMargin::sellerBackFutureMargin($agreement->seller_id, $agreement->id, $amount, $contract_info[0]['pay_type']);
                agreementMargin::insertMarginPayRecord(5, 2, $agreement->seller_id, $agreement->id, $paid_amount, $contract_info[0]['pay_type']);
                $this->model_futures_agreement->addCreditRecord($agreement, $buyer_left_amount, 1);
                break;
        }
    }

    /**
     * 保存seller的期货协议提交信息
     * @throws Exception
     */
    public function sellerProcessBid()
    {
        $this->load->model('futures/agreement');
        $post = $this->request->post;
        $sell_id = $this->customer->getId();
        $agreement = $this->model_futures_agreement->getAgreementById($post['agreement_id']);
        $this->checkAgreement($agreement, $post);
        try {
            $this->orm->getConnection()->beginTransaction();
            $pdata = [
                'agreement_status' => $post['agreement_status'],
                'update_time' => date('Y-m-d H:i:s')
            ];
            if ($post['agreement_status'] == 3) {
                $pdata['expected_delivery_date'] = $post['pre_delivery_date'];
            }
            $this->model_futures_agreement->updateAgreement($post['agreement_id'], $pdata);
            if ($post['agreement_status'] == 3) {
                // seller保证金支付业务
                $this->sellerHandleMarginPay($sell_id, $post);
                // 生成buyer的期货保证金头款
                $product_id_new = $this->model_futures_agreement->copyFutureMaginProduct($post['agreement_id']);
                // 创建期货保证金记录
                $this->model_futures_agreement->addFutureMarginProcess([
                    'advance_product_id' => $product_id_new,
                    'agreement_id' => $post['agreement_id'],
                    'process_status' => 1
                ]);
            }
            // 添加到消息表中
            $this->addMessage($post);
            $this->orm->getConnection()->commit();
            // 防止用户重复提交
            $this->session->remove('csrf_token');
            if ($agreement->agreement_status == 2) {
                $msg = 'The message is sent successfully.';
            } else {
                $msg = 'The message is sent and the agreemeent status is updated successfully.';
            }
            $this->response->success([], $msg);
        } catch (Exception $e) {
            $this->orm->getConnection()->rollBack();
            $this->response->failed($e->getMessage());
        }
    }


    protected function checkFuturesAgreement($agreement, $post, $seller_id)
    {
        $this->load->model('futures/agreement');
        $this->load->language('futures/agreement');
        // 防止重复提交
        $isJapan = JAPAN_COUNTRY_ID == $this->customer->getCountryId();
        $point = $isJapan ? 0 : 2;
        if ($post['type'] == 1) {
            // buyer seller 协议approve tab
            if ($this->session->data['futures_csrf_token'] != $post['csrf_token']) {
                return $this->response->failed('The page expires, please refresh and try again.');
            }

            if (!$agreement || !in_array($agreement->agreement_status, [ModelFuturesAgreement::AGREEMENT_APPLIED, ModelFuturesAgreement::AGREEMENT_PENDING])) {
                return $this->response->failed('The status of agreement has been changed. Please reload the page.');
            }
            $earnest = round(($agreement->unit_price * $agreement->seller_payment_ratio / 100),$point)*$agreement->num;
            if (in_array($post['agreement_status'], [ModelFuturesAgreement::AGREEMENT_APPROVED, ModelFuturesAgreement::AGREEMENT_REJECTED])) {
                if ($agreement->num != $post['num'] || bccomp($agreement->unit_price, $post['unit_price'], 2) !== 0 || bccomp($post['amount'], $earnest, 2) !== 0) {
                    return $this->response->failed('The status of agreement has been changed. Please reload the page.');
                }
            }

            if($post['agreement_status'] == ModelFuturesAgreement::AGREEMENT_APPROVED && !$this->model_futures_agreement->verifyAmountIsEnough($agreement,$isJapan)){
                return $this->response->failed(sprintf($this->language->get('text_future_seller_contract_amount_error'), $agreement->contract_no));
            }

            if($post['agreement_status'] == ModelFuturesAgreement::AGREEMENT_APPROVED && !$this->model_futures_agreement->isEnoughContractQty($agreement->id)){
                return $this->response->failed('You are not able to approve this bid request due to insufficient Total Quantity Available for This Contract.');
            }

            // 需要校验
        } elseif ($post['type'] == 2) {
            // buyer seller 协议approve 列表
            if ($agreement->update_time != $post['update_time'] && $agreement->de_update_time != $post['update_time']) {
                return $this->response->failed('The status of agreement has been changed. Please reload the page.');
            }
            if (!$agreement || !in_array($agreement->agreement_status, [ModelFuturesAgreement::AGREEMENT_APPLIED, ModelFuturesAgreement::AGREEMENT_PENDING])) {
                return $this->response->failed('The status of agreement has been changed. Please reload the page.');
            }

            if($post['agreement_status'] == ModelFuturesAgreement::AGREEMENT_APPROVED && !$this->model_futures_agreement->verifyAmountIsEnough($agreement,$isJapan)){
                return $this->response->failed(sprintf($this->language->get('text_future_seller_contract_amount_error'), $agreement->contract_no));
            }

            if($post['agreement_status'] == ModelFuturesAgreement::AGREEMENT_APPROVED && !$this->model_futures_agreement->isEnoughContractQty($agreement->id)){
                return $this->response->failed('You are not able to approve this bid request due to insufficient Total Quantity Available for This Contract.');
            }
        } elseif ($post['type'] == 3 || $post['type'] == 4) {
            // seller 入仓校验
            if ($agreement->update_time != $post['update_time'] && $agreement->de_update_time != $post['update_time']) {
                return $this->response->failed('The status of agreement has been changed. Please reload the page.');
            }
            if (!$agreement || !in_array($agreement->delivery_status, [ModelFuturesAgreement::DELIVERY_FORWARD_DELIVERY])) {
                return $this->response->failed('The status of agreement has been changed. Please reload the page.');
            }
            //seller 没有待审批的数据
            $exists = $this->orm->table('oc_futures_agreement_apply')
                ->where([
                    'status' => 0,
                    'agreement_id' => $agreement->id,
                    'customer_id' => $seller_id
                ])
                ->where('apply_type','!=', FuturesMarginApplyType::APPEAL)
                ->exists();
            if ($exists) {
                return $this->response->failed('The page expires, please refresh and try again.');
            }
            // seller days
            $day = $this->model_futures_agreement->getLeftDay($agreement);
            if ($day != $post['day']) {
                return $this->response->failed('The page expires, please refresh and try again.');
            }

        } elseif ($post['type'] == 5) {
            // buyer 待入仓阶段提交的，已入仓阶段提交的 提交的审批 seller同意或者不同意
            if ($agreement->update_time != $post['update_time'] && $agreement->de_update_time != $post['update_time']) {
                return $this->response->failed('The status of agreement has been changed. Please reload the page.');
            }
            if ($post['action_id'] == 1) {
                if (!$agreement || !in_array($agreement->delivery_status, [ModelFuturesAgreement::DELIVERY_FORWARD_DELIVERY])) {
                    return $this->response->failed('The status of agreement has been changed. Please reload the page.');
                }
            } elseif ($post['action_id'] == 8) {
                if (!$agreement || !in_array($agreement->delivery_status, [ModelFuturesAgreement::DELIVERY_TO_BE_PAID])) {
                    return $this->response->failed('The status of agreement has been changed. Please reload the page.');
                }
            }

        } elseif ($post['type'] == 6) {
            // seller 在已入仓之后发起协商或者是直接终止
            if ($agreement->update_time != $post['update_time'] && $agreement->de_update_time != $post['update_time']) {
                return $this->response->failed('The status of agreement has been changed. Please reload the page.');
            }
            if (!$agreement || !in_array($agreement->delivery_status, [ModelFuturesAgreement::DELIVERY_TO_BE_PAID])) {
                return $this->response->failed('The status of agreement has been changed. Please reload the page.');
            }
            //seller 没有待审批的数据
            $exists = $this->orm->table('oc_futures_agreement_apply')
                ->where([
                    'status' => 0,
                    'agreement_id' => $agreement->id,
                    'customer_id' => $seller_id
                ])
                ->exists();
            if ($exists) {
                return $this->response->failed('The page expires, please refresh and try again.');
            }
        } elseif ($post['type'] == 7) {
            // seller 申诉 back order之后
            if ($agreement->update_time != $post['update_time'] && $agreement->de_update_time != $post['update_time']) {
                return $this->response->failed('The status of agreement has been changed. Please reload the page.');
            }
            if (!$agreement || !in_array($agreement->delivery_status, [ModelFuturesAgreement::DELIVERY_BACK_ORDER])) {
                return $this->response->failed('The status of agreement has been changed. Please reload the page.');
            }
            //seller 没有待审批的数据
            $exists = $this->orm->table('oc_futures_agreement_apply')
                ->where([
                    'status' => 0,
                    'agreement_id' => $agreement->id,
                    'customer_id' => $seller_id
                ])
                ->exists();
            if ($exists) {
                return $this->response->failed('The page expires, please refresh and try again.');
            }
        } elseif ($post['type'] == 8) {
            // seller 申诉 back order之后
            if ($agreement->update_time != $post['update_time'] && $agreement->de_update_time != $post['update_time']) {
                return $this->response->failed('The status of agreement has been changed. Please reload the page.');
            }
            if (!$agreement || !in_array($agreement->delivery_status, [ModelFuturesAgreement::DELIVERY_BACK_ORDER])) {
                return $this->response->failed('The status of agreement has been changed. Please reload the page.');
            }
            //seller 没有待审批的数据
            $exists = $this->orm->table('oc_futures_agreement_apply')
                ->where([
                    'status' => 0,
                    'agreement_id' => $agreement->id,
                    'customer_id' => $seller_id
                ])
                ->exists();
            if ($exists) {
                return $this->response->failed('The page expires, please refresh and try again.');
            }

            // seller days
            $day = $this->model_futures_agreement->getLeftDay($agreement);
            if ($day == 0) {
                return $this->response->failed('The page expires, please refresh and try again.');
            }
        }

    }

    protected function checkAgreement($agreement, $post)
    {
        // 防止重复提交
        if ($this->session->data['csrf_token'] != $post['csrf_token']) {
            return $this->response->failed('The page expires, please refresh and try again.');
        }

        if (!$agreement || !in_array($agreement->agreement_status, [ModelFuturesAgreement::AGREEMENT_APPLIED, ModelFuturesAgreement::AGREEMENT_PENDING])) {
            return $this->response->failed('The status of agreement has been changed. Please reload the page.');
        }
        $is_japan = JAPAN_COUNTRY_ID == $this->customer->getCountryId() ? 1 : 0;
        $point = $is_japan ? 0 : 2;
        $earnest = round(($agreement->unit_price * $agreement->seller_payment_ratio / 100),$point)*$agreement->num;
        if (in_array($post['agreement_status'], [ModelFuturesAgreement::AGREEMENT_APPROVED, ModelFuturesAgreement::AGREEMENT_REJECTED])) {
            if ($agreement->num != $post['num'] || bccomp($agreement->unit_price, $post['unit_price'], 2) !== 0 || bccomp($post['amount'], $earnest, 2) !== 0) {
                return $this->response->failed('The status of agreement has been changed. Please reload the page.');
            }
        }
    }

    /**
     * 处理保证金支付业务
     * @param int $sell_id
     * @param $post
     * @throws Exception
     */
    protected function sellerHandleMarginPay($sell_id, $post)
    {
        switch ($post['pay_method']) {
            // 判断授信余额是否可以支付
            case 1:
                $credit = credit::getLineOfCredit($sell_id);
                $credit < $post['amount'] && $this->response->failed();
                credit::insertCreditBill($sell_id, $post['amount'], 1);
                break;
            // 判断有效提单是否可以支付
            case 2:
                $expected_qty = $this->getReceiptsOrder($post['product_id']);
                $expected_qty < $post['num'] && $this->response->failed();
                $post['amount'] = $post['num'];
                break;
            // 判断应收款是否可以支付
            case 3:
                $this->load->model('futures/agreement');
                $seller_bill_total = $this->getSellerBillTotal($sell_id);
                $seller_bill_total < $post['amount'] && $this->response->failed();
                break;
            // 判断现货抵押物是否可以支付
            //            case 4:
            //                $product_amount = $this->getProductAmountRatio($sell_id);
            //                $product_amount < $post['amount'] && $this->response->failed();
            //                break;
        }
        // 添加记录到seller的保证金支付记录表
        // 0.1 如果是有效提单支付 需判断是否是combo产品
        if ($post['pay_method'] == 2 && $products = $this->model_futures_agreement->getComboProductInfo($post['product_id'])) {
            foreach ($products as $item) {
                $post['product_id'] = $item->set_product_id;
                $post['amount'] = $post['amount'] * $item->qty;
                $this->addMarginPayRecord($sell_id, $post);
            }
        } else {
            $this->addMarginPayRecord($sell_id, $post);
        }
    }

    /**
     * 添加到seller的保证金支付记录表
     * @param int $sell_id
     * @param $post
     */
    protected function addMarginPayRecord($sell_id, $post)
    {
        $map['agreement_id'] = $post['agreement_id'];
        $map['customer_id'] = $sell_id;
        $map['product_id'] = $post['product_id'];
        $map['type'] = $post['pay_method'];
        $map['amount'] = $post['amount'];
        $this->model_futures_agreement->addMarginPayRecord($map);
    }


    /**
     * 保存seller的审批操作
     * @throws Exception
     */
    public function sellerApproval()
    {
        $this->load->model('futures/agreement');
        $post = $this->request->post;
        // 防止重复提交
        if ($this->session->data['csrf_token'] != $post['csrf_token']) {
            return $this->response->failed('The page expires, please refresh and try again.');
        }
        $future_agreement = $this->model_futures_agreement->getAgreementById($post['agreement_id']);
        if (!in_array($future_agreement->delivery_status, [1, 5])) {
            return $this->response->failed();
        }
        try {
            $this->orm->getConnection()->beginTransaction();
            $this->load->model('catalog/futures_product_lock');
            switch ($post['delivery_status']) {
                // seller无法交付 ，扣押seller期货保证金
                case 2:
                    $this->model_futures_agreement->withholdFutureMargin($future_agreement->id);
                    break;
                // seller交付期货，锁定库存
                case 3:
                    $data['delivery_date'] = date('Y-m-d H:i:s'); //交付日期
                    $this->load->model('common/product');
                    if (
                    !$this->model_common_product->checkProductQtyIsAvailable(
                        (int)$future_agreement->product_id,
                        (int)$future_agreement->num)
                    ) {
                        throw new Exception('Low stock quantity.');
                    }
                    $this->model_catalog_futures_product_lock->TailIn($future_agreement->id, $future_agreement->num, $future_agreement->id, 0);
                    break;
                // seller同意buyer的交割方式,返还seller期货保证金
                case 6:
                    $data['confirm_delivery_date'] = date('Y-m-d H:i:s'); //确认交割日期
                    // 返还seller期货保证金
                    $this->model_futures_agreement->backFutureMargin($future_agreement->id);
                    // 如果是转现货交割则生成现货协议和现货头款产品
                    if (in_array($future_agreement->delivery_type, [2, 3]) && !$future_agreement->margin_agreement_id) {
                        // 生成现货协议
                        $agreement = $this->addMarginAgreement($future_agreement);
                        // 生成现货头款产品
                        $product_id_new = $this->model_futures_agreement->copyMarginProduct($agreement, 1);
                        // 创建现货保证金记录
                        $this->addMarginProcess($agreement, $product_id_new);
                        // 更新期货交割表
                        $this->model_futures_agreement->updateDelivery($post['agreement_id'], ['margin_agreement_id' => $agreement['agreement_id']]);
                    }
                    break;
            }
            $data['delivery_status'] = $post['delivery_status'];
            $this->model_futures_agreement->updateDelivery($post['agreement_id'], $data);
            // 添加到消息表中
            $this->addMessage($post);
            if ($post['delivery_status'] == 6 && in_array($future_agreement->delivery_type, [2, 3])) {
                // 库存锁定变更
                $this->model_catalog_futures_product_lock->TailOut($future_agreement->id, $future_agreement->margin_apply_num, $future_agreement->id, 6);
            }
            $this->orm->getConnection()->commit();
            // 防止用户重复提交
            $this->session->remove('csrf_token');
            $this->response->success([], 'The message is sent successfully.');
        } catch (Exception $e) {
            $this->orm->getConnection()->rollBack();
            $this->log->write($e);
            // 999 因为库存不足而产生的异常
            if ($e->getCode() == 999) {
                $this->response->failed('Product not available in the desired quantity or not in stock! Please contact with our customer service to argue. ');
            }
            $this->response->failed($e->getMessage());
        }
    }

    /**
     * 添加消息
     * @param $post
     */
    public function addMessage($post)
    {
        // 添加到消息表中
        $data['agreement_id'] = $post['agreement_id'];
        $data['customer_id'] = $this->customer->getId();
        $data['message'] = $post['message'];
        $this->model_futures_agreement->addMessage($data);
    }

    /**
     * 创建现货协议
     * @param $future
     * @return mixed
     * @throws Exception
     */
    public function addMarginAgreement($future)
    {
        $this->load->model('account/product_quotes/margin_agreement');
        $this->load->language('account/product_quotes/margin');
        $product_info = $this->model_account_product_quotes_margin_agreement->getProductInformationByProductId($future->product_id);
        if (empty($product_info)) {
            throw new \Exception($this->language->get("error_no_product"));
        }
        //        if ($product_info['quantity'] < $future->margin_apply_num) {
        //            throw new \Exception($this->language->get("error_under_stock"));
        //        }
        if ($product_info['status'] == 0 || $product_info['is_deleted'] == 1 || $product_info['buyer_flag'] == 0) {
            throw new \Exception($this->language->get("error_product_invalid"));
        }
        if (JAPAN_COUNTRY_ID == $this->customer->getCountryId()) {
            $precision = 0;
        } else {
            $precision = 2;
        }
        $agreement_id = date('Ymd') . rand(100000, 999999);
        $data = [
            'agreement_id' => $agreement_id,
            'seller_id' => $future->seller_id,
            'buyer_id' => $future->buyer_id,
            'product_id' => $product_info['product_id'],
            'clauses_id' => 1,
            'price' => $future->margin_unit_price,
            'payment_ratio' => MARGIN_PAYMENT_RATIO * 100,
            'day' => $future->margin_days,
            'num' => $future->margin_apply_num,
            'money' => $future->margin_deposit_amount,
            'deposit_per' => round($future->margin_unit_price * 20 / 100, $precision),
            'status' => 3,
            'period_of_application' => 1,
            'create_user' => $future->buyer_id,
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s'),
            'program_code' => MarginAgreement::PROGRAM_CODE_V4, //现货保证金四期
        ];
        $back['agreement_id'] = $this->model_futures_agreement->saveMarginAgreement($data);
        if ($back['agreement_id']) {
            $data_t = [
                'margin_agreement_id' => $back['agreement_id'],
                'customer_id' => $data['buyer_id'],
                'message' => 'Transfered to margin goods payment',
                'create_time' => date('Y-m-d H:i:s'),
            ];
            $this->orm->table('tb_sys_margin_message')->insert($data_t);
        }
        $back['agreement_no'] = $agreement_id;
        $back['seller_id'] = $future->seller_id;
        $back['product_id'] = $future->product_id;
        $back['price_new'] = $future->margin_deposit_amount;
        return $back;
    }

    /**
     * 创建现货保证金进程记录
     * @param $data
     * @param int $product_id
     * @throws Exception
     */
    private function addMarginProcess($data, $product_id)
    {
        $margin_process = [
            'margin_id' => $data['agreement_id'],
            'margin_agreement_id' => $data['agreement_no'],
            'advance_product_id' => $product_id,
            'process_status' => 1,
            'create_time' => Carbon::now(),
            'create_username' => $this->customer->getId(),
            'program_code' => 'V1.0'
        ];
        $this->load->model('account/product_quotes/margin_contract');
        $this->model_account_product_quotes_margin_contract->addMarginProcess($margin_process);
    }


}
