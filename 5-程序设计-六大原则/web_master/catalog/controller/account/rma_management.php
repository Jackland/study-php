<?php

use App\Components\Locker;
use App\Helper\AddressHelper;
use App\Helper\CountryHelper;
use App\Helper\StringHelper;
use App\Enums\Safeguard\SafeguardBillStatus;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Components\Storage\StorageCloud;
use App\Components\Storage\StorageLocal;
use App\Enums\YzcRmaOrder\RmaApplyType;
use App\Logging\Logger;
use App\Models\Rma\YzcRmaFile;
use App\Models\Rma\YzcRmaOrder;
use App\Models\SalesOrder\CustomerSalesOrder;
use App\Repositories\Rebate\RebateRepository;
use App\Repositories\Rma\RamRepository;
use App\Enums\SalesOrder\CustomerSalesOrderMode;
use App\Repositories\SalesOrder\CustomerSalesOrderRepository;
use App\Repositories\Setup\SetupRepository;
use App\Repositories\Safeguard\SafeguardBillRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @property ModelAccountNotification $model_account_notification
 * @property ModelAccountRmaManagement $model_account_rma_management
 * @property ModelAccountRmaManage $model_account_rma_manage
 * @property ModelAccountOrder $model_account_order
 * @property ModelBuyerBuyerCommon $model_buyer_buyer_common
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelCustomerpartnerRmaManagement $model_customerpartner_rma_management
 * @property ModelToolImage $model_tool_image
 * @property ModelMessageMessage $model_message_message
 */
class ControllerAccountRmaManagement extends Controller
{
    const RMA_APPLIED = 0;
    const RMA_PROCESSED = 1;
    const RMA_PENDING = 2;
    const RMA_CANCELED = 3;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        // 判断用户是否登录
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/rma_management', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }
    }

    public function index()
    {
        // 加载model
        $this->load->model('account/rma/manage');
        $this->load->model('account/rma_management');
        // 加载语言层
        $this->load->language('account/rma_management');
        $this->load->language('common/cwf');
        // 设置文档标题
        $this->document->setTitle($this->language->get('heading_title'));
        // 面包屑导航
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_rma_management'),
            'href' => $this->url->link('account/rma_management', '', true)
        );
        $this->resolveRmaInfo($data);
        $data['currency'] = session('currency');
        $data['reship_non_process_count'] = $rec = $this->model_account_rma_management
            ->getRmaOrderInfoCount(['seller_status' => [1, 3], 'rma_type' => [1, 3], 'filter_status_reshipment' => 0]);
        $data['refund_non_process_count'] = $rfc = $this->model_account_rma_management
            ->getRmaOrderInfoCount(['seller_status' => [1, 3], 'rma_type' => [2, 3], 'filter_status_refund' => 0]);
        // 选择标签页 0-create_rma 1-reshipment_order 2-refund_application
        $show_tab = 0;
        if ($this->request->attributes->has('tab_index')) {
            $show_tab = $this->request->attributes->get('tab_index', 0);
        } else {
            if (!isset($this->request->request['filter_order_id']) && ($rec !== 0 || $rfc !== 0)) {
                $show_tab = $rec >= $rfc ? 1 : 2;
            }
        }

        $data['show_tab'] = $show_tab;
        $data['is_single_no_binding_active'] = $this->request->get('source', '') == 'sale_canceled_order';
        $data['is_japan'] = $this->customer->isJapan() ? 1 : 0;
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');
        $this->response->setOutput($this->load->view('account/rma_management/rma_management', $data));
    }

    /**
     * 检查是销售单，还是采购单
     */
    public function checkSalesAndPurchase()
    {
        $request = $this->request->request;
        $order_id = trim($request['filter_order_id'] ?? '');
        $is_sales = $this->orm->table('tb_sys_customer_sales_order')->where('order_id', $order_id)->where('buyer_id', $this->customer->getId())->select('id')->first();
        $is_purchase = null;
        $result_id = preg_replace('/\d/', '', $order_id);
        if (empty($result_id)) {
            $is_purchase = $this->orm->table('oc_order')->where('order_id', $order_id)->where('customer_id', $this->customer->getId())->select('order_id')->first();
        }

        $order_type = '';//空订单号
        if ($is_sales) {
            // 判断为销售订单
            $order_type = '1';
        }
        if ($is_purchase) {
            // 判断为采购订单
            $order_type = '2';
        }
        if ($is_sales && $is_purchase) {
            // 判断为 销售订单号与采购订单号相同
            $order_type = '1+2';
        }
        $this->jsonSuccess(['orderType' => $order_type]);
    }

    public function getReshipUnResolveCount()
    {
        $this->load->model('account/rma_management');
        $count = (string)$this->model_account_rma_management
            ->getRmaOrderInfoCount(['seller_status' => [1, 3], 'rma_type' => [1, 3], 'filter_status_reshipment' => 0]);
        return $count;
    }

    public function getRefundUnResolveCount()
    {
        $this->load->model('account/rma_management');
        $count = (string)$this->model_account_rma_management
            ->getRmaOrderInfoCount(['seller_status' => [1, 3], 'rma_type' => [2, 3], 'filter_status_refund' => 0]);
        return $count;
    }

    private function resolveRmaInfo(array &$data)
    {
        $request = request()->attributes->all();
        $data['request'] = $request;
        $order_id = trim($request['filter_order_id'] ?? '');
        $order_type = intval(trim($request['filter_order_type'] ?? ''));
        $customer_id = (int)customer()->getId();
        if (!$order_id) {
            return;
        }
        if (!$order_type) {
            if (db('tb_sys_customer_sales_order')->where(['order_id' => $order_id, 'buyer_id' => $customer_id])->exists()) {
                $order_type = 1;  // 判断为销售订单
            } elseif (db('oc_order')->where(['order_id' => $order_id, 'customer_id' => $customer_id])->exists()) {
                $order_type = 2; // 判断为采购订单
            }
        }
        $res = null;
        switch ($order_type) {
            case 1:
                $res = $this->model_account_rma_manage->getSalesOrderRmaDetail($customer_id, $order_id);
                break;
            case 2:
                $res = $this->model_account_rma_manage->getPurchaseOrderRmaDetail($customer_id, $order_id);
                break;
            default:
                return;
        }
        $data['is_single'] = (bool)((bool)$res['binding'] xor (bool)$res['no_binding']);
        $data['has_data'] = (bool)((bool)$res['binding'] or (bool)$res['no_binding']);
        $data = array_merge($data, $res);
    }

    /**
     * 创建RMA
     */
    public function addRma()
    {
        // 判断用户是否登录
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/rma_management', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }
        // 加载语言层
        $this->load->language('account/rma_management');
        // 设置文档标题
        $this->document->setTitle($this->language->get('text_create_rma'));
        // 面包屑导航
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_rma_management'),
            'href' => $this->url->link('account/rma_management', '', true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_create_rma'),
            'href' => $this->url->link('account/rma_management', '', true)
        );
        $this->load->model('account/rma_management');

        // 获取订单状态
        $customer_order_status = $this->model_account_rma_management->getCustomerOrderStatus();
        $data['customer_order_status'] = $customer_order_status;

        $data['continue'] = $this->url->link('account/account', '', true);
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');
        $this->response->setOutput($this->load->view('account/rma_management/add_rma', $data));
    }

    public function autocomplete()
    {
        $json = array();
        if (isset($this->request->get['filter_order_id'])) {
            $filter_order_id = $this->request->get['filter_order_id'];
            if ($filter_order_id != '') {
                $this->load->model('account/rma_management');
                $customerId = $this->customer->getId();
                $filter_data = array(
                    'filter_order_id' => $filter_order_id,
                    'customer_id' => $customerId,
                    'start' => 0,
                    'limit' => 10
                );
                $results = $this->model_account_rma_management->getCustomerOrders($filter_data);
                foreach ($results as $result) {
                    $json[] = array(
                        'order_id' => $result['order_id'],
                        'id' => $result['id'],
                        'orders_from' => $result['orders_from'],
                        'order_date' => $result['create_time'],
                        'order_status' => $result['order_status'],
                        'ship_to' => app('db-aes')->decrypt($result['ship_name']),
                        'phone' => app('db-aes')->decrypt($result['ship_phone']),
                        'email' => app('db-aes')->decrypt($result['email']),
                        'address' => app('db-aes')->decrypt($result['ship_address1']) . ' ' . app('db-aes')->decrypt($result['ship_city']) . ' ' . $result['ship_state'] . ' ' . $result['ship_zip_code'] . ' ' . $result['ship_country']
                    );
                }
            }
            $sort_order = array();
            foreach ($json as $key => $value) {
                $sort_order[$key] = $value['order_id'];
            }
            array_multisort($sort_order, SORT_ASC, $json);
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function loadCustomerOrderInfo()
    {
        $json = array();
        if (isset($this->request->get['customer_order_id'])) {
            $buyerId = $this->customer->getId();
            $customer_order_id = trim($this->request->get['customer_order_id']);
            $this->load->model('account/rma_management');
            $customerOrder = $this->model_account_rma_management->getCustomerOrder($customer_order_id, $buyerId);
            // 根据customer_order 的主键id，获取customer_order_detail
            if (count($customerOrder) != 0) {
                $json['id'] = $customerOrder['id'];
                $json['orders_from'] = $customerOrder['orders_from'];
                $json['order_date'] = $customerOrder['create_time'];
                $json['order_status'] = $customerOrder['order_status'];
                $json['ship_to'] = app('db-aes')->decrypt($customerOrder['ship_name']);
                $json['phone'] = app('db-aes')->decrypt($customerOrder['ship_phone']);
                $json['email'] = $customerOrder['email'];
                $json['address'] = app('db-aes')->decrypt($customerOrder['ship_address1']) . ' ' . app('db-aes')->decrypt($customerOrder['ship_city']) . ' ' . $customerOrder['ship_state'] . ' ' . $customerOrder['ship_zip_code'] . ' ' . $customerOrder['ship_country'];
            }

        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function customerOrderDetails()
    {
        $this->load->language('account/rma_management');
        // 判断用户是否登录
        $is_margin = 0;
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/rma_management', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }
        $data = array();
        if (isset($this->request->get['customer_order_id'])) {
            $customer_order_id = trim($this->request->get['customer_order_id']);
        } else {
            $customer_order_id = null;
        }
        $buyerId = $this->customer->getId();
        $this->load->model('account/rma_management');
        $this->load->language('common/cwf');
        $this->load->model('tool/image');
        // 顾客订单
        $customerOrder = $this->model_account_rma_management->getCustomerOrder($customer_order_id, $buyerId);
        // 订单状态记录
        $data['order_status'] = $customerOrder['order_status'];
        if (count($customerOrder) != 0) {
            $data['customerOrder'] = $customerOrder;
            $data['customerOrderItemStatus'] = $this->model_account_rma_management->getCustomerOrderItemStatus();

            // 顾客订单明细
            $customerOrderLines = $this->model_account_rma_management->getCustomerOrderLineByHeaderId($customerOrder['id']);
            // 获取订单明细所对应的采购订单详情
            $orderLineIds = array();
            // ItemCode;
            $selectItems = array();
            $margin_array = [];
            foreach ($customerOrderLines as $customerOrderLine) {
                $orderLineIds[] = $customerOrderLine['id'];
            }
            // 获取 type_id 和 agreement_id
            $orders = $this->model_account_rma_management->getPurchaseOrderInfo($orderLineIds);
            $isEurope = $this->country->isEuropeCountry($this->customer->getCountryId());
            $data['isEurope'] = $isEurope;
            foreach ($customerOrderLines as &$customerOrderLine) {
                if ((float)$customerOrderLine['item_price'] == 1.0) {
                    $customerOrderLine['item_price'] = '';
                } else {
                    $customerOrderLine['item_price'] = sprintf("%.2f", $customerOrderLine['item_price']);
                }
                $orderItemInfos = array();
                $orderDetails = array();
                foreach ($orders as $orderDetail) {
                    if ($customerOrderLine['id'] == $orderDetail['sales_order_line_id']) {
                        if (!(count(explode('http', $orderDetail['image'])) > 1)) {
                            if (is_file(DIR_IMAGE . $orderDetail['image'])) {
                                $image = $this->model_tool_image->resize($orderDetail['image']);
                            } else {
                                $image = $this->model_tool_image->resize('no_image.png');
                            }
                            $orderDetail['image'] = $image;
                        }
                        //判断是否开启议价
                        $quoteResult = $this->model_account_rma_management->getQuotePrice($orderDetail['order_id'], $orderDetail['product_id']);
                        //判断返点
                        $this->load->model('customerpartner/rma_management');
                        //查看返点协议申请情况
                        //判断是否有保证金合同的包销产品
                        $margin_info = [];
                        $future_margin_info = [];
                        if ($orderDetail['type_id'] == 2) {
                            $margin_info = $this->model_account_rma_management->getMarginInfoByOrderIdAndProductId($orderDetail['order_id'], $orderDetail['product_id']);
                        } elseif ($orderDetail['type_id'] == 3) {
                            $future_margin_info = $this->model_account_rma_management->getFutureMarginInfoByOrderIdAndProductId($orderDetail['order_id'], $orderDetail['product_id']);
                        }
                        if ($orderDetail['freight_difference_per'] > 0) {
                            $orderDetail['freight_diff'] = true;
                            $orderDetail['tips_freight_difference_per'] = str_replace(
                                '_freight_difference_per_',
                                $this->currency->formatCurrencyPrice($orderDetail['freight_difference_per'], session('currency')),
                                $this->language->get('tips_freight_difference_per')
                            );
                        } else {
                            $orderDetail['freight_diff'] = false;
                        }
                        if ($margin_info && $customerOrder['order_status'] == CustomerSalesOrderStatus::COMPLETED) {
                            $is_margin = 1;
                            $result = $this->model_account_rma_management->getMarginPriceInfo($orderDetail['product_id'], $orderDetail['qty'], $orderDetail['order_product_id']);
                            $orderDetail['total'] = $result['totalMargin'];
                            $orderDetail['price_per'] = $result['unitMarginPrice'];
                            $orderDetail['service_fee_per'] = $result['restServiceFee'];
                            $orderDetail['advance_unit_price'] = $result['advanceUnitPrice'];
                            $orderDetail['freight'] = $result['freight'];
                            $orderDetail['poundage'] = $result['poundage'];
                            $orderDetail['transactionFee'] = $result['transactionFee'];
                            if ($customerOrder['order_status'] == CustomerSalesOrderStatus::COMPLETED) {
                                $orderDetail['totalPrice'] = round($result['totalPrice'], 2);
                            } else {
                                $orderDetail['totalPrice'] = round($result['restTotal'], 2);
                            }
                        } else if ($future_margin_info && $customerOrder['order_status'] == CustomerSalesOrderStatus::COMPLETED) {
                            $is_margin = 1;
                            $result = $this->model_account_rma_management->getFutureMarginPriceInfo($orderDetail['agreement_id'], $orderDetail['qty'], $orderDetail['order_product_id']);
                            $orderDetail['total'] = $result['totalFutureMargin'];
                            $orderDetail['price_per'] = $result['unitFutureMarginPrice'];
                            $orderDetail['service_fee_per'] = $result['serviceFee'];
                            $orderDetail['advance_unit_price'] = $result['advanceUnitPrice'];
                            $orderDetail['freight'] = $result['freight'];
                            $orderDetail['poundage'] = $result['poundage'];
                            $orderDetail['transactionFee'] = $result['transactionFee'];
                            if ($customerOrder['order_status'] == CustomerSalesOrderStatus::COMPLETED) {
                                $orderDetail['totalPrice'] = round($result['totalPrice'], 2);
                            } else {
                                $orderDetail['totalPrice'] = round($result['restTotal'], 2);
                            }
                        } else {
                            if (!empty($quoteResult)) {
                                $data['isQuote'] = true;
                                $orderDetail['quote'] = $this->currency->formatCurrencyPrice((double)($quoteResult['price'] - $quoteResult['discount_price']) * (int)$orderDetail['qty'], session('currency'));
                                $data['isEuropeCountry'] = $this->country->isEuropeCountry($this->customer->getCountryId());
                                if ($data['isEuropeCountry']) {
                                    $orderDetail['totalPrice'] = ($quoteResult['price'] + $orderDetail['freight_per'] + $orderDetail['package_fee']) * $orderDetail['qty'];
                                    $orderDetail['price_per'] = $this->currency->format($orderDetail['price'] - $quoteResult['amount_price_per'], session('currency'));
                                    // 获取用户国际判断是否为欧洲，欧洲订单包含服务费
                                    $orderDetail['service_fee_per'] = $this->currency->format((double)$orderDetail['unit_service_fee'] - $quoteResult['amount_service_fee_per'], session('currency'));
                                } else {
                                    $orderDetail['totalPrice'] = ($quoteResult['price'] + $orderDetail['freight_per'] + $orderDetail['package_fee']) * $orderDetail['qty'];
                                    $orderDetail['price_per'] = $this->currency->format($quoteResult['price'], session('currency'));
                                }
                            } else {
                                $orderDetail['quote'] = $this->currency->format(0.00, session('currency'));
                                $orderDetail['price_per'] = $this->currency->format($orderDetail['price'], session('currency'));
                                $orderDetail['totalPrice'] = ($orderDetail['price'] + $orderDetail['unit_service_fee'] + $orderDetail['freight_per'] + $orderDetail['package_fee']) * $orderDetail['qty'];
                                // 获取用户国际判断是否为欧洲，欧洲订单包含服务费
                                $orderDetail['service_fee_per'] = $this->currency->format((double)$orderDetail['unit_service_fee'], session('currency'));
                            }
                            $orderDetail['total'] = $this->currency->format($orderDetail['totalPrice'], session('currency'));
                            $orderDetail['poundage'] = $this->currency->format((double)$orderDetail['unit_poundage'] * (int)$orderDetail['qty'], session('currency'));
                            $orderDetail['transactionFee'] = $this->currency->format((double)$orderDetail['unit_poundage'] * (int)$orderDetail['qty'], session('currency'), '', false);
                            $orderDetail['freight'] = $this->currency->format($orderDetail['freight_per'] + $orderDetail['package_fee'], session('currency'));
                        }
                        $orderDetail['orderHistoryUrl'] = $this->url->link('account/order/purchaseOrderInfo', "order_id=" . $orderDetail['order_id'], true);
                        $orderDetail['contactSeller'] = $this->url->link('customerpartner/profile', "id=" . $orderDetail['seller_id'] . "&contact=1", true);
                        // 装配Order_Id 以及对应的 Buyer_Id
                        $flag = true;
                        //返点四期
                        $rebateInfo = $this->model_customerpartner_rma_management->getRebateInfo($orderDetail['order_id'], $orderDetail['product_id']);
                        $maxRefundMoney = $orderDetail['totalPrice'];
                        $canRefundMoney = $maxRefundMoney;
                        $data['tip_rebate_refund'] = '';
                        $data['msg_rebate_refund'] = '';
                        if (!empty($rebateInfo) && $customerOrder['order_status'] == CustomerSalesOrderStatus::CANCELED) {
                            $rebateRequestInfo = $this->model_customerpartner_rma_management->getRebateRequestInfo($rebateInfo['id']);
                            //判断该RMA申请是否在返点协议里(判断该订单之前的返点数量满不满足返点协议)
                            $beforeQty = $this->model_customerpartner_rma_management->getRebateOrderBefore($orderDetail['order_id'], $orderDetail['product_id'], $rebateInfo['id']);
                            $orderQty = $this->model_customerpartner_rma_management->getRebateQty($orderDetail['order_id'], $orderDetail['product_id'], $rebateInfo['id']);
                            if (in_array($rebateInfo['rebate_result'], [1, 2])) {
                                //正在生效的返点协议
                                $data['tip_rebate_refund'] = sprintf($this->language->get('tip_rebate'), $this->currency->formatCurrencyPrice($orderDetail['totalPrice'], session('currency')));
                                $data['msg_rebate_refund'] = $this->language->get('msg_rebate_process');
                            } else if ($rebateInfo['rebate_result'] == 7) {
                                /*
                                 * 6.协议申请中
                                 * 7.协议到期 request完成
                                 * 8.协议到期 request拒绝
                                 */
                                //该产品参与的返点协议已到期并达成，Seller已经同意返点给Buyer
                                if ($beforeQty >= $rebateInfo['rebateQty']) {
                                    //该订单前的可参加返点协议的数量已经大于返点熟练，全款退,无需提示


                                } else if ($beforeQty < $rebateInfo['rebateQty'] && $orderQty <= ($rebateInfo['rebateQty'] - $beforeQty)) {
                                    //该订单全在返点协议里,需扣除返点金额
                                    $hasRebateMoney = $rebateInfo['rebate_amount'] * $orderQty;
                                    $maxRefundMoney = $orderDetail['totalPrice'] - $hasRebateMoney;
                                    $canRefundMoney = $maxRefundMoney;
                                    $maxRefundMoneyCurr = $this->currency->formatCurrencyPrice($maxRefundMoney, session('currency'));
                                    $unitRefundCurr = $this->currency->formatCurrencyPrice($maxRefundMoney / min($orderDetail['qty'], $rebateInfo['qty']), session('currency'));
                                    $hasRebateMoneyCurr = $this->currency->formatCurrencyPrice($hasRebateMoney, session('currency'));
                                    $orderTotalMoneyCurr = $this->currency->formatCurrencyPrice($orderDetail['totalPrice'], session('currency'));
                                    $data['tip_rebate_refund'] = sprintf($this->language->get('tip_rebate_over'), $unitRefundCurr);
                                    $data['msg_rebate_refund'] = sprintf($this->language->get('msg_rebate_over'), $orderQty, $orderTotalMoneyCurr, $orderQty,
                                        $hasRebateMoneyCurr, $unitRefundCurr);
                                } else if ($beforeQty < $rebateInfo['rebateQty'] && $orderQty > ($rebateInfo['rebateQty'] - $beforeQty)) {
                                    //该订单部分算在返点协议里
                                    $hasRebateMoney = round($rebateInfo['rebate_amount'] * ($rebateInfo['rebateQty'] - $beforeQty), 2);
                                    $maxRefundMoney = ($orderDetail['totalPrice'] - $hasRebateMoney);
                                    $canRefundMoney = $maxRefundMoney;
                                    $unitRefundCurr = $this->currency->formatCurrencyPrice($maxRefundMoney / min($orderDetail['qty'], $rebateInfo['qty']), session('currency'));
                                    $maxRefundMoneyCurr = $this->currency->formatCurrencyPrice($maxRefundMoney, session('currency'));
                                    $hasRebateMoneyCurr = $this->currency->formatCurrencyPrice($hasRebateMoney, session('currency'));
                                    $orderTotalMoneyCurr = $this->currency->formatCurrencyPrice($orderDetail['totalPrice'], session('currency'));
                                    $data['tip_rebate_refund'] = sprintf($this->language->get('tip_rebate_over'), $unitRefundCurr);
                                    $data['msg_rebate_refund'] = sprintf($this->language->get('msg_rebate_over'), $orderQty, $orderTotalMoneyCurr, $rebateInfo['rebateQty'] - $beforeQty,
                                        $hasRebateMoneyCurr, $unitRefundCurr);
                                }

                            } else if ($rebateInfo['rebate_result'] == 5 && empty($rebateRequestInfo)) {
                                //该产品参与的返点协议已到期并达成，但Buyer还没有申请返点
                                $maxRefundMoneyCurr = $this->currency->formatCurrencyPrice($orderDetail['totalPrice'], session('currency'));
                                $needRebateMoney = $rebateInfo['rebate_amount'] * max(min($rebateInfo['rebateQty'] - $beforeQty, $orderQty), 0);
                                $needRebateMoneyCurr = $this->currency->formatCurrencyPrice($needRebateMoney, session('currency'));
                                $data['tip_rebate_refund'] = sprintf($this->language->get('tip_rebate'), $maxRefundMoneyCurr);
                                $canRefundMoney = $maxRefundMoney;
                                $maxRefundMoney = $maxRefundMoney - $needRebateMoney;
                                $maxRefundMoneyCurr = $this->currency->formatCurrencyPrice($maxRefundMoney, session('currency'));
                                $data['msg_rebate_refund'] = sprintf($this->language->get('msg_rebate_no_request'), $needRebateMoneyCurr, $maxRefundMoneyCurr);
                            } else if ($rebateInfo['rebate_result'] == 6) {
                                //该产品参与的返点协议已到期并达成，Buyer申请了返点但Seller还没有同意
                                $maxRefundMoneyCurr = $this->currency->formatCurrencyPrice($orderDetail['totalPrice'], session('currency'));
                                $needRebateMoney = $rebateInfo['rebate_amount'] * max(min($rebateInfo['rebateQty'] - $beforeQty, $orderQty), 0);
                                $needRebateMoneyCurr = $this->currency->formatCurrencyPrice($needRebateMoney, session('currency'));
                                $data['tip_rebate_refund'] = sprintf($this->language->get('tip_rebate'), $maxRefundMoneyCurr);
                                $canRefundMoney = $maxRefundMoney;
                                $maxRefundMoney = $maxRefundMoney - $needRebateMoney;
                                $maxRefundMoneyCurr = $this->currency->formatCurrencyPrice($maxRefundMoney, session('currency'));
                                $data['msg_rebate_refund'] = sprintf($this->language->get('msg_rebate_request'), $needRebateMoneyCurr, $maxRefundMoneyCurr);
                            } else if ($rebateInfo['rebate_result'] == 8) {
                                //该产品参与的返点协议已到期并达成，Buyer申请了返点但Seller拒绝了
                                $maxRefundMoneyCurr = $this->currency->formatCurrencyPrice($orderDetail['totalPrice'], session('currency'));
                                $needRebateMoney = $rebateInfo['rebate_amount'] * max(min($rebateInfo['rebateQty'] - $beforeQty, $orderQty), 0);
                                $needRebateMoneyCurr = $this->currency->formatCurrencyPrice($needRebateMoney, session('currency'));
                                $data['tip_rebate_refund'] = sprintf($this->language->get('tip_rebate'), $maxRefundMoneyCurr);
                                $canRefundMoney = $maxRefundMoney;
                                $maxRefundMoney = $maxRefundMoney - $needRebateMoney;
                                $maxRefundMoneyCurr = $this->currency->formatCurrencyPrice($maxRefundMoney, session('currency'));
                                $data['msg_rebate_refund'] = sprintf($this->language->get('msg_rebate_request_reject'), $needRebateMoneyCurr, $maxRefundMoneyCurr);
                            }
                        }
                        foreach ($orderItemInfos as &$orderItemInfo) {
                            if ($orderItemInfo['orderId'] == $orderDetail['order_id']) {
                                $flag = false;
                                $sellerItemInfos = array(
                                    'orderStatus' => $customerOrder['order_status'],
                                    "sellerName" => $orderDetail['screenname'],
                                    "sellerId" => $orderDetail['seller_id'],
                                    "qty" => $orderDetail['qty'],
                                    "transactionFee" => $orderDetail['transactionFee'] . '',
                                    "totalPrice" => $canRefundMoney . '',
                                    'tip_rebate_refund' => $data['tip_rebate_refund'],
                                    'msg_rebate_refund' => $data['msg_rebate_refund']
                                );
                                $orderItemInfo["items"][] = $sellerItemInfos;
                                break;
                            }
                        }
                        if ($flag) {
                            $sellerItemInfos = array(
                                'orderStatus' => $customerOrder['order_status'],
                                "sellerName" => $orderDetail['screenname'],
                                "sellerId" => $orderDetail['seller_id'],
                                "qty" => $orderDetail['qty'],
                                "transactionFee" => $orderDetail['transactionFee'] . '',
                                "totalPrice" => $canRefundMoney . '',
                                'tip_rebate_refund' => $data['tip_rebate_refund'],
                                'msg_rebate_refund' => $data['msg_rebate_refund']
                            );
                            $orderItemInfos[] = array(
                                "orderId" => $orderDetail['order_id'],
                                "items" => array($sellerItemInfos)
                            );
                        }
                        $margin_array[] = $orderDetail;
                        $orderDetails[] = $orderDetail;
                    }
                }
                $itemCodeItemInfo = array(
                    "itemCode" => $customerOrderLine['item_code'],
                    "salesOrderLineId" => $customerOrderLine['id'],
                    "items" => $orderItemInfos
                );
                $customerOrderLine['orderDetails'] = $orderDetails;
                $selectItems[] = $itemCodeItemInfo;
            }
            // ItemCode->OrderId->Store选择项
            $data['selectItems'] = json_encode($selectItems);
            $data['margin_array'] = json_encode($margin_array);
            $data['customerOrderLines'] = $customerOrderLines;
        }
        $data['is_margin'] = $is_margin;
        $this->response->setOutput($this->load->view('account/rma_management/add_rma_order_details', $data));
    }

    public function orderDetailsTable()
    {
        $this->load->language('account/rma_management');
        // 判断用户是否登录
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/rma_management', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }
        $data = array();
        $data['currency'] = session('currency');
        if (isset($this->request->get['customer_order_id'])) {
            $customer_order_id = trim($this->request->get['customer_order_id']);
        } else {
            $customer_order_id = null;
        }
        if (isset($this->request->get['count'])) {
            $count = $this->request->get['count'];
        } else {
            $count = null;
        }
        $data['count'] = $count;
        $buyerId = $this->customer->getId();
        $this->load->model('account/rma_management');
        // 顾客订单
        $customerOrder = $this->model_account_rma_management->getCustomerOrder($customer_order_id, $buyerId);
        // 去除Orders From 两边空格
        $customerOrder['orders_from'] = strtolower(trim($customerOrder['orders_from']));
        if (count($customerOrder) != 0) {
            $data['customerOrder'] = $customerOrder;
            // 顾客订单明细
            $customerOrderLines = $this->model_account_rma_management->getCustomerOrderLineByHeaderId($customerOrder['id']);
            $data['customerOrderLines'] = $customerOrderLines;
        }
        // 获取退货理由
        $rmaReason = $this->model_account_rma_management->getRmaReason($customerOrder['order_status']);
        $data['rmaReason'] = $rmaReason;
        //获取rma_id_temp
        $rmaIdTemp = $this->model_account_rma_management->getRmaIdTemp();
        if (!empty($rmaIdTemp)) {
            $data['rma_order_id'] = $rmaIdTemp->rma_order_id;
        }
        $this->response->setOutput($this->load->view('account/rma_management/rma_order_details_table', $data));
    }

    public function saveRmaRequestCheckAddress()
    {

        $post = request()->post();
        $salesOrderId = intval($post['sales_order_id'] ?? 0);
        $shipToAddress = trim($post['re_ship_to_address'] ?? "");
        $shipToState = trim($post['re_ship_to_state'] ?? "");
        $customerSalesOrder = CustomerSalesOrder::query()->where(['id' => $salesOrderId])->first();
        if (empty($customerSalesOrder)) {
            return $this->response->json([
                'code' => -2,
                'msg' => "sales_order_id error"
            ]);
        }

        $shipToAddressLen = StringHelper::stringCharactersLen($shipToAddress);
        $len = $shipToAddressLen;  // 默认就取传入的长度, 防止下面没有判断的
        if ($customerSalesOrder->order_mode == CustomerSalesOrderMode::DROP_SHIPPING) {
            $countryId = $this->customer->getCountryId();
            $isLTL = ($countryId == AMERICAN_COUNTRY_ID) ? app(CustomerSalesOrderRepository::class)->isLTL($this->customer->getCountryId(),
                app(CustomerSalesOrderRepository::class)->getItemCodesByHeaderId($salesOrderId)) : false;

            if (!$isLTL) {
                if ($countryId == AMERICAN_COUNTRY_ID) {
                    $len = $this->config->get('config_b2b_address_len_us1');
                    if (AddressHelper::isPoBox($shipToAddress)) {
                        $result['code'] = -1;
                        $result['msg'] = [
                            'field_name' => 're_ship_to_address',
                            'error' => 'Ship-To Address in P.O.BOX doesn\'t support delivery,Please see the instructions.'
                        ];
                        return $this->response->json($result);
                    }

                    if (AddressHelper::isRemoteRegion($shipToState)) {
                        $result['code'] = -1;
                        $result['msg'] = [
                            'field_name' => 're_ship_to_state',
                            'error' => 'ShipToState in PR, AK, HI, GU, AA, AE, AP doesn\'t support delivery,Please see the instructions'
                        ];
                        return $this->response->json($result);
                    }
                } else if ($countryId == UK_COUNTRY_ID) {
                    $len = $this->config->get('config_b2b_address_len_uk');
                } else if ($countryId == DE_COUNTRY_ID) {
                    $len = $this->config->get('config_b2b_address_len_de');
                } else if ($countryId == JAPAN_COUNTRY_ID) {
                    $len = $this->config->get('config_b2b_address_len_jp');
                }
            } else {
                $len = $this->config->get('config_b2b_address_len');
            }
        } else if ($customerSalesOrder->order_mode == CustomerSalesOrderMode::PICK_UP) {
            $len = $this->config->get('config_b2b_address_len');
        }
        if ($shipToAddressLen > $len) {
            $result['code'] = -1;
            $result['msg'] = [
                'field_name' => 're_ship_to_address',
                'error' => "Ship-To Address: maximum length is {$len} characters"
            ];
            return $this->response->json($result);
        }
        return $this->response->json([
            'code' => 0,
            'msg' => 'success'
        ]);
    }

    public function saveRmaRequest()
    {
        // 递归去除request的参数的非法字符
        trim_strings($this->request->post);
        $this->load->model('account/rma_management');
        $this->load->language('account/rma_management');
        $result = [];
        // 默认采用redis锁方式
        $lock = Locker::rma('rmaLock');
        if (!$lock->acquire(true)) { // 采用阻塞方式
            $result = ['error' => [['errorMsg' => 'Operation failed.']]];
            goto end;
        }
        $checkData = $this->checkRmaData();
        if (count($checkData['error']) > 0) {
            $result = ['error' => $checkData['error']];
            goto end;
        }
        $connection = $this->orm->getConnection();
        try {
            $connection->beginTransaction();
            // 用来判断是否只是单独申请refund  reship  all
            // 获取参数下标
            $index = $this->request->post['index'];
            $rmaTypeArrays = [0];
            // 默认rma状态ID
            define("DEFAULT_RMA_STATUS_ID", 1);
            $buyerId = $this->customer->getId();
            $yzc_order_id_number = $this->sequence->getYzcOrderIdNumber();
            $customerOrderMap = array();
            $customerOrderLineMap = array();
            foreach ($index as $i) {
                // 1. 获取itemCode,orderId,sellerId,rmaQty,reason,comments
                $rmaOrderId = $this->request->post['rmaOrderId' . $i];
                $itemCode = trim($this->request->post['itemCode' . $i]);
                $orderId = (int)$this->request->post['orderId' . $i];
                $customer_order_id = $this->request->post['customer_order_id'];
                $sellerId = (int)$this->request->post['sellers' . $i];
                $rmaQty = (int)$this->request->post['rmaQty' . $i];
                // 获取ASIN
                if (isset($this->request->post['asin' . $i])) {
                    $asin = $this->request->post['asin' . $i];
                } else {
                    $asin = null;
                }
                $reason = $this->request->post['reason' . $i] == '' ? null : (int)$this->request->post['reason' . $i];
                $comments = $this->request->post['comments' . $i];
                $associated = $checkData['associated'][$i];
                $singleCouponAmount = $singleCampaignAmount = 0;
                $alreadyAgreedRmaCount = app(RamRepository::class)
                    ->calculateOrderProductApplyedRmaNum($this->customer->getId(), $customer_order_id, $associated['order_product_id']);
                if ($alreadyAgreedRmaCount == 0) {
                    $singleCouponAmount = $associated['coupon_amount'];
                    $singleCampaignAmount = $associated['campaign_amount'];
                }

                // 2. 插入oc_yzc_rma_order
                $rmaOrder = array(
                    "rma_order_id" => $rmaOrderId,
                    "order_id" => $orderId,
                    "from_customer_order_id" => $customer_order_id,
                    "seller_id" => $sellerId,
                    "buyer_id" => $buyerId,
                    "admin_status" => null,
                    "seller_status" => DEFAULT_RMA_STATUS_ID,
                    "cancel_rma" => false,
                    "solve_rma" => false,
                    "create_user_name" => $buyerId,
                );
                $rmaOrder = $this->model_account_rma_management->addRmaOrder($rmaOrder);
                $rmaId = $rmaOrder->id;
                // 3.判断有无上传rma文件
                if (!empty(request()->file('files' . $i))) {
                    // 有文件上传，将文件保存服务器上并插入数据到表oc_yzc_rma_file
                    $files = request()->file('files' . $i);
                    // 上传RMA文件，以用户ID进行分类
                    for ($j = 0; $j < count($files); $j++) {
                        /** @var UploadedFile $file */
                        $file = $files[$j];
                        if ($file->isValid()) {
                            // 替换为新的命名规则
                            $filename = date('Ymd') . '_'
                                . md5((html_entity_decode($file->getClientOriginalName(), ENT_QUOTES, 'UTF-8') . micro_time()))
                                . '.' . $file->getClientOriginalExtension();

                            //上传oss
                            StorageCloud::rmaFile()->writeFile($file, $buyerId, $filename);
                            // 插入文件数据
                            $rmaFile = [
                                'rma_id' => $rmaId,
                                'file_name' => $file->getClientOriginalName(),
                                'size' => $file->getSize(),
                                'file_path' => $buyerId . DIRECTORY_SEPARATOR . $filename,
                                'buyer_id' => $buyerId
                            ];
                            $this->model_account_rma_management->addRmaFile($rmaFile);
                        }
                    }
                }
                // 4.插入RMA明细数据，oc_yzc_rma_order_product
                // 4.1 判断RMA Type (1.仅重发、2.仅退款、3.即重发又退款)
                $rmaTypes = $this->request->post['rma_type' . $i];
                $rmaType = null;
                if (count($rmaTypes) > 1) {
                    // 选择了两个以上类型
                    if ($rmaTypes[0] == 1 && $rmaTypes[1] == 2) {
                        $rmaType = 3;
                    }
                } else {
                    $rmaType = $rmaTypes[0];
                }
                $rmaTypeArrays[] = (int)$rmaType;
                // 申请退款金额
                $refundAmount = null;
                if ($rmaType == 2 || $rmaType == 3) {
                    if (isset($this->request->post['refund_amount' . $i]) && trim($this->request->post['refund_amount' . $i]) != '') {
                        $refundAmount = (float)$this->request->post['refund_amount' . $i];
                    }
                }
                $rmaOrderProduct = array(
                    "rma_id" => $rmaId,
                    "product_id" => $associated['product_id'],
                    "item_code" => $itemCode,
                    "quantity" => $rmaQty,
                    "reason_id" => $reason,
                    'asin' => $asin,
                    "order_product_id" => $associated['order_product_id'],
                    "comments" => $comments,
                    "rma_type" => $rmaType,
                    "apply_refund_amount" => $refundAmount,
                    'coupon_amount' => 0,
                    'campaign_amount' => 0,
                );
                //退款2 + 退款重发3  才考虑退优惠券
                if (in_array($rmaType, RmaApplyType::getRefund())) {
                    $rmaOrderProduct['coupon_amount'] = $singleCouponAmount;
                    $rmaOrderProduct['campaign_amount'] = $singleCampaignAmount;
                }
                $this->model_account_rma_management->addRmaOrderProduct($rmaOrderProduct);
                // 插入重发单数据
                if ($rmaType == 1 || $rmaType == 3) {
                    $sales_order_id = $associated['sales_order_id'];
                    $sales_order_line_id = $associated['sales_order_line_id'];
                    // 获取原始订单
                    if (!isset($customerOrderMap[$sales_order_id])) {
                        $customerOrderMap[$sales_order_id] = $this->model_account_rma_management->getCustomerOrderById($sales_order_id);
                    }
                    // 获取原始订单明细
                    if (!isset($customerOrderLineMap[$sales_order_line_id])) {
                        $customerOrderLineMap[$sales_order_line_id] = $this->model_account_rma_management->getCustomerOrderLineById($sales_order_line_id);
                    }
                    // 根据sales_order_id统计有几个重发单
                    $reOrderCount = $this->model_account_rma_management->getReorderCountByCustomerOrderId($sales_order_id);
                    // 订单头表数据
                    $yzc_order_id_number++;
                    $customerSalesReorder = array(
                        "rma_id" => $rmaId,
                        "yzc_order_id" => "YC-" . $yzc_order_id_number,
                        "sales_order_id" => $sales_order_id,
                        "reorder_id" => $customerOrderMap[$sales_order_id]->order_id . '-' . 'R' . '-' . ($reOrderCount + 1),
                        "reorder_date" => date("Y-m-d H:i:s", time()),
                        "email" => $this->request->post['re_ship_to_email' . $i],
                        "ship_name" => $this->request->post['re_ship_to_name' . $i],
                        "ship_address" => $this->request->post['re_ship_to_address' . $i],
                        "ship_city" => $this->request->post['re_ship_to_city' . $i],
                        "ship_state" => $this->request->post['re_ship_to_state' . $i],
                        "ship_zip_code" => $this->request->post['re_ship_to_postal_code' . $i],
                        "ship_country" => $this->request->post['re_ship_to_country' . $i],
                        "ship_phone" => $this->request->post['re_ship_to_phone' . $i],
                        "ship_method" => isset($this->request->post['re_ship_to_service' . $i]) ? $this->request->post['re_ship_to_service' . $i] : null,
                        "ship_service_level" => isset($this->requst->post['re_ship_to_service_level' . $i]) ? $this->requst->post['re_ship_to_service_level' . $i] : null,
                        "ship_company" => $customerOrderMap[$sales_order_id]->ship_company,
                        "store_name" => $customerOrderMap[$sales_order_id]->store_name,
                        "store_id" => $customerOrderMap[$sales_order_id]->store_id,
                        "buyer_id" => $buyerId,
                        "order_status" => 1,
                        "sell_manager" => $customerOrderMap[$sales_order_id]->sell_manager,
                        "create_user_name" => $this->customer->getId(),
                        "create_time" => date("Y-m-d H:i:s", time()),
                        "program_code" => PROGRAM_CODE
                    );
                    // 保存重发订单头表数据
                    $customerSalesReorder = $this->model_account_rma_management->addReOrder($customerSalesReorder);
                    // 保存重发订单明细表
                    $customerSalesReorderLine = array(
                        "reorder_header_id" => $customerSalesReorder->id,
                        "line_item_number" => 1,
                        "product_name" => $customerOrderLineMap[$sales_order_line_id]->product_name,
                        "qty" => $this->request->post['re_ship_to_qty' . $i],
                        "item_code" => $itemCode,
                        "product_id" => $associated['product_id'],
                        "image_id" => $customerOrderLineMap[$sales_order_line_id]->image_id == null ? 1 : $customerOrderLineMap[$sales_order_line_id]->image_id,
                        "seller_id" => $associated['seller_id'],
                        "item_status" => 1,
                        "create_user_name" => $buyerId,
                        "create_time" => date("Y-m-d H:i:s", time()),
                        "program_code" => PROGRAM_CODE
                    );
                    $customerSalesReorderLine = $this->model_account_rma_management->addReOrderLine($customerSalesReorderLine);
                }
                $this->rmaCommunication($rmaId);
            }
            $this->sequence->updateYzcOrderIdNumber($yzc_order_id_number);
            $result['status'] = 'success';
            $result['successUrl'] = $this->url->link('account/rma_management');
            // 处理返回数据
            $rmaTypeInfos = array_sum(array_unique($rmaTypeArrays));
            // result 中的rmaType只会有3个值 1-重发 2-退款 3-重发又退款
            $result['rma_type'] = $rmaTypeInfos >= 3 ? 3 : $rmaTypeInfos;
            $connection->commit();
        } catch (Exception $exception) {
            $connection->rollBack();
            $result['error'] = [['errorMsg' => $exception->getMessage()]];
            Logger::error($exception);
        }
        end:
        $lock->release();
        return $this->response->json($result);
    }

    // 取消rma
    public function cancelRma()
    {
        // 获取RMAID
        $rmaId = $this->request->post['rmaId'];
        $this->load->model('account/rma_management');
        // 更新RMA状态
        $result = $this->model_account_rma_management->cancelRmaOrder($rmaId);
        if (isset($result['error'])) {
            goto end;
        }
        $result = array();
        $result['status'] = "success";
        $result['status_name'] = '<span style="color: red">Canceled</span>';
        end:
        $this->response->returnJson($result);
    }

    private function checkRmaData()
    {
        $index = $this->request->post['index'];
        $error = array();
        $associatedArr = array();
        $itemCodeOrderIdStoreArr = array();
        $rmaQtyArr = array();
        $reshipmentQtyArr = array();
        $indexArr = array();
        // 销售订单ID
        $sales_order_id = $this->request->post['sales_order_id'];
        $buyer_id = $this->customer->getId();
        // orderFrom
        $orderFrom = $this->request->post['orderFrom'];
        if (strtolower($orderFrom) == 'amazon' || strtolower($orderFrom) == 'amazonshz') {
            $isAmazon = true;
        } else {
            $isAmazon = false;
        }
        foreach ($index as $i) {
            // 1.校验ItemCode+OrderId+Store 是否有重复的
            if (isset($this->request->post['itemCode' . $i]) && isset($this->request->post['orderId' . $i]) && isset($this->request->post['sellers' . $i])) {
                $ios = $this->request->post['itemCode' . $i] . $this->request->post['orderId' . $i] . $this->request->post['sellers' . $i];
                if (!isset($indexArr[$ios])) {
                    $indexArr[$ios] = $i;
                }
                // 获取rma_type
                $rmaType = null;
                if (!isset($this->request->post['rma_type' . $i])) {
                    // 没有选择RMA类型
                    if (isset($this->request->post['orderStatus']) && $this->request->post['orderStatus'] == 'Cancelled') {
                        $error[] = array(
                            "id" => array('collapseOne' . $i, 'collapseTwo' . $i),
                            "href" => 'collapseOne' . $i,
                            "displayType" => 1,
                            "errorMsg" => "Please select refund option."
                        );
                    } else {
                        $error[] = array(
                            "id" => array('collapseOne' . $i, 'collapseTwo' . $i),
                            "href" => 'collapseOne' . $i,
                            "displayType" => 1,
                            "errorMsg" => "Please select at least one reshipment or refund option."
                        );
                    }
                } else {
                    // 校验退款金额
                    $rmaTypes = $this->request->post['rma_type' . $i];
                    if (count($rmaTypes) > 1) {
                        // 选择了两个以上类型
                        if ($rmaTypes[0] == 1 && $rmaTypes[1] == 2) {
                            $rmaType = 3;
                        }
                    } else {
                        $rmaType = $rmaTypes[0];
                    }
                    if ($rmaType == 2 || $rmaType == 3) {
                        if (!isset($this->request->post['refund_amount' . $i]) || trim($this->request->post['refund_amount' . $i]) == '') {
                            $error[] = array(
                                "id" => 'refund_amount' . $i,
                                "href" => 'refund_amount' . $i,
                                "displayType" => 2,
                                "errorMsg" => "Please fill in the refund amount of the application."
                            );
                        } else {
                            $totalPrice = $this->request->post['totalPrice' . $i];
                            if ($this->request->post['refund_amount' . $i] > $totalPrice) {
                                $error[] = array(
                                    "id" => 'refund_amount' . $i,
                                    "href" => 'refund_amount' . $i,
                                    "displayType" => 2,
                                    "errorMsg" => "The amount entered exceeds the total amount of the order details!"
                                );
                            } else if ((double)$this->request->post['refund_amount' . $i] == 0) {
                                $error[] = array(
                                    "id" => 'refund_amount' . $i,
                                    "href" => 'refund_amount' . $i,
                                    "displayType" => 2,
                                    "errorMsg" => "The refund amount can't fill in 0."
                                );
                            }
                        }
                    }
                }
                // 校验数量
                // 获取销售订单明细所对应的强绑定数据
                $order_id = $this->request->post['orderId' . $i];
                $item_code = $this->request->post['itemCode' . $i];
                $seller_id = $this->request->post['sellers' . $i];
                $associated = $this->model_account_rma_management->getOrderAssociated(
                    $sales_order_id, $item_code, $buyer_id, $order_id, $seller_id
                );

                $associatedArr[$i] = $associated;
                $rmaQty = null;
                if (isset($this->request->post['rmaQty' . $i])) {
                    $rmaQty = $this->request->post['rmaQty' . $i];
                    if ($rmaQty == '') {
                        $error[] = array(
                            "id" => "rmaQty" . $i,
                            "href" => 'rmaQty' . $i,
                            "displayType" => 1,
                            "errorMsg" => $this->language->get('error_enter_rma_qty')
                        );
                    } else {
                        // 统计rmaQty总数
                        if (isset($rmaQtyArr[$ios])) {
                            $rmaQtyArr[$ios] += (int)$rmaQty;
                        } else {
                            $rmaQtyArr[$ios] = (int)$rmaQty;
                        }
                        // 单一总数不能大于购买数
                        if ($rmaQty > $associated['qty']) {
                            $error[] = array(
                                "id" => "rmaQty" . $i,
                                "href" => 'rmaQty' . $i,
                                "displayType" => 1,
                                "errorMsg" => $this->language->get('error_enter_ship_to_qty_max')
                            );
                        }
                    }
                }

                if (isset($this->request->post['rma_type' . $i])) {
                    if ($rmaType == 1 || $rmaType == 3) {
                        if (isset($this->request->post['re_ship_to_qty' . $i])) {
                            $reQty = $this->request->post['re_ship_to_qty' . $i];
                            if ($reQty > $associated['qty']) {
                                $error[] = array(
                                    "id" => "re_ship_to_qty" . $i,
                                    "href" => 're_ship_to_qty' . $i,
                                    "displayType" => 2,
                                    "errorMsg" => $this->language->get('error_enter_ship_to_qty_max')
                                );
                            }
                            // 统计reshipment总数
                            if (isset($reshipmentQtyArr[$ios])) {
                                $reshipmentQtyArr[$ios] += (int)$reQty;
                            } else {
                                $reshipmentQtyArr[$ios] = (int)$reQty;
                            }
                        }
                    }
                }
            }
            // Amazon 订单必填项ASIN
            // kimi 没有删除是因为还不能判断
//            if ($isAmazon) {
//                if ((!isset($this->request->post['asin' . $i])) || trim($this->request->post['asin' . $i]) == '') {
//                    $error[] = array(
//                        "id" => array("asin" . $i),
//                        "href" => 'asin' . $i,
//                        "displayType" => 1,
//                        "errorMsg" => $this->language->get('error_enter_asin')
//                    );
//                }
//            }
            // 上传附件图片
            if (!isset($this->request->files['files' . $i])) {
                $error[] = array(
                    "id" => "appFile" . $i,
                    "href" => 'appFile' . $i,
                    "displayType" => 1,
                    "errorMsg" => "Please upload an attachment image!"
                );
            }
            // 校验理由，理由为必填项
            if ((!isset($this->request->post['reason' . $i])) || trim($this->request->post['reason' . $i]) == '') {
                $error[] = array(
                    "id" => "reason" . $i,
                    "href" => 'reason' . $i,
                    "displayType" => 2,
                    "errorMsg" => $this->language->get('error_enter_reason')
                );
            }
            // 2.校验必填项(重发)
            if (isset($this->request->post['rma_type' . $i])) {
                if ((count($this->request->post['rma_type' . $i]) > 1 && $this->request->post['rma_type' . $i][0] == '1') || $this->request->post['rma_type' . $i][0] == '1') {
                    // 2.1 收货人姓名
                    if ((!isset($this->request->post['re_ship_to_name' . $i])) || trim($this->request->post['re_ship_to_name' . $i]) == '') {
                        $error[] = array(
                            "id" => "re_ship_to_name" . $i,
                            "href" => 're_ship_to_name' . $i,
                            "displayType" => 2,
                            "errorMsg" => $this->language->get('error_enter_ship_to_name')
                        );
                    }
                    // 2.2 收货人邮箱
                    if ((!isset($this->request->post['re_ship_to_email' . $i])) || trim($this->request->post['re_ship_to_email' . $i]) == '') {
                        $error[] = array(
                            "id" => "re_ship_to_email" . $i,
                            "href" => 're_ship_to_email' . $i,
                            "displayType" => 2,
                            "errorMsg" => $this->language->get('error_enter_ship_to_email')
                        );
                    }
                    // 2.3 收货人国家
                    if ((!isset($this->request->post['re_ship_to_country' . $i])) || trim($this->request->post['re_ship_to_country' . $i]) == '') {
                        $error[] = array(
                            "id" => "re_ship_to_country" . $i,
                            "href" => "re_ship_to_country" . $i,
                            "displayType" => 2,
                            "errorMsg" => $this->language->get('error_enter_ship_to_country')
                        );
                    }
                    // 2.4 收货人城市
                    if ((!isset($this->request->post['re_ship_to_city' . $i])) || trim($this->request->post['re_ship_to_city' . $i]) == '') {
                        $error[] = array(
                            "id" => "re_ship_to_city" . $i,
                            "href" => "re_ship_to_city" . $i,
                            "displayType" => 2,
                            "errorMsg" => $this->language->get('error_enter_ship_to_city')
                        );
                    }
                    // 2.5 收货数量
                    if (!isset($this->request->post['re_ship_to_qty' . $i]) || trim($this->request->post['re_ship_to_qty' . $i]) == '') {
                        $error[] = array(
                            "id" => "re_ship_to_qty" . $i,
                            "href" => "re_ship_to_qty" . $i,
                            "displayType" => 2,
                            "errorMsg" => $this->language->get('error_enter_ship_to_qty')
                        );
                    } else if ((int)$this->request->post['re_ship_to_qty' . $i] == 0) {
                        $error[] = array(
                            "id" => "re_ship_to_qty" . $i,
                            "href" => "re_ship_to_qty" . $i,
                            "displayType" => 2,
                            "errorMsg" => "The reshipped quantity can't fill in 0"
                        );
                    }
                    // 2.6 收货人电话
                    if ((!isset($this->request->post['re_ship_to_phone' . $i])) || trim($this->request->post['re_ship_to_phone' . $i]) == '') {
                        $error[] = array(
                            "id" => "re_ship_to_phone" . $i,
                            "href" => "re_ship_to_phone" . $i,
                            "displayType" => 2,
                            "errorMsg" => $this->language->get('error_enter_ship_to_phone')
                        );
                    }
                    // 2.7 收货人邮编
                    if ((!isset($this->request->post['re_ship_to_postal_code' . $i])) || trim($this->request->post['re_ship_to_postal_code' . $i]) == '') {
                        $error[] = array(
                            "id" => "re_ship_to_postal_code" . $i,
                            "href" => "re_ship_to_postal_code" . $i,
                            "displayType" => 2,
                            "errorMsg" => $this->language->get('error_enter_ship_to_postal_code')
                        );
                    }
                    // 2.8 收货人 州/地区
                    if ((!isset($this->request->post['re_ship_to_state' . $i])) || trim($this->request->post['re_ship_to_state' . $i]) == '') {
                        $error[] = array(
                            "id" => "re_ship_to_state" . $i,
                            "href" => "re_ship_to_state" . $i,
                            "displayType" => 2,
                            "errorMsg" => $this->language->get('error_enter_ship_to_state')
                        );
                    }
                    // 2.9 收获地址
                    if ((!isset($this->request->post['re_ship_to_address' . $i])) || trim($this->request->post['re_ship_to_address' . $i]) == '') {
                        $error[] = array(
                            "id" => "re_ship_to_address" . $i,
                            "href" => "re_ship_to_address" . $i,
                            "displayType" => 2,
                            "errorMsg" => $this->language->get('error_enter_ship_to_address')
                        );
                    }
                    // 3. 校验文件上传是否正确
                    if (isset($this->request->files['files' . $i])) {
                        $files = $this->request->files['files' . $i];
                        for ($j = 0; $j < count($files['name']); $j++) {
                            if ($files['error'][$j] != 0) {
                                $errorMsg = "";
                                switch ($files['tmp_name'][$j]) {
                                    // UPLOAD_ERR_INI_SIZE
                                    case 1:
                                        $errorMsg = "Upload file size exceeds maximum limit.";
                                        break;
                                    // UPLOAD_ERR_FORM_SIZE
                                    case 2:
                                        $errorMsg = "Upload file size exceeds maximum limit.";
                                        break;
                                    // UPLOAD_ERR_PARTIAL
                                    case 3:
                                        $errorMsg = "Only part of the file is uploaded.";
                                        break;
                                    // UPLOAD_ERR_NO_FILE
                                    case 4:
                                        $errorMsg = "No files were uploaded.";
                                        break;
                                    // UPLOAD_ERR_NO_TMP_DIR
                                    case 6:
                                        $errorMsg = "Temporary folder not found.";
                                        break;
                                    // UPLOAD_ERR_CANT_WRITE
                                    case 7:
                                        $errorMsg = "File write failed.";
                                        break;
                                }
                                if ($errorMsg != "") {
                                    $error[] = array(
                                        "id" => "upload_file" . $i,
                                        "href" => "upload_file" . $i,
                                        "displayType" => 1,
                                        "errorMsg" => $errorMsg . " NO. " . ($j + 1)
                                    );
                                }
                            }
                        }
                    }
                }
            }
        }
        //查询是否存在保证金头款商品
        $get_post_rma_data = array();
        foreach ($index as $i) {
            $get_post_rma_data = array(
                'item_code' => $this->request->post["itemCode$i"],
                'orderId' => $this->request->post["orderId$i"]
            );
            $get_agreement_product = $this->model_account_rma_management->get_agreement_product($get_post_rma_data);
            if ($get_agreement_product) {   //存在保证金头款商品
                $error[] = array(
                    "id" => array("selectItemCode" . $i, "selectOrderId" . $i, "selectSellers" . $i),
                    "href" => 'selectItemCode' . $i,
                    "displayType" => 1,
                    "errorMsg" => "ItemCode:" . $this->request->post['itemCode' . $i] . " OrderId:" . $this->request->post['orderId' . $i] . " Store:&nbsp;&nbsp;" . $this->language->get('error_margin_not_retrun'),
                );
            }
        }

        // 校验数量
        if (count($rmaQtyArr) > 0) {
            foreach ($rmaQtyArr as $key => $value) {
                // 获取强绑定数据
                $i = $indexArr[$key];
                $associated = $associatedArr[$i];
                // 查询历史该退货的数量
                $qty = $associated['qty'];
                if ($value > $qty) {
                    $sellerName = $this->model_account_rma_management->getStoreNameBySellerId($this->request->post['sellers' . $i]);
                    $error[] = array(
                        "id" => array("selectItemCode" . $i, "selectOrderId" . $i, "selectSellers" . $i),
                        "href" => 'selectItemCode' . $i,
                        "displayType" => 1,
                        "errorMsg" => "ItemCode:" . $this->request->post['itemCode' . $i] . " OrderId:" . $this->request->post['orderId' . $i] . " Store:" . $sellerName['sellerName'] . " &nbsp;&nbsp;The total number of applications exceeds the number of purchases!"
                    );
                }
            }
        }
        if (count($reshipmentQtyArr) > 0) {
            foreach ($reshipmentQtyArr as $key => $value) {
                // 获取强绑定数据
                $i = $indexArr[$key];
                $associated = $associatedArr[$i];
                // 查询历史该退货的数量
                $qty = $associated['qty'];
                if ($value > $qty) {
                    $sellerName = $this->model_account_rma_management->getStoreNameBySellerId($this->request->post['sellers' . $i]);
                    $error[] = array(
                        "id" => "re_ship_to_qty" . $i,
                        "href" => 're_ship_to_qty' . $i,
                        "displayType" => 2,
                        "errorMsg" => "ItemCode:" . $this->request->post['itemCode' . $i] . " OrderId:" . $this->request->post['orderId' . $i] . " Store:" . $sellerName['sellerName'] . " &nbsp;&nbsp;The total number of reshipment quantity exceeds the number of purchases!"
                    );
                }
            }
        }
        $result = array(
            'error' => $error,
            'associated' => $associatedArr
        );
        return $result;
    }

    private function rmaCommunication($rmaId)
    {
        $this->load->model('account/notification');
        $this->load->model('customerpartner/rma_management');
        /** @var ModelAccountNotification $modelAccountNotification */
        $modelAccountNotification = $this->model_account_notification;
        // 消息提醒
//        $modelAccountNotification->addRmaActivity($rmaId);
        // 站内信
        $communicationInfo = $this->model_customerpartner_rma_management->getCommunicationInfoOrm($rmaId);
        if (!empty($communicationInfo)) {
            $message = '<table   border="0" cellspacing="0" cellpadding="0" >';
            $message .= '<tr><th align="left">RMA ID:</th><td>' . $communicationInfo->rma_order_id . '</td></tr> ';
            $message .= '<tr><th align="left">Order ID:</th><td>' . $communicationInfo->order_id . '</td></tr>';
            $message .= '<tr><th align="left">MPN:</th><td>' . $communicationInfo->mpn . '</td></tr>';
            $message .= '<tr><th align="left">Item Code:</th><td>' . $communicationInfo->sku . '</td></tr>';
            if ($communicationInfo->rma_type == 1) {
                $message .= '<tr><th align="left">Applied for Reshipment：</th><td>Yes</td></tr>';
                $message .= '<tr><th align="left">Applied for Refund：</th><td>No</td></tr>';
            } else if ($communicationInfo->rma_type == 2) {
                $message .= '<tr><th align="left">Applied for Reshipment：</th><td>No</td></tr>';
                $message .= '<tr><th align="left">Applied for Refund：</th><td>Yes</td></tr>';
            } else if ($communicationInfo->rma_type == 3) {
                $message .= '<tr><th align="left">Applied for Reshipment：</th><td>Yes</td></tr>';
                $message .= '<tr><th align="left">Applied for Refund：</th><td>Yes</td></tr>';
            }
            $message .= '</table>';
            //$this->communication->saveCommunication('RMA Request', $message, $communicationInfo->seller_id, $communicationInfo->buyer_id, 0);

            // 新消息中心
            $subject = 'RMA Request (RMA ID:' . $communicationInfo->rma_order_id . ')';
            $this->load->model('message/message');

            // 6774 修改为批量发送
            $receiverIds[] = $communicationInfo->seller_id;
            if ($communicationInfo->original_seller_id) {//如果seller_id是包销店铺，也给原店铺发一条消息提醒
                $receiverIds[] = $communicationInfo->original_seller_id;
            }
            $this->model_message_message->addSystemMessageToBuyer('rma', $subject, $message, $receiverIds);

        }
    }

    /**
     * RMA,重发单信息的展示
     */
    public function reshipment()
    {
        // 判断用户是否登录
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/rma_management', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }
        // 加载语言层
        $this->load->language('account/rma_management');
        $this->load->model('account/rma_management');

        // 获取Store下拉选
        $data['stores'] = $this->model_account_rma_management->getStoreByBuyerId($this->customer->getId());
        // 获取RMA状态下拉选
        $data['rmaStatus'] = $this->model_account_rma_management->getRmaStatus();
        $this->getReshipmentList($data);
        $data['continue'] = $this->url->link('account/account', '', true);
        $data['tickets'] = $this->load->controller('common/ticket');
        $data['now'] = time();
        $data['country'] = session('country');
        $this->response->setOutput($this->load->view('account/rma_management/reshipment_info', $data));
    }

    /**
     * 获取重发单数据
     * @param $data
     */
    public function getReshipmentList(&$data)
    {
        $this->load->language('account/rma_management');
        $this->load->model('account/rma_management');
        $this->load->model('catalog/product');
        $this->load->model('tool/image');
        $url = "";
        if (isset($this->request->get['filter_reshipment_id'])) {
            $filter_reshipment_id = $this->request->get['filter_reshipment_id'];
            $url .= "&filter_reshipment_id=" . $filter_reshipment_id;
        } else {
            $filter_reshipment_id = null;
        }
        if (isset($this->request->get['filter_reshipment_seller_status'])) {
            $filter_reshipment_seller_status = $this->request->get['filter_reshipment_seller_status'];
            $url .= "&filter_reshipment_seller_status=" . $filter_reshipment_seller_status;
        } else {
            $filter_reshipment_seller_status = -1;
        }

        if (isset($this->request->get['filter_reshipment_item_code'])) {
            $filter_reshipment_item_code = $this->request->get['filter_reshipment_item_code'];
            $url .= "&filter_reshipment_item_code=" . $filter_reshipment_item_code;
        } else {
            $filter_reshipment_item_code = null;
        }
        if (isset($this->request->get['filter_reshipment_sales_order_id'])) {
            $filter_reshipment_sales_order_id = $this->request->get['filter_reshipment_sales_order_id'];
            $url .= "&filter_reshipment_sales_order_id=" . $filter_reshipment_sales_order_id;
        } else {
            $filter_reshipment_sales_order_id = null;
        }
        if (isset($this->request->get['filter_reshipment_order_status'])) {
            $filter_reshipment_order_status = $this->request->get['filter_reshipment_order_status'];
            $url .= "&filter_reshipment_order_status=" . $filter_reshipment_order_status;
        } else {
            $filter_reshipment_order_status = null;
        }

        if (isset($this->request->get['filter_reshipment_store'])) {
            $filter_reshipment_store = $this->request->get['filter_reshipment_store'];
            $url .= "&filter_reshipment_store=" . $filter_reshipment_store;
        } else {
            $filter_reshipment_store = null;
        }

        if (isset($this->request->get['filter_reshipment_rma_id'])) {
            $filter_reshipment_rma_id = $this->request->get['filter_reshipment_rma_id'];
            $url .= "&filter_reshipment_rma_id=" . $filter_reshipment_rma_id;
        } else {
            $filter_reshipment_rma_id = null;
        }

        if (isset($this->request->get['filter_reshipment_applyDateFrom'])) {
            $filter_reshipment_applyDateFrom = $this->request->get['filter_reshipment_applyDateFrom'];
            $url .= "&filter_reshipment_applyDateFrom=" . $filter_reshipment_applyDateFrom;
        } else {
            $filter_reshipment_applyDateFrom = null;
        }

        if (isset($this->request->get['filter_reshipment_applyDateTo'])) {
            $filter_reshipment_applyDateTo = $this->request->get['filter_reshipment_applyDateTo'];
            $url .= "&filter_reshipment_applyDateTo=" . $filter_reshipment_applyDateTo;
        } else {
            $filter_reshipment_applyDateTo = null;
        }

        $data['filter_reshipment_id'] = $filter_reshipment_id;
        $data['filter_reshipment_seller_status'] = $filter_reshipment_seller_status;
        $data['filter_reshipment_item_code'] = $filter_reshipment_item_code;
        $data['filter_reshipment_sales_order_id'] = $filter_reshipment_sales_order_id;
        $data['filter_reshipment_order_status'] = $filter_reshipment_order_status;
        $data['filter_reshipment_store'] = $filter_reshipment_store;
        $data['filter_reshipment_rma_id'] = $filter_reshipment_rma_id;
        $data['filter_reshipment_applyDateFrom'] = $filter_reshipment_applyDateFrom;
        $data['filter_reshipment_applyDateTo'] = $filter_reshipment_applyDateTo;

        /* 分页 */
        if (isset($this->request->get['page_num'])) {
            $page_num = intval($this->request->get['page_num']);
        } else {
            $page_num = 1;
        }
        $data['page_num'] = $page_num;
        $url .= "&page_num=" . $page_num;

        if (isset($this->request->get['page_limit'])) {
            $page_limit = intval($this->request->get['page_limit']) ?: 15;
        } else {
            $page_limit = 15;
        }

        $data['page_limit'] = $page_limit;
        $url .= "&page_limit=" . $page_limit;

        $filter_data = array(
            "filter_reshipment_id" => $filter_reshipment_id,
            "filter_seller_status" => $filter_reshipment_seller_status,
            "filter_item_code" => $filter_reshipment_item_code,
            "filter_sales_order_id" => $filter_reshipment_sales_order_id,
            "filter_order_status" => $filter_reshipment_order_status,
            "filter_store" => $filter_reshipment_store,
            "filter_rma_id" => $filter_reshipment_rma_id,
            "filter_applyDateFrom" => $filter_reshipment_applyDateFrom,
            "filter_applyDateTo" => $filter_reshipment_applyDateTo,
            "page_num" => ($page_num - 1) * $page_limit,
            "page_limit" => $page_limit
        );
        $tmp = $this->model_account_rma_management->getReshipmentInfoCount($filter_data, true);
        $total = $tmp['total'];
        //14370 【BUG】RMA Management
        //$this->session->data['rma_management_reshipment']['id_str'] = $tmp['id_str'];
        $this->cache->set($this->customer->getId() . '_rma_management_reshipment', $tmp['id_str']);
        $results = $this->model_account_rma_management->getReshipmentInfo($filter_data);
        if (isset($this->request->get['backUrlKey'])) {
            $this->cache->delete($this->request->get['backUrlKey']);
        }
        $key = time() . "_rmaOrderDetail";
        foreach ($results as $item) {
            // 获取附件
            $rmaOrderFiles = $this->model_account_rma_management->getRmaOrderFile($item['rma_id'], 1);
            // 遍历文件
            $rmaFiles = array();
            foreach ($rmaOrderFiles as $rmaOrderFile) {
                $imageUrl = StorageCloud::rmaFile()->getUrl($rmaOrderFile->file_path);
                $isImg = $this->isImg($imageUrl);
                $rmaFiles[] = [
                    'isImg' => $isImg,
                    'imageUrl' => $isImg ? $imageUrl : null,
                    'id' => $rmaOrderFile->id,
                    'file_name' => $rmaOrderFile->file_name,
                    'download' => $this->url->link('account/rma_order_detail/download', '&rmaFileId=' . $rmaOrderFile->id, true)
                ];
            }

            $tag_array = array();
            $icon_array = [];
            foreach (explode(",", $item['product_ids']) as $product_id) {
                //配件和超大件标识
                $icon_tmp_array = $this->model_catalog_product->getProductSpecificTag($product_id);
                if ($icon_tmp_array) {
                    foreach ($icon_tmp_array as $key_1 => $val_1) {
                        $img_url = $this->model_tool_image->getOriginImageProductTags($val_1['origin_icon']);
                        $icon_tmp_array[$key_1]['icon_url'] = $img_url;
                        $icon_tmp_array[$key_1]['icon_class'] = $val_1['class_style'];
                    }
                }
                $tag_array[] = $icon_tmp_array;
                $icon_array[] = get_value_or_default(
                    TRANSACTION_TYPE_ICON,
                    $this->model_account_rma_management->getIconByRmaIdAndProductId($item['rma_order_id'], $product_id) ?? 0
                );
            }
            $itemcode_array = array();
            foreach (explode(",", $item['item_code']) as $item_code) {
                array_push($itemcode_array, $item_code);
            }

            $tracking_array = $this->model_account_rma_management->getRmaTrackingNumber($item['reorder_id'], $this->customer->getId());
            $trackingNumber = array();
            $carrierName = array();
            $trackStatus = array();
            if (isset($tracking_array) && !empty($tracking_array)) {
                foreach ($tracking_array as $track) {
                    $track_temp = explode(',', $track['trackingNo']);
                    $track_size = sizeof($track_temp);
                    for ($i = 0; $i < $track_size; $i++) {
                        //英国订单,物流单号为JD开头,显示Carrier是Yodel
                        if ($this->customer->getCountryId() == 222 && 'JD' == substr($track_temp[$i], 0, 2) && in_array($track['carrierName'], CHANGE_CARRIER_NAME)) {
                            $carrierName[] = 'Yodel';
                        } elseif ($this->customer->getCountryId() == 222 && in_array($track['carrierName'], CHANGE_CARRIER_NAME)) {
                            $carrierName[] = 'WHISTL';
                        } else {
                            $carrierName[] = $track['carrierName'];
                        }
                        if ($track['status'] == 0) {
                            $trackStatus[] = 0;
                        } else {
                            $trackStatus[] = 1;
                        }
                    }
                    $trackingNumber = array_merge($trackingNumber, $track_temp);
                }
            }
            //获取是否是返点还是保证金的图标
            $type_icon = $this->model_account_rma_management->getIconByRmaId($item['rma_id']);
            //获取是否是期货保证金的图标
            $ret = $this->model_account_rma_management->getFutureMarginInfo($item['rma_id']);
            //101592 一件代发 taixing
            $isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
            if ($isCollectionFromDomicile) {
                $order_string = 'index.php?route=account/customer_order/customerOrderSalesOrderDetails&id=';
            } else {
                $order_string = 'index.php?route=account/sales_order/sales_order_management/customerOrderSalesOrderDetails&id=';
            }
            // 获取协议url
            $agreementUrl = 'javascript:void(0);';
            if ($type_icon->type_id == 3) {
                $agreementUrl = !empty($ret['contract_id'])
                    ? url(['account/product_quotes/futures/buyerFuturesBidDetail', 'id' => $type_icon->agreement_id])
                    : url(['account/product_quotes/futures/detail', 'id' => $type_icon->agreement_id]);
            }
            if ($type_icon->type_id == 2) {
                $agreementUrl = url(['account/product_quotes/margin/detail_list', 'id' => $type_icon->agreement_id]);
            }
            $data['rmaOrders'][] = array(
                "reshipment_id" => $item['reorder_id'],
                'type_icon' => sprintf(TRANSACTION_TYPE_ICON[$type_icon->type_id], $type_icon->agreement_no),
                "sales_order_id" => $item['from_customer_order_id'],
                "sales_order_link" => $order_string . $item['customer_order_id'],
                "rma_id" => $item['rma_order_id'],
                "rma_id_s" => $item['rma_id'],
                'rma_order_id_s' => $item['rma_order_id'],
                "rma_id_link" => 'index.php?route=account/rma_order_detail&rma_id=' . $item['rma_id'],
                "item_code" => $itemcode_array,
                "tracking_number" => $trackingNumber,
                "carrier" => $carrierName,
                'tracking_status' => $trackStatus,
                "order_status" => $item['DicValue'],
                'seller_status' => $item['seller_status'],
                "application_date" => $item['create_time'],
                "store" => $item['screenname'],
                "store_link" => 'index.php?route=customerpartner/profile&id=' . $item['seller_id'],
                "status_reshipment" => $item['status_reshipment'],
                "tag" => $tag_array,
                "icon" => $icon_array,
                "reshipment_info" => app('db-aes')->decrypt($item['ship_name']) . ' | ' .
                    app('db-aes')->decrypt($item['ship_address']) . ',' . app('db-aes')->decrypt($item['ship_city']) . ',' .
                    $item['ship_state'] . ',' . $item['ship_zip_code'] . ',' . $item['ship_country'] . ' | ' .
                    app('db-aes')->decrypt($item['ship_phone']) . ' | ' . app('db-aes')->decrypt($item['email']),
                "reason" => $item['reason'],
                "comments" => $item['comments'],
                "seller_reshipment_comments" => $item['seller_reshipment_comments'],
                'rmaFiles' => $rmaFiles,
                "order_status_id" => $item['order_status'],
                'type_id' => $ret['type_id'] ?? null,
                'future_margin_agreement_id' => $ret['future_margin_agreement_id'] ?? null,
                'agreement_id' => $ret['agreement_id'] ?? null,
                'contract_id' => $ret['contract_id'] ?? null,
                'agreement_url' => $agreementUrl,
                // edit cancel
                'cancel_status' => $item['cancel_status'],
                'canCancelOrEdit' => $this->canEditRma($item['rma_order_id']),
                'order_type' => $item['order_type'],
                'create_timestamp' => strtotime($item['create_time']),
            );
        }
        //分页
        $total_pages = ceil($total / $page_limit);
        $data['total_pages'] = $total_pages;
        $data['total_num'] = $total;
        $data['page_limit'] = $page_limit;
        $data['results'] = sprintf($this->language->get('text_pagination'), ($total) ? (($page_num - 1) * $page_limit) + 1 : 0, ((($page_num - 1) * $page_limit) > ($total - $page_limit)) ? $total : ((($page_num - 1) * $page_limit) + $page_limit), $total, $total_pages);
        $data['text_no_results'] = $this->language->get('text_no_results');
        $backUrl = $this->url->link('account/rma_management' . '&backUrlKey=' . $key . $url, true);
        $this->cache->set($key, $backUrl);
        $data['backUrlKey'] = $key;
    }

    private function isImg($fileName)
    {
        // 考虑到存储到oss的问题 这里的方法需要变动
        return (bool)preg_match('/.*(\.png|\.jpg|\.jpeg|\.gif)$/', $fileName);
    }

    /**
     * Buyer下载重发单，生成CSV文件
     */
    public function downloadReshipmentInfo()
    {
        $this->load->language('account/customer_order_import');
        $this->load->language('account/customer_order');

        // 判断用户是否登录
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/rma_management/downloadReshipmentInfo', '', true));

            $this->response->redirect($this->url->link('account/login', '', true));
        }
        // 用户ID
        $customer_id = $this->customer->getId();
        $country_id = $this->customer->getCountryId();
        $this->load->model('account/rma_management');
        $id_str = $this->cache->get($customer_id . '_rma_management_reshipment');
        if ($id_str) {
            $results = $this->model_account_rma_management->getReshipmentInfoById($id_str);
        } else {
            if (isset($this->request->get['filter_reshipment_id'])) {
                $filter_reshipment_id = $this->request->get['filter_reshipment_id'];
            } else {
                $filter_reshipment_id = null;
            }
            if (isset($this->request->get['filter_reshipment_seller_status'])) {
                $filter_reshipment_seller_status = $this->request->get['filter_reshipment_seller_status'];
            } else {
                $filter_reshipment_seller_status = -1;
            }

            if (isset($this->request->get['filter_reshipment_item_code'])) {
                $filter_reshipment_item_code = $this->request->get['filter_reshipment_item_code'];
            } else {
                $filter_reshipment_item_code = null;
            }
            if (isset($this->request->get['filter_reshipment_sales_order_id'])) {
                $filter_reshipment_sales_order_id = $this->request->get['filter_reshipment_sales_order_id'];
            } else {
                $filter_reshipment_sales_order_id = null;
            }
            if (isset($this->request->get['filter_reshipment_order_status'])) {
                $filter_reshipment_order_status = $this->request->get['filter_reshipment_order_status'];
            } else {
                $filter_reshipment_order_status = null;
            }

            if (isset($this->request->get['filter_reshipment_store'])) {
                $filter_reshipment_store = $this->request->get['filter_reshipment_store'];
            } else {
                $filter_reshipment_store = null;
            }

            if (isset($this->request->get['filter_reshipment_rma_id'])) {
                $filter_reshipment_rma_id = $this->request->get['filter_reshipment_rma_id'];
            } else {
                $filter_reshipment_rma_id = null;
            }

            if (isset($this->request->get['filter_reshipment_applyDateFrom'])) {
                $filter_reshipment_applyDateFrom = $this->request->get['filter_reshipment_applyDateFrom'];
            } else {
                $filter_reshipment_applyDateFrom = null;
            }

            if (isset($this->request->get['filter_reshipment_applyDateTo'])) {
                $filter_reshipment_applyDateTo = $this->request->get['filter_reshipment_applyDateTo'];
            } else {
                $filter_reshipment_applyDateTo = null;
            }

            //过滤参数
            $filter_data = array(
                "filter_reshipment_id" => $filter_reshipment_id,
                "filter_seller_status" => $filter_reshipment_seller_status,
                "filter_item_code" => $filter_reshipment_item_code,
                "filter_sales_order_id" => $filter_reshipment_sales_order_id,
                "filter_order_status" => $filter_reshipment_order_status,
                "filter_store" => $filter_reshipment_store,
                "filter_rma_id" => $filter_reshipment_rma_id,
                "filter_applyDateFrom" => $filter_reshipment_applyDateFrom,
                "filter_applyDateTo" => $filter_reshipment_applyDateTo,
            );
            $results = $this->model_account_rma_management->getReshipmentInfo($filter_data);

        }
        //13377 B2B上comboSKU需要显示每一个子SKU对应的运单号
        //针对于每个reshipment订单进行处理
        //一个重发单对应一个
        $results = $this->model_account_rma_management->getReshipmentTrackingInfo($results);

        //12591 B2B记录各国别用户的操作时间
        $country = session('country', 'USA');
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        $time = dateFormat($fromZone, $toZone, date("YmdHis", time()), 'YmdHis');
        //12591 end
        $fileName = "Reshipment Order" . $time . ".xls";
        $head = [
            'Reshipment ID', 'Sales Order ID', 'RMA ID', 'Item Code', 'Quantity',
            'Sub-item Code', 'Sub-item Quantity', 'Ship To', 'Address', 'Tracking Number',
            'Carrier', 'Reshipment Order Status', 'Application Date', 'Store', 'Reason',
            'Seller\'s Process Result'
        ];
        if (isset($results) && !empty($results)) {
            foreach ($results as $result) {
                //13377 B2B上comboSKU需要显示每一个子SKU对应的运单号
                $carrier_name = '';
                if ($result['carrier_name'] != null) {
                    if (count(array_unique($result['carrier_name'])) == 1) {
                        $carrier_name = current($result['carrier_name']);
                    } else {
                        $carrier_name = implode(PHP_EOL, $result['carrier_name']);
                    }
                }
                $tracking_number = '';
                if ($result['tracking_number'] != null) {
                    foreach ($result['tracking_number'] as $key => $value) {
                        if ($result['tracking_status'][$key] == 0) {
                            $tracking_number .= $value . ' (invalid) ' . PHP_EOL;
                        } else {
                            $tracking_number .= $value . PHP_EOL;
                        }
                    }
                }
                $content[] = array(
                    $result['reorder_id'],
                    $result['from_customer_order_id'],
                    "\t" . $result['rma_order_id'],
                    $result['sku'],
                    $result['line_qty'],
                    $result['child_sku'],
                    $result['all_qty'],
                    app('db-aes')->decrypt($result['ship_name']),
                    htmlspecialchars_decode(
                        app('db-aes')->decrypt($result['ship_address']) . ',' .
                        app('db-aes')->decrypt($result['ship_city']) . ',' .
                        $result['ship_state'] . ',' .
                        $result['ship_zip_code'] . ',' . $result['ship_country']
                    ),
                    $tracking_number,
                    $carrier_name,
                    $result['order_status'] == 1 ? 'New Order' : $result['DicValue'],
                    $result['create_time'],
                    html_entity_decode($result['screenname']),
                    $result['reason'],
                    $result['status_reshipment']
                );
            }
            //12591 B2B记录各国别用户的操作时间
            outputExcel($fileName, $head, $content, $this->session);
            //12591 end
        } else {
            $content[] = array($this->language->get('error_no_record'));
            //12591 B2B记录各国别用户的操作时间
            outputExcel($fileName, $head, $content, $this->session);
            //12591 end
        }
    }

    public function refund()
    {
        // 判断用户是否登录
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/rma_management', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }
        // 加载语言层
        $this->load->language('account/rma_management');
        $this->load->language('common/cwf');
        $this->load->model('account/rma_management');

        // 获取Store下拉选
        $data['stores'] = $this->model_account_rma_management->getStoreByBuyerId($this->customer->getId());
        // 获取RMA状态下拉选
        $data['rmaStatus'] = $this->model_account_rma_management->getRmaStatus();
        $data['now'] = time();
        $this->getRefundList($data);
        $data['continue'] = $this->url->link('account/account', '', true);
        $data['country'] = session('country');
        $this->response->setOutput($this->load->view('account/rma_management/refund_info', $data));
    }

    /**
     * 获取RMA返金数据
     * @param $data
     */
    public function getRefundList(&$data)
    {
        $this->load->language('account/rma_management');
        $this->load->model('account/rma_management');
        $this->load->model('catalog/product');
        $this->load->model('tool/image');

        $url = "";
        if (isset($this->request->get['filter_purchase_order_id'])) {
            $filter_purchase_order_id = $this->request->get['filter_purchase_order_id'];
            $url .= "&filter_purchase_order_id=" . $filter_purchase_order_id;
        } else {
            $filter_purchase_order_id = null;
        }
        if (isset($this->request->get['filter_refund_seller_status'])) {
            $filter_refund_seller_status = $this->request->get['filter_refund_seller_status'];
            $url .= "&filter_refund_seller_status=" . $filter_refund_seller_status;
        } else {
            $filter_refund_seller_status = -1;
        }

        if (isset($this->request->get['filter_refund_sales_order_id'])) {
            $filter_refund_sales_order_id = $this->request->get['filter_refund_sales_order_id'];
            $url .= "&filter_refund_sales_order_id=" . $filter_refund_sales_order_id;
        } else {
            $filter_refund_sales_order_id = null;
        }
        if (isset($this->request->get['filter_refund_store'])) {
            $filter_refund_store = $this->request->get['filter_refund_store'];
            $url .= "&filter_refund_store=" . $filter_refund_store;
        } else {
            $filter_refund_store = null;
        }
        if (isset($this->request->get['filter_refund_rma_id'])) {
            $filter_refund_rma_id = $this->request->get['filter_refund_rma_id'];
            $url .= "&filter_refund_rma_id=" . $filter_refund_rma_id;
        } else {
            $filter_refund_rma_id = null;
        }

        if (isset($this->request->get['filter_refund_applyDateFrom'])) {
            $filter_refund_applyDateFrom = $this->request->get['filter_refund_applyDateFrom'];
            $url .= "&filter_refund_applyDateFrom=" . $filter_refund_applyDateFrom;
        } else {
            $filter_refund_applyDateFrom = null;
        }

        if (isset($this->request->get['filter_refund_applyDateTo'])) {
            $filter_refund_applyDateTo = $this->request->get['filter_refund_applyDateTo'];
            $url .= "&filter_refund_applyDateTo=" . $filter_refund_applyDateTo;
        } else {
            $filter_refund_applyDateTo = null;
        }

        $data['filter_purchase_order_id'] = $filter_purchase_order_id;
        $data['filter_refund_seller_status'] = $filter_refund_seller_status;
        $data['filter_refund_sales_order_id'] = $filter_refund_sales_order_id;
        $data['filter_refund_rma_id'] = $filter_refund_rma_id;
        $data['filter_refund_store'] = $filter_refund_store;
        $data['filter_refund_applyDateFrom'] = $filter_refund_applyDateFrom;
        $data['filter_refund_applyDateTo'] = $filter_refund_applyDateTo;

        /* 分页 */
        if (isset($this->request->get['page_num'])) {
            $page_num = intval($this->request->get['page_num']);
        } else {
            $page_num = 1;
        }
        $data['page_num'] = $page_num;
        $url .= "&page_num=" . $page_num;

        if (isset($this->request->get['page_limit'])) {
            $page_limit = intval($this->request->get['page_limit']) ?: 15;
        } else {
            $page_limit = 15;
        }
        $data['page_limit'] = $page_limit;
        $url .= "&page_limit=" . $page_limit;

        $filter_data = array(
            "filter_purchase_order_id" => $filter_purchase_order_id,
            "filter_refund_seller_status" => $filter_refund_seller_status,
            "filter_refund_store" => $filter_refund_store,
            "filter_refund_rma_id" => $filter_refund_rma_id,
            "filter_refund_sales_order_id" => $filter_refund_sales_order_id,
            "filter_refund_applyDateFrom" => $filter_refund_applyDateFrom,
            "filter_refund_applyDateTo" => $filter_refund_applyDateTo,
            "page_num" => ($page_num - 1) * $page_limit,
            "page_limit" => $page_limit
        );
        $total = $this->model_account_rma_management->getRefundInfoCount($filter_data);
        $results = $this->model_account_rma_management->getRefundInfo($filter_data);
        $numStart = ($page_num - 1) * $page_limit;
        if (isset($this->request->get['backUrlKey'])) {
            $this->cache->delete($this->request->get['backUrlKey']);
        }
        $key = time() . "_rmaOrderDetail";
        foreach ($results as $item) {
            // 获取附件
            $rmaOrderFiles = $this->model_account_rma_management->getRmaOrderFile($item['rma_id'], 1);
            // 遍历文件
            $rmaFiles = [];
            foreach ($rmaOrderFiles as $rmaOrderFile) {
                $imageUrl = StorageCloud::rmaFile()->getUrl($rmaOrderFile->file_path);
                $isImg = $this->isImg($imageUrl);
                $rmaFiles[] = [
                    'isImg' => $isImg,
                    'imageUrl' => $isImg ? $imageUrl : null,
                    'id' => $rmaOrderFile->id,
                    'file_name' => $rmaOrderFile->file_name,
                    'download' => $this->url->link('account/rma_order_detail/download', '&rmaFileId=' . $rmaOrderFile->id, true)
                ];
            }

            $tag_array = $this->model_catalog_product->getProductSpecificTag($item['product_id']);
            if ($tag_array) {
                foreach ($tag_array as $key_1 => $val_1) {
                    $img_url = $this->model_tool_image->getOriginImageProductTags($val_1['origin_icon']);
                    $tag_array[$key_1]['icon_url'] = $img_url;
                    $tag_array[$key_1]['icon_class'] = $val_1['class_style'];
                }
            }

            //获取价格,根据productId
            $this->load->model('buyer/buyer_common');
            $result = $this->model_buyer_buyer_common->getOrderPrice($item['order_id'], $item['product_id']);
            if (!empty($result)) {
                $price_flag = 0;
                $actual_price = $result[$item['product_id']]['actual_price'];
                $service_fee = $result[$item['product_id']]['service_fee'];
                $poundage = $result[$item['product_id']]['poundage'];
                $quote = $result[$item['product_id']]['quote'];
                $freight_per = $result[$item['product_id']]['freight_per'];
                $package_fee = $result[$item['product_id']]['package_fee'];
                $freight_difference_per = $result[$item['product_id']]['freight_difference_per'];
                //判断是否为欧洲
                if (session('currency') != 'USD' && session('currency') != 'JPY') {
                    if (!$quote) {
                        $price = $this->currency->format($item['qty'] * ($actual_price + $service_fee + $freight_per + $package_fee), session('currency'));
                        $clean_price = $item['qty'] * ($actual_price + $service_fee + $freight_per + $package_fee);
                    } else {
                        $price = $this->currency->format($item['qty'] * ($actual_price + $freight_per + $package_fee), session('currency'));
                        $clean_price = $item['qty'] * ($actual_price + $freight_per + $package_fee);
                    }
                } else {
                    $price = $this->currency->format($item['qty'] * ($actual_price + $freight_per + $package_fee), session('currency'));
                    $clean_price = $item['qty'] * ($actual_price + $freight_per + $package_fee);
                }
//                if ($poundage == 0) {
//                    $totalAmount = $price;
//                } else {
//                    $totalAmount = '(' . $price . '+' . $this->currency->format($item['qty'] * $poundage, session('currency')) . ')';
//                    $clean_price = $clean_price + $item['qty'] * $poundage;
//                    $price_flag = 1;
//                }
                $totalAmount = $price;
                // 14370 【BUG】RMA Management
                $clean_price = $this->currency->format($clean_price, session('currency'));
            }
            $marginHaveProcessFlag = $this->model_account_rma_management
                ->checkMarginProductHaveProcess($item['order_id'], $item['product_id']);
            //获取销售订单的状态
            // 顾客订单
            $future_margin_info = [];
            if ($item['type_id'] == 3) {
                $future_margin_info = $this->model_account_rma_management->getFutureMarginInfoByOrderIdAndProductId($item['order_id'], $item['product_id']);
            }
            if (isset($item['from_customer_order_id'])) {
                $customerOrder = $this->model_account_rma_management->getCustomerOrder($item['from_customer_order_id'], $this->customer->getId());
                if ($marginHaveProcessFlag && $customerOrder['order_status'] == CustomerSalesOrderStatus::COMPLETED) {
                    $marginInfo = $this->model_account_rma_management->getMarginPriceInfo($item['product_id'], $item['qty'], $item['order_product_id']);
                    $totalAmount = $this->currency->format($marginInfo['totalPrice'], session('currency'));
                } elseif ($future_margin_info && $customerOrder['order_status'] == CustomerSalesOrderStatus::COMPLETED) {
                    $marginInfo = $this->model_account_rma_management->getFutureMarginPriceInfo($item['agreement_id'], $item['qty'], $item['order_product_id']);
                    $totalAmount = $this->currency->format($marginInfo['totalPrice'], session('currency'));
                }
            }
            //获取是否是返点还是保证金的图标
            $type_icon = $this->model_account_rma_management->getIconByRmaId($item['rma_id']);
            //获取是否是期货保证金的图标
            $ret = $this->model_account_rma_management->getFutureMarginInfo($item['rma_id']);
            // 获取协议url
            $agreementUrl = 'javascript:void(0);';
            if ($type_icon->type_id == 3) {
                $agreementUrl = !empty($ret['contract_id'])
                    ? url(['account/product_quotes/futures/buyerFuturesBidDetail', 'id' => $type_icon->agreement_id])
                    : url(['account/product_quotes/futures/detail', 'id' => $type_icon->agreement_id]);
            }
            if ($type_icon->type_id == 2) {
                $agreementUrl = url(['account/product_quotes/margin/detail_list', 'id' => $type_icon->agreement_id]);
            }
            $data['rmaOrders'][] = array(
                "purchase_order_id" => $item['order_id'],
                "delivery_type" => $item['delivery_type'],
                'type_icon' => sprintf(TRANSACTION_TYPE_ICON[$type_icon->type_id], $type_icon->agreement_no),
                "purchase_order_id_link" => 'index.php?route=account/order/purchaseOrderInfo&order_id=' . $item['order_id'],
                "sales_order_id" => $item['from_customer_order_id'],
                "sales_order_link" => 'index.php?route=account/customer_order/customerOrderSalesOrderDetails&id=' . $item['customer_order_id'],
                //"sales_order_link" => 'index.php?route=account/customer_order&purchase_order_id=' . $item['from_customer_order_id'],
                "rma_id" => $item['rma_order_id'],
                "rma_id_s" => $item['rma_id'],
                'rma_order_id_s' => $item['rma_order_id'],
                "rma_id_link" => 'index.php?route=account/rma_order_detail&rma_id=' . $item['rma_id'],
                "item_code" => $item['sku'],
                "qty" => $item['qty'],
                'seller_status' => $item['seller_status'],
                "total_amount" => $totalAmount,
                "clean_price" => $clean_price,
                "price_flag" => $price_flag,
                "apply_refund_amount" => $this->currency->format($item['apply_refund_amount'],  session('currency')),
                "seller_refund_amount" =>
                    bccomp($item['actual_refund_amount'], 0) === 1
                        ? $this->currency->format($item['actual_refund_amount'], session('currency'))
                        : 'N/A',
                "application_date" => $item['create_time'],
                "store" => $item['screenname'],
                "store_link" => 'index.php?route=customerpartner/profile&id=' . $item['seller_id'],
                "status_refund" => $item['statusRefund'],
                'refund_type' => $item['refundType'],
                "tag" => $tag_array,
                "product_name" => $item['name'],
                "product_link" => 'index.php?route=product/product&product_id=' . $item['product_id'],
                "reason" => $item['reason'],
                "comments" => $item['comments'],
                "seller_refund_comments" => $item['seller_refund_comments'],
                'rmaFiles' => $rmaFiles,
                'type_id' => $ret['type_id'] ?? null,
                'future_margin_agreement_id' => $ret['future_margin_agreement_id'] ?? null,
                'agreement_id' => $ret['agreement_id'] ?? null,
                'contract_id' => $ret['contract_id'] ?? null,
                'agreement_url' => $agreementUrl,
                // edit cancel
                'cancel_status' => $item['cancel_status'],
                'canCancelOrEdit' => $this->canEditRma($item['rma_order_id']),
                'order_type' => $item['order_type'],
                'create_timestamp' => strtotime($item['create_time']),
            );
        }
        //分页
        $total_pages = ceil($total / $page_limit);
        $data['total_pages'] = $total_pages;
        $data['total_num'] = $total;
        $data['page_limit'] = $page_limit;
        $data['results'] = sprintf($this->language->get('text_pagination'), ($total) ? (($page_num - 1) * $page_limit) + 1 : 0, ((($page_num - 1) * $page_limit) > ($total - $page_limit)) ? $total : ((($page_num - 1) * $page_limit) + $page_limit), $total, $total_pages);
        $data['text_no_results'] = $this->language->get('text_no_results');
        $backUrl = $this->url->link('account/rma_management' . '&backUrlKey=' . $key . $url, true);
        $this->cache->set($key, $backUrl);
        $data['backUrlKey'] = $key;
    }

    /**
     * Buyer下载refund，生成CSV文件
     */
    public function downloadRefundInfo()
    {
        $this->load->language('account/customer_order_import');
        $this->load->language('account/customer_order');

        // 判断用户是否登录
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/rma_management/downloadRefundInfo', '', true));

            $this->response->redirect($this->url->link('account/login', '', true));
        }
        if (isset($this->request->get['filter_purchase_order_id'])) {
            $filter_purchase_order_id = $this->request->get['filter_purchase_order_id'];
        } else {
            $filter_purchase_order_id = null;
        }
        if (isset($this->request->get['filter_refund_seller_status'])) {
            $filter_refund_seller_status = $this->request->get['filter_refund_seller_status'];
        } else {
            $filter_refund_seller_status = null;
        }

        if (isset($this->request->get['filter_refund_sales_order_id'])) {
            $filter_refund_sales_order_id = $this->request->get['filter_refund_sales_order_id'];
        } else {
            $filter_refund_sales_order_id = null;
        }
        if (isset($this->request->get['filter_refund_store'])) {
            $filter_refund_store = $this->request->get['filter_refund_store'];
        } else {
            $filter_refund_store = null;
        }
        if (isset($this->request->get['filter_refund_rma_id'])) {
            $filter_refund_rma_id = $this->request->get['filter_refund_rma_id'];
        } else {
            $filter_refund_rma_id = null;
        }

        if (isset($this->request->get['filter_refund_applyDateFrom'])) {
            $filter_refund_applyDateFrom = $this->request->get['filter_refund_applyDateFrom'];
        } else {
            $filter_refund_applyDateFrom = null;
        }

        if (isset($this->request->get['filter_refund_applyDateTo'])) {
            $filter_refund_applyDateTo = $this->request->get['filter_refund_applyDateTo'];
        } else {
            $filter_refund_applyDateTo = null;
        }

        // 用户ID
        $customer_id = $this->customer->getId();
        $country_id = $this->customer->getCountryId();
        //过滤参数
        $filter_data = array(
            "filter_purchase_order_id" => $filter_purchase_order_id,
            "filter_refund_seller_status" => $filter_refund_seller_status,
            "filter_refund_store" => $filter_refund_store,
            "filter_refund_rma_id" => $filter_refund_rma_id,
            "filter_refund_sales_order_id" => $filter_refund_sales_order_id,
            "filter_refund_applyDateFrom" => $filter_refund_applyDateFrom,
            "filter_refund_applyDateTo" => $filter_refund_applyDateTo
        );
        $this->load->model('account/rma_management');
        $results = $this->model_account_rma_management->getRefundInfo($filter_data);
        //12591 B2B记录各国别用户的操作时间
        $country = session('country', 'USA');
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        $time = dateFormat($fromZone, $toZone, date("YmdHis", time()), 'YmdHis');
        //12591 end
        $fileName = "Refund Application" . $time . ".xls";
        $head = [
            'Purchase Order ID', 'Sales Order ID', 'RMA ID', 'Item Code',
            'Quantity', 'Total Amount', 'Apply Refund Amount', 'Seller\'s Refund Amount',
            'Application Date', 'Store', 'Reason', 'Seller\'s Process Result', 'Refund Type'
        ];
        foreach ($head as $i => $v) {
            // CSV的Excel支持GBK编码，一定要转换，否则乱码
            $head [$i] = iconv('utf-8', 'gbk', $v);
        }
        if (isset($results) && !empty($results)) {
            foreach ($results as $result) {
                //获取价格,根据productId
                $this->load->model('buyer/buyer_common');
                $priceResult = $this->model_buyer_buyer_common->getOrderPrice($result['order_id'], $result['product_id']);
                if (!empty($priceResult)) {
                    $actual_price = $priceResult[$result['product_id']]['actual_price'];
                    $service_fee = $priceResult[$result['product_id']]['service_fee'];
                    $poundage = $priceResult[$result['product_id']]['poundage'];
                    $quote = $priceResult[$result['product_id']]['quote'];
                    $freight_per = $priceResult[$result['product_id']]['freight_per'];
                    $package_fee = $priceResult[$result['product_id']]['package_fee'];
                    $freight_difference_per = $priceResult[$result['product_id']]['freight_difference_per'];
                    //判断是否为欧洲
                    if (session('currency') != 'USD' && session('currency') != 'JPY') {
                        if (!$quote) {
                            $price = $result['qty'] * ($actual_price + $service_fee + $freight_per + $package_fee);
                        } else {
                            $price = $result['qty'] * ($actual_price + $freight_per + $package_fee);
                        }
                    } else {
                        $price = $result['qty'] * ($actual_price + $freight_per + $package_fee);
                    }
                    $totalAmount = $price;
                }
                $marginHaveProcessFlag = $this->model_account_rma_management
                    ->checkMarginProductHaveProcess($result['order_id'], $result['product_id']);
                //获取销售订单的状态
                // 顾客订单
                $future_margin_info = [];
                if ($result['type_id'] == 3) {
                    $future_margin_info = $this->model_account_rma_management->getFutureMarginInfoByOrderIdAndProductId($result['order_id'], $result['product_id']);
                }
                if (isset($result['from_customer_order_id'])) {
                    $customerOrder = $this->model_account_rma_management->getCustomerOrder($result['from_customer_order_id'], $this->customer->getId());
                    if ($marginHaveProcessFlag && $customerOrder['order_status'] == CustomerSalesOrderStatus::COMPLETED) {
                        $marginInfo = $this->model_account_rma_management->getMarginPriceInfo($result['product_id'], $result['qty'], $result['order_product_id']);
                        //$totalAmount = $this->currency->format($marginInfo['totalPrice'], session('currency'));
                        $totalAmount = $marginInfo['totalPrice'];
                    } elseif ($future_margin_info && $customerOrder['order_status'] == CustomerSalesOrderStatus::COMPLETED) {
                        $marginInfo = $this->model_account_rma_management->getFutureMarginPriceInfo($result['agreement_id'], $result['qty'], $result['order_product_id']);
                        $totalAmount = $marginInfo['totalPrice'];
                    }
                }

                $totalCouponAndCampaign = $result['coupon_amount'] + $result['campaign_amount'];
                if ($totalAmount > 0 && $totalCouponAndCampaign > 0) {
                    $totalAmount = max($totalAmount - $totalCouponAndCampaign, 0);
                }
                $totalAmount = $this->currency->format($totalAmount, session('currency'));
                $content[] = array(
                    $result['order_id'],
                    $result['from_customer_order_id'],
                    "\t" . $result['rma_order_id'],
                    $result['sku'],
                    $result['qty'],
                    $totalAmount,
                    $result['apply_refund_amount'],
                    $result['actual_refund_amount'],
                    $result['create_time'],
                    html_entity_decode($result['screenname']),
                    $result['reason'],
                    $result['statusRefund'],
                    $result['refundType']
                );
            }
            //12591 B2B记录各国别用户的操作时间
            outputExcel($fileName, $head, $content, $this->session);
            //12591 end
        } else {
            $content[] = array($this->language->get('error_no_record'));
            //12591 B2B记录各国别用户的操作时间
            outputExcel($fileName, $head, $content, $this->session);
            //12591 end
        }
    }

    /**
     * 修改RMA
     */
    public function editRma()
    {
        $is_margin = 0;
        // 加载语言层
        $this->load->language('account/rma_management');
        $this->load->language('common/cwf');
        // 设置文档标题
        $this->document->setTitle($this->language->get('text_edit_rma'));
        // 面包屑导航
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_account'),
            'href' => $this->url->link('account/account', '', true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_rma_management'),
            'href' => $this->url->link('account/rma_management', '', true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_edit_rma'),
            'href' => $this->url->link('account/rma_management', '', true)
        );
        // model
        $this->load->model('account/rma_management');
        $this->load->model('tool/image');
        //获取RMA_ID
        $rma_order_id = $this->request->attributes->get('rma_order_id');
        $buyerId = $this->customer->getId();
        $data['currency'] = session('currency');
        //获取修改的RMA的信息
        $rmaInfo = $this->model_account_rma_management->getRmaInfo($rma_order_id);
        $data['rma_id'] = $rmaInfo['rma_id'];
        $data['rmaInfo'] = json_encode($rmaInfo);
        $data['rma_reason_id'] = $rmaInfo['reason_id'];
        $data['comments'] = $rmaInfo['comments'];
        $data['rma_type'] = $rmaInfo['rma_type'];
        $data['apply_refund_amount'] = customer()->isJapan() ? (int)$rmaInfo['apply_refund_amount'] : $rmaInfo['apply_refund_amount'];
        //RMA图片 获取附件
        $rmaOrderFiles = $this->model_account_rma_management->getRmaOrderFile($rmaInfo['rma_id'], 1);
        // 遍历文件
        foreach ($rmaOrderFiles as $rmaOrderFile) {
            $imageUrl = StorageCloud::rmaFile()->getUrl($rmaOrderFile->file_path);
            $data['imageResult'][] = [
                'id' => $rmaOrderFile->id,
                'rmaId' => $rmaInfo['rma_id'],
                'name' => $rmaOrderFile->file_name,
                'path' => $imageUrl,
            ];
        }
        // 获取顾客重发单
        $reorder = obj2array($this->model_account_rma_management->getReorderByRmaId($rmaInfo['rma_id']));
        $data['reorder'] = $reorder;
        // 获取重发单明细
        if ($reorder != null) {
            $reorderLines = $this->model_account_rma_management->getReorderLineByReorderId($reorder['id']);
            $data['reorderLines'] = $reorderLines;
            // 顾客订单
            $customerOrder = $this->model_account_rma_management->getCustomerOrder($rmaInfo['from_customer_order_id'], $buyerId);
            $data['sales_order_id'] = $customerOrder['id'];
            $data['order_id'] = $customerOrder['order_id'];
            $data['order_from'] = $customerOrder['orders_from'];
            $data['order_date'] = $reorder['create_time'];
            $data['order_status'] = $customerOrder['DicValue'];
            $data['ship_to'] = app('db-aes')->decrypt($reorder['ship_name']);
            $data['email'] = app('db-aes')->decrypt($reorder['email']);
            $data['ship_phone'] = app('db-aes')->decrypt($reorder['ship_phone']);
            $data['address'] = app('db-aes')->decrypt($reorder['ship_address']) . ' '
                .  app('db-aes')->decrypt($reorder['ship_city']) . ' ' . $reorder['ship_state']
                . ' ' . $reorder['ship_zip_code'] . ' ' . $reorder['ship_country'];
            $data['ship_qty'] = $reorderLines[0]['qty'];
            $data['customer_order_ship_address'] = app('db-aes')->decrypt($reorder['ship_address']);
        } else {
            $customerOrder = $this->model_account_rma_management->getCustomerOrder($rmaInfo['from_customer_order_id'], $buyerId);
            $data['sales_order_id'] = $customerOrder['id'];
            $data['order_id'] = $customerOrder['order_id'];
            $data['order_from'] = $customerOrder['orders_from'];
            $data['order_date'] = $customerOrder['create_time'];
            $data['order_status'] = $customerOrder['DicValue'];
            $data['ship_to'] =  app('db-aes')->decrypt($customerOrder['ship_name']);
            $data['email'] = app('db-aes')->decrypt($customerOrder['email']);
            $data['ship_phone'] =  app('db-aes')->decrypt($customerOrder['ship_phone']);
            $data['address'] =  app('db-aes')->decrypt($customerOrder['ship_address1']) . ' ' .  app('db-aes')->decrypt($customerOrder['ship_address2']) . ' '
                .  app('db-aes')->decrypt($customerOrder['ship_city']) . ' ' . $customerOrder['ship_state']
                . ' ' . $customerOrder['ship_zip_code'] . ' ' . $customerOrder['ship_country'];
            $data['customer_order_ship_address'] = app('db-aes')->decrypt($customerOrder['ship_address1']);
        }
        if (count($customerOrder) != 0) {
            $data['customerOrder'] = $customerOrder;
            $data['customerOrderItemStatus'] = $this->model_account_rma_management->getCustomerOrderItemStatus();
            // 顾客订单明细
            $customerOrderLines = $this->model_account_rma_management->getCustomerOrderLineByHeaderId($customerOrder['id']);
            // 获取订单明细所对应的采购订单详情
            $orderLineIds = array();
            // ItemCode
            $selectItems = array();
            foreach ($customerOrderLines as $customerOrderLine) {
                $orderLineIds[] = $customerOrderLine['id'];
            }
            $orders = $this->model_account_rma_management->getPurchaseOrderInfo($orderLineIds);
            $isEurope = $this->country->isEuropeCountry($this->customer->getCountryId());
            $data['isEurope'] = $isEurope;
            foreach ($customerOrderLines as &$customerOrderLine) {
                if ((float)$customerOrderLine['item_price'] == 1.0) {
                    $customerOrderLine['item_price'] = '';
                } else {
                    $customerOrderLine['item_price'] = sprintf("%.2f", $customerOrderLine['item_price']);
                }
                $orderItemInfos = array();
                $orderDetails = array();
                $coupon_and_campaign = 0;
                foreach ($orders as $orderDetail) {
                    if ($customerOrderLine['id'] == $orderDetail['sales_order_line_id']) {
                        if (!(count(explode('http', $orderDetail['image'])) > 1)) {
                            if (StorageCloud::image()->fileExists($orderDetail['image'])) {
                                $image = $this->model_tool_image->resize($orderDetail['image']);
                            } else {
                                $image = $this->model_tool_image->resize('no_image.png');
                            }
                            $orderDetail['image'] = $image;
                        }
                        //判断是否开启议价
                        $quoteResult = $this->model_account_rma_management->getQuotePrice($orderDetail['order_id'], $orderDetail['product_id']);
                        $margin_info = [];
                        $future_margin_info = [];
                        if ($orderDetail['type_id'] == 2) {
                            $margin_info = $this->model_account_rma_management->getMarginInfoByOrderIdAndProductId($orderDetail['order_id'], $orderDetail['product_id']);
                        } elseif ($orderDetail['type_id'] == 3) {
                            $future_margin_info = $this->model_account_rma_management->getFutureMarginInfoByOrderIdAndProductId($orderDetail['order_id'], $orderDetail['product_id']);
                        }
                        if ($orderDetail['freight_difference_per'] > 0) {
                            $orderDetail['freight_diff'] = true;
                            $orderDetail['tips_freight_difference_per'] = str_replace(
                                '_freight_difference_per_',
                                $this->currency->formatCurrencyPrice($orderDetail['freight_difference_per'], session('currency')),
                                $this->language->get('tips_freight_difference_per')
                            );
                        } else {
                            $orderDetail['freight_diff'] = false;
                        }
                        if ($margin_info && $customerOrder['order_status'] == CustomerSalesOrderStatus::COMPLETED) {
                            $is_margin = 1;
                            $result = $this->model_account_rma_management->getMarginPriceInfo($orderDetail['product_id'], $orderDetail['qty'], $orderDetail['order_product_id']);
                            $orderDetail['total'] = $result['totalMargin'];
                            $orderDetail['price_per'] = $result['unitMarginPrice'];
                            $orderDetail['service_fee_per'] = $result['restServiceFee'];
                            $orderDetail['advance_unit_price'] = $result['advanceUnitPrice'];
                            $orderDetail['freight'] = $result['freight'];
                            $orderDetail['poundage'] = $result['poundage'];
                            $orderDetail['transactionFee'] = $result['transactionFee'];
                            if ($customerOrder['order_status'] == CustomerSalesOrderStatus::COMPLETED) {
                                $orderDetail['totalPrice'] = round($result['totalPrice'], 2);
                            } else {
                                $orderDetail['totalPrice'] = round($result['restTotal'], 2);
                            }
                        } elseif ($future_margin_info && $customerOrder['order_status'] == CustomerSalesOrderStatus::COMPLETED) {
                            $is_margin = 1;
                            $result = $this->model_account_rma_management->getFutureMarginPriceInfo($orderDetail['agreement_id'], $orderDetail['qty'], $orderDetail['order_product_id']);
                            $orderDetail['total'] = $result['totalFutureMargin'];
                            $orderDetail['price_per'] = $result['unitFutureMarginPrice'];
                            $orderDetail['service_fee_per'] = $result['serviceFee'];
                            $orderDetail['advance_unit_price'] = $result['advanceUnitPrice'];
                            $orderDetail['freight'] = $result['freight'];
                            $orderDetail['poundage'] = $result['poundage'];
                            $orderDetail['transactionFee'] = $result['transactionFee'];
                            if ($customerOrder['order_status'] == CustomerSalesOrderStatus::COMPLETED) {
                                $orderDetail['totalPrice'] = round($result['totalPrice'], 2);
                            } else {
                                $orderDetail['totalPrice'] = round($result['restTotal'], 2);
                            }
                        } else {
                            if (!empty($quoteResult)) {
                                $data['isQuote'] = true;
                                $orderDetail['quote'] = $this->currency->formatCurrencyPrice((double)($quoteResult['price'] - $quoteResult['discount_price']) * (int)$orderDetail['qty'], session('currency'));
                                $data['isEuropeCountry'] = $this->country->isEuropeCountry($this->customer->getCountryId());
                                if ($data['isEuropeCountry']) {
                                    $orderDetail['totalPrice'] = ($quoteResult['price'] + $orderDetail['freight_per'] + $orderDetail['package_fee']) * $orderDetail['qty'];
                                    $orderDetail['price_per'] = $this->currency->format($orderDetail['price'] - $quoteResult['amount_price_per'], session('currency'));
                                    // 获取用户国际判断是否为欧洲，欧洲订单包含服务费
                                    $orderDetail['service_fee_per'] = $this->currency->format((double)$orderDetail['unit_service_fee'] - $quoteResult['amount_service_fee_per'], session('currency'));
                                } else {
                                    $orderDetail['totalPrice'] = ($quoteResult['price'] + $orderDetail['freight_per'] + $orderDetail['package_fee']) * $orderDetail['qty'];
                                    $orderDetail['price_per'] = $this->currency->format($quoteResult['price'], session('currency'));
                                }
                            } else {
                                $orderDetail['quote'] = $this->currency->format(0.00, session('currency'));
                                $orderDetail['price_per'] = $this->currency->format($orderDetail['price'], session('currency'));
                                $orderDetail['totalPrice'] = ($orderDetail['price'] + $orderDetail['unit_service_fee'] + $orderDetail['freight_per'] + $orderDetail['package_fee']) * $orderDetail['qty'];
                                // 获取用户国际判断是否为欧洲，欧洲订单包含服务费
                                $orderDetail['service_fee_per'] = $this->currency->format((double)$orderDetail['unit_service_fee'], session('currency'));
                            }
                            $orderDetail['poundage'] = $this->currency->format((double)$orderDetail['unit_poundage'] * (int)$orderDetail['qty'], session('currency'));
                            $orderDetail['transactionFee'] = $this->currency->format((double)$orderDetail['unit_poundage'] * (int)$orderDetail['qty'], session('currency'), '', false);
                            $orderDetail['freight'] = $this->currency->format($orderDetail['freight_per'] + $orderDetail['package_fee'], session('currency'));
                        }

                        //活动+优惠券, 现货+期货+普通 统一逻辑
                        $couponAndCampaignAmount = $orderDetail['coupon_amount'] + $orderDetail['campaign_amount'];
                        if ($couponAndCampaignAmount > 0) {
                            $coupon_and_campaign += 1;
                            $orderDetail['totalPrice'] -= $couponAndCampaignAmount;
                            $orderDetail['coupon_and_campaign_show'] = '-' . $this->currency->format($couponAndCampaignAmount, session('currency'));
                        } else {
                            $orderDetail['coupon_and_campaign_show'] = '-' . $this->currency->format(-0, session('currency'));
                        }
                        $orderDetail['total'] = $this->currency->format($orderDetail['totalPrice'], session('currency'));

                        $orderDetail['orderHistoryUrl'] = $this->url->link('account/order/purchaseOrderInfo', "order_id=" . $orderDetail['order_id'], true);
                        $orderDetail['contactSeller'] = $this->url->link('customerpartner/profile', "id=" . $orderDetail['seller_id'] . "&contact=1", true);
                        // 装配Order_Id 以及对应的 Buyer_Id
                        //判断返点
                        $this->load->model('customerpartner/rma_management');
                        $rebateInfo = $this->model_customerpartner_rma_management->getRebateInfo($orderDetail['order_id'], $orderDetail['product_id']);
                        $maxRefundMoney = $orderDetail['totalPrice'];
                        $canRefundMoney = $maxRefundMoney;
                        $tipBuyerMsg = '';
                        if (!empty($rebateInfo) && $customerOrder['order_status'] == CustomerSalesOrderStatus::CANCELED) {
                            $checkRebateInfo = app(RebateRepository::class)
                                ->checkRebateRefundMoney(
                                    $canRefundMoney,
                                    $orderDetail['qty'],
                                    $orderDetail['order_id'],
                                    $orderDetail['product_id'],
                                    false
                                );
                            $refundRange = $checkRebateInfo['refundRange'] ?? [];
                            $canRefundMoney = $refundRange[$orderDetail['qty']] ?? $canRefundMoney;
                            $tipBuyerMsg = $checkRebateInfo['buyerMsg'];
                        }
                        $flag = true;
                        foreach ($orderItemInfos as &$orderItemInfo) {
                            if ($orderItemInfo['orderId'] == $orderDetail['order_id']) {
                                $flag = false;
                                $sellerItemInfos = array(
                                    'orderStatus' => $customerOrder['order_status'],
                                    "sellerName" => $orderDetail['screenname'],
                                    "sellerId" => $orderDetail['seller_id'],
                                    "qty" => $orderDetail['qty'],
                                    "transactionFee" => $orderDetail['transactionFee'] . '',
                                    "totalPrice" => $canRefundMoney . '',
                                    'msg_rebate_refund' => $tipBuyerMsg,
                                );
                                $orderItemInfo["items"][] = $sellerItemInfos;
                                break;
                            }
                        }
                        if ($flag) {
                            $sellerItemInfos = array(
                                'orderStatus' => $customerOrder['order_status'],
                                "sellerName" => $orderDetail['screenname'],
                                "sellerId" => $orderDetail['seller_id'],
                                "qty" => $orderDetail['qty'],
                                "transactionFee" => $orderDetail['transactionFee'] . '',
                                "totalPrice" => $canRefundMoney . '',
                                'msg_rebate_refund' => $tipBuyerMsg,
                            );
                            $orderItemInfos[] = array(
                                "orderId" => $orderDetail['order_id'],
                                "items" => array($sellerItemInfos)
                            );
                        }
                        $orderDetails[] = $orderDetail;
                    }
                }
                $itemCodeItemInfo = array(
                    "itemCode" => $customerOrderLine['item_code'],
                    "salesOrderLineId" => $customerOrderLine['id'],
                    "items" => $orderItemInfos
                );
                $customerOrderLine['coupon_and_campaign'] = $coupon_and_campaign;
                $customerOrderLine['orderDetails'] = $orderDetails;
                $data['hasReorder'] = json_encode($reorder != null ? true : false);
                $selectItems[] = $itemCodeItemInfo;
            }
            // ItemCode->OrderId->Store选择项
            $data['selectItems'] = json_encode($selectItems);
            $data['customerOrderLines'] = $customerOrderLines;
        }

        // 获取退货理由
        $rmaReason = $this->model_account_rma_management->getRmaReason($customerOrder['order_status']);
        $data['rmaReason'] = $rmaReason;
        $data['rma_order_id'] = $rma_order_id;
        // 获取订单状态
        $customer_order_status = $this->model_account_rma_management->getCustomerOrderStatus();
        $data['customer_order_status'] = $customer_order_status;

        $data['continue'] = $this->url->link('account/account');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');
        $data['is_margin'] = $is_margin;
        // 查询销售单是否购买保障服务
        if (isset($customerOrder['order_status']) && $customerOrder['order_status'] == CustomerSalesOrderStatus::COMPLETED) {
            $data['safeguard_bill_list'] = app(SafeguardBillRepository::class)->getSafeguardBillsBySaleId($data['sales_order_id'] ?? 0, SafeguardBillStatus::ACTIVE);
        }

        $this->response->setOutput($this->load->view('account/rma_management/edit_rma', $data));
    }

    /**
     * 删除RMA附件
     */
    public function deleteRMAImage()
    {
        // 判断用户是否登录
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/rma_management', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
            }
        $this->load->model('account/rma_management');
        $this->model_account_rma_management->deleteRMAImage($this->request->request['id']);
        //删除服务器文件
        unlink(DIR_WORKSPACE . $this->request->request['path']);
    }

    /**
     * 修改RMA
     */
    public function editRmaRequest()
    {
        $this->load->model('account/rma_management');
        $this->load->language('account/rma_management');
        $checkData = $this->checkRmaDataForEdit();
        $buyerId = $this->customer->getId();
        $post = $this->request->input;
        $result = [
            'code' => 200,
            'msg' => [
                'field_name' => '',
                'error' => ''
            ],
            'successUrl' => ''
        ];
        if (count($checkData['error']) > 0) {
            $result['code'] = -1;
            $result['msg'] = [
                'field_name' => $checkData['error'][0]['id'] ?? '',
                'error' => $checkData['error'][0]['errorMsg'] ?? '',
            ];
            goto end;
        }
        $connection = $this->orm->getConnection();
        try {
            $connection->beginTransaction();
            // 默认rma状态ID
            define("DEFAULT_RMA_STATUS_ID", 1);
            // 1. 获取itemCode,orderId,sellerId,rmaQty,reason,comments
            $rmaOrderId = $post->get('rmaOrderId');
            $itemCode = trim($post->get('itemCode'));
            $orderId = (int)$post->get('orderId');
            $customer_order_id = $post->get('customer_order_id');
            $sellerId = (int)$post->get('sellers');
            $rmaQty = (int)$post->get('rmaQty');
            $rmaId = (int)$post->get('rmaId');
            // 获取ASIN
            $asin = $post->get('asin');
            $reason = $post->get('reason') == '' ? null : (int)$this->request->post('reason');
            $comments = $post->get('comments');
            $associated = $checkData['associated'];
            // 2. 插入oc_yzc_rma_order
            $singleCouponAmount = $singleCampaignAmount = 0;
            $alreadyAgreedRmaCount = app(RamRepository::class)
                ->calculateOrderProductApplyedRmaNum($this->customer->getId(), $customer_order_id, $associated[1]['order_product_id']);
            if ($alreadyAgreedRmaCount == 0) {
                $singleCouponAmount = $associated[1]['coupon_amount'];
                $singleCampaignAmount = $associated[1]['campaign_amount'];
            }
            $rmaOrder = [
                "order_id" => $orderId,
                "from_customer_order_id" => $customer_order_id,
                "seller_id" => $sellerId,
                "buyer_id" => $buyerId,
                "admin_status" => null,
                "seller_status" => DEFAULT_RMA_STATUS_ID,
                "cancel_rma" => false,
                "solve_rma" => false,
                'create_time' => date('Y-m-d H:i:s', time()),
                'update_time' => date('Y-m-d H:i:s', time()),
                "update_user_name" => $buyerId,
                "processed_date" => null,
            ];
            $this->model_account_rma_management->updateRmaOrder($rmaOrderId, $rmaOrder);
            // 3.判断有无上传rma文件
            if ($this->request->filesBag->count() > 0) {
                // 有文件上传，将文件保存服务器上并插入数据到表oc_yzc_rma_file
                $files = $this->request->filesBag;
                // 上传RMA文件，以用户ID进行分类
                /** @var UploadedFile $file */
                foreach ($files as $file) {
                    if ($file->isValid()) {
                        // 变更命名规则
                        $filename = date('Ymd') . '_'
                            . md5((html_entity_decode($file->getClientOriginalName(), ENT_QUOTES, 'UTF-8') . micro_time()))
                            . '.' . $file->getClientOriginalExtension();
                        StorageCloud::rmaFile()->writeFile($file, $buyerId, $filename);
                        // 插入文件数据
                        $rmaFile = [
                            'rma_id' => $rmaId,
                            'file_name' => $file->getClientOriginalName(),
                            'size' => $file->getSize(),
                            'file_path' => $buyerId . '/' . $filename,
                            'buyer_id' => $buyerId
                        ];
                        $this->model_account_rma_management->addRmaFile($rmaFile);
                    }
                }
            }
            // 4.插入RMA明细数据，oc_yzc_rma_order_product
            // 4.1 判断RMA Type (1.仅重发、2.仅退款、3.即重发又退款)
            $rmaTypes = $post->get('rma_type');
            $rmaType = null;
            if (count($rmaTypes) > 1) {
                // 选择了两个以上类型
                if ($rmaTypes[0] == 1 && $rmaTypes[1] == 2) {
                    $rmaType = 3;
                }
            } else {
                $rmaType = (int)$rmaTypes[0];
            }
            // 申请退款金额
            $refundAmount = null;
            if (in_array($rmaType, [2, 3]) && trim($post->get('refund_amount', '')) != '') {
                $refundAmount = (float)$post->get('refund_amount');
            }
            $rmaOrderProduct = [
                "rma_id" => $rmaId,
                "product_id" => $associated[1]['product_id'],
                "item_code" => $itemCode,
                "quantity" => $rmaQty,
                "reason_id" => $reason,
                'asin' => $asin,
                "order_product_id" => $associated[1]['order_product_id'],
                "comments" => $comments,
                "rma_type" => $rmaType,
                "apply_refund_amount" => $refundAmount,
                "status_reshipment" => 0,
                "status_refund" => 0,
                "reshipment_type" => null,
                "refund_type" => null,
                "seller_reshipment_comments" => null,
                "seller_refund_comments" => null,
            ];
            //退款2 + 退款重发3  才考虑退优惠券
            if ($rmaType == 1) {
                $rmaOrderProduct['coupon_amount'] = 0;
                $rmaOrderProduct['campaign_amount'] = 0;
            } else {
                if (in_array($rmaType, RmaApplyType::getRefund())) {
                    $rmaOrderProduct['coupon_amount'] = $singleCouponAmount;
                    $rmaOrderProduct['campaign_amount'] = $singleCampaignAmount;
                }
            }

            $this->model_account_rma_management->updateRmaOrderProduct($rmaId, $rmaOrderProduct);
            // 插入重发单数据
            if (in_array($rmaType, [1, 3])) {
                $sales_order_id = $associated[1]['sales_order_id'];
                $sales_order_line_id = $associated[1]['sales_order_line_id'];
                // 获取顾客重发单
                $reorder = $customerOrder = $this->model_account_rma_management->getReorderByRmaId($rmaId);
                // 获取原始订单
                if (!isset($customerOrderMap[$sales_order_id])) {
                    $customerOrderMap[$sales_order_id] = $this->model_account_rma_management->getCustomerOrderById($sales_order_id);
                }
                // 获取原始订单明细
                if (!isset($customerOrderLineMap[$sales_order_line_id])) {
                    $customerOrderLineMap[$sales_order_line_id] = $this->model_account_rma_management->getCustomerOrderLineById($sales_order_line_id);
                }

                $orderMode = $customerOrderMap[$sales_order_id]->order_mode ?? CustomerSalesOrderMode::DROP_SHIPPING;
                $shipToAddress = trim($post->get('re_ship_to_address'));
                $shipToAddressLen = strlen($shipToAddress);
                $len = $shipToAddressLen;  // 默认就取传入的长度, 防止下面没有判断的

                if ($orderMode == CustomerSalesOrderMode::DROP_SHIPPING) {
                    $salesOrderId = $customerOrderMap[$sales_order_id]->id ?? 0;
                    $countryId = $this->customer->getCountryId();
                    $isLTL = ($countryId == AMERICAN_COUNTRY_ID) ? app(CustomerSalesOrderRepository::class)->isLTL($this->customer->getCountryId(),
                        app(CustomerSalesOrderRepository::class)->getItemCodesByHeaderId(intval($salesOrderId))) : false;

                    if (!$isLTL) {
                        if ($countryId == AMERICAN_COUNTRY_ID) {
                            $len = $this->config->get('config_b2b_address_len_us1');
                            // pobox 地区判断
                            if (AddressHelper::isPoBox($shipToAddress)) {
                                $connection->rollBack();
                                $result['code'] = -1;
                                $result['msg'] = [
                                    'field_name' => 're_ship_to_address',
                                    'error' => 'Ship-To Address in P.O.BOX doesn\'t support delivery,Please see the instructions.'
                                ];
                                return $this->response->json($result);
                            }
                            // 偏远地区判断
                            if (AddressHelper::isRemoteRegion($post->get('re_ship_to_state'))) {
                                $connection->rollBack();
                                $result['code'] = -1;
                                $result['msg'] = [
                                    'field_name' => 're_ship_to_state',
                                    'error' => 'ShipToState in PR, AK, HI, GU, AA, AE, AP doesn\'t support delivery,Please see the instructions'
                                ];
                                return $this->response->json($result);
                            }
                        } else if ($countryId == UK_COUNTRY_ID) {
                            $len = $this->config->get('config_b2b_address_len_uk');
                        } else if ($countryId == DE_COUNTRY_ID) {
                            $len = $this->config->get('config_b2b_address_len_de');
                        } else if ($countryId == JAPAN_COUNTRY_ID) {
                            $len = $this->config->get('config_b2b_address_len_jp');
                        }
                    } else {
                        $len = $this->config->get('config_b2b_address_len');
                    }
                } else if ($orderMode == CustomerSalesOrderMode::PICK_UP) {
                    $len = $this->config->get('config_b2b_address_len');
                }

                if ($shipToAddressLen > $len) {
                    $connection->rollBack();
                    $result['code'] = -1;
                    $result['msg'] = [
                        'field_name' => 're_ship_to_address',
                        'error' => "Ship-To Address: maximum length is {$len} characters"
                    ];
                    return $this->response->json($result);
                }

                // 根据rma id统计有几个重发单
                $reOrderCount = $this->model_account_rma_management->getReorderCountByRmaId($rmaId);
                // 根据sales_order_id统计有几个重发单
                $salesOrderReOrderCount = $this->model_account_rma_management->getReorderCountByCustomerOrderId($sales_order_id);
                // 订单头表数据
                if ($reOrderCount != 0) {
                    $customerSalesReorder = [
                        "rma_id" => $rmaId,
                        "sales_order_id" => $sales_order_id,
                        "reorder_date" => date("Y-m-d H:i:s", time()),
                        "email" => $post->get('re_ship_to_email'),
                        "ship_name" => $post->get('re_ship_to_name'),
                        "ship_address" => $post->get('re_ship_to_address'),
                        "ship_city" => $post->get('re_ship_to_city'),
                        "ship_state" => $post->get('re_ship_to_state'),
                        "ship_zip_code" => $post->get('re_ship_to_postal_code'),
                        "ship_country" => $post->get('re_ship_to_country'),
                        "ship_phone" => $post->get('re_ship_to_phone'),
                        "ship_method" => $post->get('re_ship_to_service'),
                        "ship_service_level" => $post->get('re_ship_to_service_level'),
                        "ship_company" => $customerOrderMap[$sales_order_id]->ship_company,
                        "store_name" => $customerOrderMap[$sales_order_id]->store_name,
                        "store_id" => $customerOrderMap[$sales_order_id]->store_id,
                        "buyer_id" => $buyerId,
                        "order_status" => 1,
                        "sell_manager" => $customerOrderMap[$sales_order_id]->sell_manager,
                        "update_user_name" => $buyerId,
                        "update_time" => date("Y-m-d H:i:s", time()),
                        "program_code" => PROGRAM_CODE
                    ];
                    // 修改重发订单头表数据
                    $this->model_account_rma_management->updateReOrder($customerSalesReorder, $rmaId);
                    // 保存重发订单明细表
                    $customerSalesReorderLine = [
                        "line_item_number" => 1,
                        "product_name" => $customerOrderLineMap[$sales_order_line_id]->product_name,
                        "qty" => $post->get('re_ship_to_qty'),
                        "item_code" => $itemCode,
                        "product_id" => $associated[1]['product_id'],
                        "image_id" => $customerOrderLineMap[$sales_order_line_id]->image_id == null
                            ? 1
                            : $customerOrderLineMap[$sales_order_line_id]->image_id,
                        "seller_id" => $associated[1]['seller_id'],
                        "item_status" => 1,
                        "update_user_name" => $buyerId,
                        "update_time" => date("Y-m-d H:i:s", time()),
                        "program_code" => PROGRAM_CODE
                    ];
                    $this->model_account_rma_management->updateReOrderLine($customerSalesReorderLine, $reorder->id);
                } else {
                    $yzc_order_id_number = $this->sequence->getYzcOrderIdNumber();
                    $yzc_order_id_number++;
                    $customerSalesReorder = [
                        "rma_id" => $rmaId,
                        "yzc_order_id" => "YC-" . $yzc_order_id_number,
                        "sales_order_id" => $sales_order_id,
                        "reorder_id" => $customerOrderMap[$sales_order_id]->order_id . '-' . 'R' . '-' . ($salesOrderReOrderCount + 1),
                        "reorder_date" => date("Y-m-d H:i:s", time()),
                        "email" => $post->get('re_ship_to_email'),
                        "ship_name" => $post->get('re_ship_to_name'),
                        "ship_address" => $post->get('re_ship_to_address'),
                        "ship_city" => $post->get('re_ship_to_city'),
                        "ship_state" => $post->get('re_ship_to_state'),
                        "ship_zip_code" => $post->get('re_ship_to_postal_code'),
                        "ship_country" => $post->get('re_ship_to_country'),
                        "ship_phone" => $post->get('re_ship_to_phone'),
                        "ship_method" => $post->get('re_ship_to_service'),
                        "ship_service_level" => $post->get('re_ship_to_service_level'),
                        "ship_company" => $customerOrderMap[$sales_order_id]->ship_company,
                        "store_name" => $customerOrderMap[$sales_order_id]->store_name,
                        "store_id" => $customerOrderMap[$sales_order_id]->store_id,
                        "buyer_id" => $buyerId,
                        "order_status" => 1,
                        "sell_manager" => $customerOrderMap[$sales_order_id]->sell_manager,
                        "create_user_name" => $buyerId,
                        "create_time" => date("Y-m-d H:i:s", time()),
                        "program_code" => PROGRAM_CODE
                    ];
                    // 保存重发订单头表数据
                    $customerSalesReorder = $this->model_account_rma_management->addReOrder($customerSalesReorder);
                    // 保存重发订单明细表
                    $customerSalesReorderLine = [
                        "reorder_header_id" => $customerSalesReorder->id,
                        "line_item_number" => 1,
                        "product_name" => $customerOrderLineMap[$sales_order_line_id]->product_name,
                        "qty" => $post->get('re_ship_to_qty'),
                        "item_code" => $itemCode,
                        "product_id" => $associated[1]['product_id'],
                        "image_id" => $customerOrderLineMap[$sales_order_line_id]->image_id == null
                            ? 1
                            : $customerOrderLineMap[$sales_order_line_id]->image_id,
                        "seller_id" => $associated[1]['seller_id'],
                        "item_status" => 1,
                        "create_user_name" => $buyerId,
                        "create_time" => date("Y-m-d H:i:s", time()),
                        "program_code" => PROGRAM_CODE
                    ];
                    $this->model_account_rma_management->addReOrderLine($customerSalesReorderLine);
                }
            } else {
                //删除重发单
                $this->model_account_rma_management->deleteReorder($rmaId);
            }
            $this->rmaCommunication($rmaId);
            $result['code'] = 200;
            $result['msg'] = 'success';
            $result['successUrl'] = $this->url->link('account/rma_management');
            $connection->commit();
        } catch (Exception $e) {
            $connection->rollBack();
            Logger::error($e);
            $result['code'] = 500;
            $result['msg'] = 'error';
            goto end;
        }
        end:
        return $this->response->json($result);
    }

    private function checkRmaDataForEdit()
    {
        $index = $this->request->post['index'];
        $error = array();
        $associatedArr = array();
        $rmaQtyArr = array();
        $reshipmentQtyArr = array();
        $indexArr = array();
        // 销售订单ID
        $sales_order_id = $this->request->post['sales_order_id'];
        $buyer_id = $this->customer->getId();
        // orderFrom
        $orderFrom = $this->request->post['orderFrom'];
        if (strtolower($orderFrom) == 'amazon' || strtolower($orderFrom) == 'amazonshz') {
            $isAmazon = true;
        } else {
            $isAmazon = false;
        }
        foreach ($index as $i) {
            //判断seller的处理状态
            $canCancelOrEdit = $this->canEditRma();
            if ($canCancelOrEdit == 0) {
                $error[] = array(
                    "displayType" => 1,
                    "errorMsg" => "RMA Status is changed, can not be edited."
                );
            }
            // 1.校验ItemCode+OrderId+Store 是否有重复的
            if (isset($this->request->post['itemCode']) && isset($this->request->post['orderId']) && isset($this->request->post['sellers'])) {
                $ios = $this->request->post['itemCode'] . $this->request->post['orderId'] . $this->request->post['sellers'];
                if (!isset($indexArr[$ios])) {
                    $indexArr[$ios] = $i;
                }
                // 获取rma_type
                $rmaType = null;
                if (!isset($this->request->post['rma_type'])) {
                    // 没有选择RMA类型
                    if (isset($this->request->post['orderStatus']) && $this->request->post['orderStatus'] == 'Cancelled') {
                        $error[] = array(
                            "id" => array('collapseOne', 'collapseTwo'),
                            "href" => 'collapseOne',
                            "displayType" => 1,
                            "errorMsg" => "Please select refund option."
                        );
                    } else {
                        $error[] = array(
                            "id" => array('collapseOne', 'collapseTwo'),
                            "href" => 'collapseOne',
                            "displayType" => 1,
                            "errorMsg" => "Please select at least one reshipment or refund option."
                        );
                    }
                } else {
                    // 校验退款金额
                    $rmaTypes = $this->request->post['rma_type'];
                    if (count($rmaTypes) > 1) {
                        // 选择了两个以上类型
                        if ($rmaTypes[0] == 1 && $rmaTypes[1] == 2) {
                            $rmaType = 3;
                        }
                    } else {
                        $rmaType = $rmaTypes[0];
                    }
                    if ($rmaType == 2 || $rmaType == 3) {
                        if (!isset($this->request->post['refund_amount']) || trim($this->request->post['refund_amount']) == '') {
                            $error[] = array(
                                "id" => 'refund_amount',
                                "href" => 'refund_amount',
                                "displayType" => 2,
                                "errorMsg" => "Please fill in the refund amount of the application."
                            );
                        } else {
                            $totalPrice = $this->request->post['totalPrice'];
                            if ($this->request->post['refund_amount'] > $totalPrice) {
                                $error[] = array(
                                    "id" => 'refund_amount',
                                    "href" => 'refund_amount',
                                    "displayType" => 2,
                                    "errorMsg" => "The amount entered exceeds the total amount of the order details!"
                                );
                            } else if ((double)$this->request->post['refund_amount'] == 0) {
                                $error[] = array(
                                    "id" => 'refund_amount',
                                    "href" => 'refund_amount',
                                    "displayType" => 2,
                                    "errorMsg" => "The refund amount can't fill in 0."
                                );
                            }
                        }
                    }
                }
                // 校验数量
                // 获取销售订单明细所对应的强绑定数据
                $order_id = $this->request->post['orderId'];
                $item_code = $this->request->post['itemCode'];
                $seller_id = $this->request->post['sellers'];
                $associated = $this->model_account_rma_management->getOrderAssociated(
                    $sales_order_id, $item_code, $buyer_id, $order_id, $seller_id
                );
                $associatedArr[$i] = $associated;
                $rmaQty = null;
                if (isset($this->request->post['rmaQty'])) {
                    $rmaQty = $this->request->post['rmaQty'];
                    if ($rmaQty == '') {
                        $error[] = array(
                            "id" => "rmaQty",
                            "href" => 'rmaQty',
                            "displayType" => 1,
                            "errorMsg" => $this->language->get('error_enter_rma_qty')
                        );
                    } else {
                        // 统计rmaQty总数
                        if (isset($rmaQtyArr[$ios])) {
                            $rmaQtyArr[$ios] += (int)$rmaQty;
                        } else {
                            $rmaQtyArr[$ios] = (int)$rmaQty;
                        }
                        // 单一总数不能大于购买数
                        if ($rmaQty > $associated['qty']) {
                            $error[] = array(
                                "id" => "rmaQty",
                                "href" => 'rmaQty',
                                "displayType" => 1,
                                "errorMsg" => $this->language->get('error_enter_ship_to_qty_max')
                            );
                        }
                    }
                }

                if (isset($this->request->post['rma_type'])) {
                    if ($rmaType == 1 || $rmaType == 3) {
                        if (isset($this->request->post['re_ship_to_qty'])) {
                            $reQty = $this->request->post['re_ship_to_qty'];
                            if ($reQty > $associated['qty']) {
                                $error[] = array(
                                    "id" => "re_ship_to_qty",
                                    "href" => 're_ship_to_qty',
                                    "displayType" => 2,
                                    "errorMsg" => $this->language->get('error_enter_ship_to_qty_max')
                                );
                            }
                            // 统计reshipment总数
                            if (isset($reshipmentQtyArr[$ios])) {
                                $reshipmentQtyArr[$ios] += (int)$reQty;
                            } else {
                                $reshipmentQtyArr[$ios] = (int)$reQty;
                            }
//                            if ($rmaQty != null) {
//                                if ($reQty > (int)$rmaQty) {
//                                    $error[] = array(
//                                        "id" => "re_ship_to_qty" . $i,
//                                        "href" => 're_ship_to_qty' . $i,
//                                        "displayType" => 2,
//                                        "errorMsg" => $this->language->get('error_enter_ship_to_qty_more_than_rmaqty')
//                                    );
//                                }
//                            }
                        }
                    }
                }
            }
            // Amazon 订单必填项ASIN
            if ($isAmazon) {
//                if ((!isset($this->request->post['asin'])) || trim($this->request->post['asin']) == '') {
//                    $error[] = array(
//                        "id" => array("asin"),
//                        "href" => 'asin',
//                        "displayType" => 1,
//                        "errorMsg" => $this->language->get('error_enter_asin')
//                    );
//                }
            }
            // 校验理由，理由为必填项
            if ((!isset($this->request->post['reason'])) || trim($this->request->post['reason']) == '') {
                $error[] = array(
                    "id" => "reason",
                    "href" => 'reason',
                    "displayType" => 2,
                    "errorMsg" => $this->language->get('error_enter_reason')
                );
            }

            // 校验图片，本次没有上传图片，则必须存在历史图片
            if ($this->request->filesBag->count() == 0) {
                // 判断该RAM记录下面是否存在图片，不存在则不允许提交
                $imgIsExist = $this->model_account_rma_management->checkRmaOrderFileExist($this->request->input->get('rmaId', 0), 1);
                if (!$imgIsExist) {
                    $error[] = array(
                        "id" => "appFile",
                        "href" => 'appFile',
                        "displayType" => 2,
                        "errorMsg" => $this->language->get('error_enter_upload_img')
                    );
                }
            }


            // 2.校验必填项(重发)
            if (isset($this->request->post['rma_type'])) {
                if ((count($this->request->post['rma_type']) > 1 && $this->request->post['rma_type'][0] == '1') || $this->request->post['rma_type'][0] == '1') {
                    // 2.1 收货人姓名
                    if ((!isset($this->request->post['re_ship_to_name'])) || trim($this->request->post['re_ship_to_name']) == '') {
                        $error[] = array(
                            "id" => "re_ship_to_name",
                            "href" => 're_ship_to_name',
                            "displayType" => 2,
                            "errorMsg" => $this->language->get('error_enter_ship_to_name')
                        );
                    }
                    // 2.2 收货人邮箱
                    if ((!isset($this->request->post['re_ship_to_email'])) || trim($this->request->post['re_ship_to_email']) == '') {
                        $error[] = array(
                            "id" => "re_ship_to_email",
                            "href" => 're_ship_to_email',
                            "displayType" => 2,
                            "errorMsg" => $this->language->get('error_enter_ship_to_email')
                        );
                    }
                    // 2.3 收货人国家
                    if ((!isset($this->request->post['re_ship_to_country'])) || trim($this->request->post['re_ship_to_country']) == '') {
                        $error[] = array(
                            "id" => "re_ship_to_country",
                            "href" => "re_ship_to_country",
                            "displayType" => 2,
                            "errorMsg" => $this->language->get('error_enter_ship_to_country')
                        );
                    }
                    // 2.4 收货人城市
                    if ((!isset($this->request->post['re_ship_to_city'])) || trim($this->request->post['re_ship_to_city']) == '') {
                        $error[] = array(
                            "id" => "re_ship_to_city",
                            "href" => "re_ship_to_city",
                            "displayType" => 2,
                            "errorMsg" => $this->language->get('error_enter_ship_to_city')
                        );
                    }
                    // 2.5 收货数量
                    if (!isset($this->request->post['re_ship_to_qty']) || trim($this->request->post['re_ship_to_qty']) == '') {
                        $error[] = array(
                            "id" => "re_ship_to_qty",
                            "href" => "re_ship_to_qty",
                            "displayType" => 2,
                            "errorMsg" => $this->language->get('error_enter_ship_to_qty')
                        );
                    } else if ((int)$this->request->post['re_ship_to_qty'] == 0) {
                        $error[] = array(
                            "id" => "re_ship_to_qty",
                            "href" => "re_ship_to_qty",
                            "displayType" => 2,
                            "errorMsg" => "The reshipped quantity can't fill in 0"
                        );
                    }
                    // 2.6 收货人电话
                    if ((!isset($this->request->post['re_ship_to_phone'])) || trim($this->request->post['re_ship_to_phone']) == '') {
                        $error[] = array(
                            "id" => "re_ship_to_phone",
                            "href" => "re_ship_to_phone",
                            "displayType" => 2,
                            "errorMsg" => $this->language->get('error_enter_ship_to_phone')
                        );
                    }
                    // 2.7 收货人邮编
                    if ((!isset($this->request->post['re_ship_to_postal_code'])) || trim($this->request->post['re_ship_to_postal_code']) == '') {
                        $error[] = array(
                            "id" => "re_ship_to_postal_code",
                            "href" => "re_ship_to_postal_code",
                            "displayType" => 2,
                            "errorMsg" => $this->language->get('error_enter_ship_to_postal_code')
                        );
                    }
                    // 2.8 收货人 州/地区
                    if ((!isset($this->request->post['re_ship_to_state'])) || trim($this->request->post['re_ship_to_state']) == '') {
                        $error[] = array(
                            "id" => "re_ship_to_state",
                            "href" => "re_ship_to_state",
                            "displayType" => 2,
                            "errorMsg" => $this->language->get('error_enter_ship_to_state')
                        );
                    }
                    // 2.9 收获地址
                    if ((!isset($this->request->post['re_ship_to_address'])) || trim($this->request->post['re_ship_to_address']) == '') {
                        $error[] = array(
                            "id" => "re_ship_to_address",
                            "href" => "re_ship_to_address",
                            "displayType" => 2,
                            "errorMsg" => $this->language->get('error_enter_ship_to_address')
                        );
                    }
                    // 3. 校验文件上传是否正确
                }
            }
        }
        //查询是否存在保证金头款商品
        $get_post_rma_data = array(
            'item_code' => $this->request->post["itemCode"],
            'orderId' => $this->request->post["orderId"]
        );
        $get_agreement_product = $this->model_account_rma_management->get_agreement_product($get_post_rma_data);
        if ($get_agreement_product) {   //存在保证金头款商品
            $error[] = array(
                "id" => array("selectItemCode" . $i, "selectOrderId" . $i, "selectSellers" . $i),
                "href" => 'selectItemCode' . $i,
                "displayType" => 1,
                "errorMsg" => "ItemCode:" . $this->request->post['itemCode'] . " OrderId:" . $this->request->post['orderId'] . " Store:&nbsp;&nbsp;" . $this->language->get('error_margin_not_retrun'),
            );
        }
        // 校验数量
        if (count($rmaQtyArr) > 0) {
            foreach ($rmaQtyArr as $key => $value) {
                // 获取强绑定数据
                $i = $indexArr[$key];
                $associated = $associatedArr[$i];
                // 查询历史该退货的数量
                $qty = $associated['qty'];
                if ($value > $qty) {
                    $sellerName = $this->model_account_rma_management->getStoreNameBySellerId($this->request->post['sellers']);
                    $error[] = array(
                        "id" => array("selectItemCode", "selectOrderId", "selectSellers"),
                        "href" => 'selectItemCode',
                        "displayType" => 1,
                        "errorMsg" => "ItemCode:" . $this->request->post['itemCode'] . " OrderId:" . $this->request->post['orderId'] . " Store:" . $sellerName['sellerName'] . " &nbsp;&nbsp;The total number of applications exceeds the number of purchases!"
                    );
                }
            }
        }
        if (count($reshipmentQtyArr) > 0) {
            foreach ($reshipmentQtyArr as $key => $value) {
                // 获取强绑定数据
                $i = $indexArr[$key];
                $associated = $associatedArr[$i];
                // 查询历史该退货的数量
                $qty = $associated['qty'];
                if ($value > $qty) {
                    $sellerName = $this->model_account_rma_management->getStoreNameBySellerId($this->request->post['sellers']);
                    $error[] = array(
                        "id" => "re_ship_to_qty",
                        "href" => 're_ship_to_qty',
                        "displayType" => 2,
                        "errorMsg" => "ItemCode:" . $this->request->post['itemCode'] . " OrderId:" . $this->request->post['orderId'] . " Store:" . $sellerName['sellerName'] . " &nbsp;&nbsp;The total number of reshipment quantity exceeds the number of purchases!"
                    );
                }
            }
        }
        $result = array(
            'error' => $error,
            'associated' => $associatedArr
        );
        return $result;
    }

    public function checkRma()
    {
        // 判断用户是否登录
        if (!$this->customer->isLogged()) {
            $json['error'] = true;
            $json['url'] = $this->url->link('account/login', '', true);
        }
        if (isset($this->request->post['customer_order_id'])) {
            $customer_order_id = $this->request->post['customer_order_id'];
        } else {
            $customer_order_id = null;
        }
        $customer_id = $this->customer->getId();
        $this->load->model('account/rma_management');
        if ($customer_order_id != null) {
            $countCwf = $this->model_account_rma_management->checkCwfRmaByOrderId($customer_id, $customer_order_id);
            $count = $this->model_account_rma_management->checkProcessingRmaByOrderId($customer_id, $customer_order_id);
            $json['success'] = true;
            $json['count'] = $count;
            $json['countCwf'] = $countCwf;
        } else {
            $json['error'] = true;
            $json['url'] = $this->url->link('account/rma_management', '', true);
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function checkCanEditRma()
    {
        $canCancelOrEdit = $this->canEditRma();
        if ($canCancelOrEdit == 0) {
            $json['error'] = 'RMA Status is changed, can not be edited.';
            $json['status'] = false;
        } else {
            $json['status'] = true;
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    private function canEditRma($rma_id = null)
    {
        $this->load->model('account/rma_management');
        // 兼容之前的写法
        $rma_id = $rma_id ?? $this->request->post['rmaOrderId'];
        $filter_data = array(
            "rma_id" => $rma_id,
            "cancelFlag" => false
        );
        $results = $this->model_account_rma_management->getRmaOrderInfo($filter_data);
        $result = $results[0];
        // 判断RMA能否被cancel applied+被refuse的订单可以
        //采购订单的修改,需判断采购数量与绑定数量
        if (
            $result['seller_status'] == static::RMA_PROCESSED
            || (
                $result['seller_status'] == static::RMA_PENDING
                && ($result['status_refund'] != 1 && $result['status_reshipment'] != 1)
            )
        ) {
            //采购订单的修改,需判断采购数量与绑定数量
            if ($result['order_type'] == 2) {
                $this->load->model('account/rma/manage');
                $noBindingInfo = $this->model_account_rma_manage
                    ->getPurchaseOrderInfo($result['b2b_order_id'], $result['product_id']);
                if ($noBindingInfo['quantity'] == 0) {
                    $canCancelOrEdit = 0;
                } else {
                    $canCancelOrEdit = 1;
                }
            } else {
                //销售订单的RMA修改,如果有未处理的相同order_id的RMA不允许被修改
                $countNoProcessedRMA = $this->model_account_rma_management
                    ->countNoProcessedRMABySaleOrderId($result['from_customer_order_id'], $result['buyer_id'], $result['seller_id'], $results[0]['rma_id']);
                $canCancelOrEdit = $countNoProcessedRMA > 0 ? 0 : 1;
            }
        } else {
            $canCancelOrEdit = 0;
        }
        return $canCancelOrEdit;
    }
}
