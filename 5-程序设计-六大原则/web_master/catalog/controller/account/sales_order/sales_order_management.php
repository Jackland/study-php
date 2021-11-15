<?php

use App\Catalog\Controllers\BaseController;
use App\Catalog\Forms\SalesOrder\Import\EbayRowValidate;
use App\Enums\Buyer\BuyerType;
use App\Enums\FeeOrder\FeeOrderFeeType;
use App\Enums\ModifyLog\CommonOrderProcessCode;
use App\Enums\Product\ProductTransactionType;
use App\Components\Storage\StorageCloud;
use App\Enums\Product\ProductType;
use App\Enums\Safeguard\SafeguardBillStatus;
use App\Enums\Safeguard\SafeguardClaimStatus;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Enums\SalesOrder\HomePickImportMode;
use App\Models\Product\Product;
use App\Models\Safeguard\SafeguardSalesOrderErrorLog;
use App\Models\SalesOrder\CustomerSalesOrderLine;
use App\Models\Track\CountryState;
use App\Repositories\Buyer\BuyerRepository;
use App\Repositories\Order\CountryStateRepository;
use App\Repositories\Margin\MarginRepository;
use App\Repositories\Safeguard\SafeguardBillRepository;
use App\Repositories\Safeguard\SafeguardConfigRepository;
use App\Logging\Logger;
use App\Repositories\FeeOrder\FeeOrderRepository;
use App\Repositories\FeeOrder\StorageFeeRepository;
use App\Repositories\Marketing\CouponRepository;
use App\Repositories\Order\OrderRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\SalesOrder\CustomerSalesOrderRepository;
use App\Repositories\Track\TrackRepository;
use App\Services\SalesOrder\SalesOrderService;
use Catalog\model\account\sales_order\SalesOrderManagement as sales_model;
use Framework\Exception\Http\NotFoundException;
use kriss\bcmath\BCS;
use App\Helper\CountryHelper;
use App\Enums\Track\TrackStatus;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * 一件代发Buyer导入销售单
 * @property ModelToolExcel $model_tool_excel
 * @property ModelAccountCustomerOrderImport $model_account_customer_order_import;
 * @property ModelAccountCustomerOrder $model_account_customer_order;
 * @property ModelAccountSalesOrderMatchInventoryWindow $model_account_sales_order_match_inventory_window;
 * @property ModelExtensionTotalGigaCoupon $model_extension_total_giga_coupon;
 * @property ModelExtensionTotalPromotionDiscount $model_extension_total_promotion_discount;
 * @property ModelToolCsv $model_tool_csv;
 * @property ModelToolImage $model_tool_image;
 * @property ModelAccountTicket $model_account_ticket;
 */
class ControllerAccountSalesOrderSalesOrderManagement extends BaseController
{

    private $customer_id = null;
    private $country_id;
    private $group_id;
    private $isCollectionFromDomicile;
    private $isPartner = false;
    private $sales_model;
    protected $tracking_privilege;
    protected $modelPreOrder;
    private $order_status = [
        '*' => 'ALL',
        '1' => 'To Be Paid',
        '2' => 'Being Processed',
        '4' => 'On Hold',
        '16' => 'Canceled',
        '32' => 'Completed',
        '64' => 'LTL Check',
        '128' => 'ASR To Be Paid',
    ];

    private $action_btn = [
        '1'   =>['view','cancel','address','paid'],
        '2'   =>['view','cancel','address'], // bp状态下先取消address
        '4'   =>['view','cancel','released'],
        '16'  =>['view','rma'],
        '32'  =>['view','rma'],
        '64'  =>['view','cancel','address','email'],
        '128' =>['view','cancel','address','asr'],

    ];

    public function __construct($registry,ModelCheckoutPreOrder $modelCheckoutPreOrder)
    {
        parent::__construct($registry);
        $this->customer_id = $this->customer->getId();
        $this->isPartner = $this->customer->isPartner();
        $this->country_id = $this->customer->getCountryId();
        $this->group_id = $this->customer->getGroupId();
        $this->isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
        $this->modelPreOrder = $modelCheckoutPreOrder;
        $this->load->language('account/sales_order/sales_order_management');
        $this->sales_model = new sales_model($registry);
        if (empty($this->customer_id) || $this->isPartner) {
            return $this->response->redirectTo(url()->to(['account/login']))->send();
        }
        // 上门取货逻辑走customer_order
        if($this->isCollectionFromDomicile){
            return $this->response->redirectTo(url()->to(['account/customer_order']))->send();
        }
        $this->load->model('account/customer_order');
        $this->load->model('account/customer_order_import');
        $this->tracking_privilege = $this->model_account_customer_order_import->getTrackingPrivilege($this->customer->getId(),$this->customer->isCollectionFromDomicile(),$this->customer->getCountryId());
    }

    public function index()
    {
        //设置title
        $this->document->setTitle($this->language->get('heading_title_drop_shipping'));
        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => url()->to(['common/home'])
        ];
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title_sales_order'),
            'href' => 'javascription:void(0)'
        );

        $data['is_show_wholesale'] = $this->country_id == AMERICAN_COUNTRY_ID ?  true : false;
        $data['is_show_tracking_shipment'] = $this->country_id == AMERICAN_COUNTRY_ID ?  true : false;

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        $data['url_cloud_wholesale_fulfillment'] = url()->to(['account/sales_order/CloudWholesaleFulfillment/tabsPage']);
        // 优先展示的问题 todo
        //需求13548 RMA Management 页面跳转 edit by xxl
        //if (isset($this->request->get['purchase_order_id'])) {
        //    $order_id = $this->request->get['purchase_order_id'];
        //    $data['fromRMALink'] = true;
        //    $data['order_id'] = $order_id;
        //    $data['rmaViewUrl'] = url()->to(['account/customer_order/customerOrderTable', 'filter_orderId' => $order_id, 'filter_tracking_number' => 2]);
        //}
        //end 13548
        //102760 其他页面跳转过来控制tab切换
        $data['tab'] = get_value_or_default($this->request->get, 'tab', 1);
        if ($this->request->query->has('filter_tracking_number')) {
            $data['filter_tracking_number'] = $this->request->get('filter_tracking_number');
        }
        if ($this->request->query->has('filter_orderStatus')) {
            $data['filter_orderStatus'] = $this->request->get('filter_orderStatus');
        }
        if ($this->request->query->has('filter_orderId')) {
            $data['filter_orderId'] = $this->request->get('filter_orderId');
        }
        $this->response->setOutput($this->load->view('account/sales_order/list', $data));
    }

    /**
     * [salesOrderTab description] drop shipping 一件代发分为三个标签页
     * @throws Exception
     */
    public function salesOrderTab()
    {
        $data['importOrder'] = boolval($this->customer->getCustomerExt(2));
        if ($this->request->query->has('filter_tracking_number')) {
            $data['filter_tracking_number'] = $this->request->get('filter_tracking_number');
        }
        if ($this->request->query->has('filter_orderStatus')) {
            $data['filter_orderStatus'] = $this->request->get('filter_orderStatus');
        }
        $data['list'] = changeInputByZone($this->salesOrderList()->getContent(),session('country'));
        $this->response->setOutput($this->load->view('account/sales_order/sales_order_tab', $data));
    }

    /**
     * [salesOrderList description]
     * @throws Exception
     */
    public function salesOrderList()
    {
        // request里面的数据也需要返回
        load()->model('account/customer_order');
        load()->model('account/ticket');
        $data['tracking_privilege'] = $this->tracking_privilege;
        $order_status = $this->order_status;
        $paramTotal = $data = $this->request->request;
        $filter_choose_tag = 0;
        if(isset($data['filter_orderStatus']) && $data['filter_orderStatus'] != '*'){
            $filter_choose_tag = 1;
            $data['filter_choose_tag'] = $filter_choose_tag;
            $data['batch_action_btn'] = $this->action_btn[$data['filter_orderStatus']];
        }
        // url
        $data['order_url'] = url()->to(['account/sales_order/sales_order_management/salesOrderList']);
        $data['download_url'] = url()->to(['account/sales_order/sales_order_management/salesOrderDownloadData']);
        // orders
        $data['page'] = intval($data['page'] ?? 1);
        if(!isset($data['page_limit']) || (int)$data['page_limit'] == 0){
            $data['page_limit'] = 20;
        }
        $order_status_list = $this->sales_model->getSalesOrderStatusList($this->customer_id);
        $data['all_count'] = array_sum(array_column($order_status_list,'count'));
        $orders = $this->sales_model->getSalesOrderList($this->customer_id, $data);
        //获取每一个order的error信息
        $error_info = $this->sales_model->getOrderErrorInfo(array_column($orders,'id'),$this->customer_id,$this->country_id);
        $tracking_array = $this->sales_model->getTrackingNumber(array_column($orders,'id'),array_column($orders,'order_id'));
        $data['trackingSearchShow'] = ($this->country_id == AMERICAN_COUNTRY_ID);
        $tracking = [];
        if($tracking_array){
            foreach($tracking_array as $key => $value){
                $tracking[$value['SalesOrderId']][] = $value;
            }
        }
        //获取所有的line
        $order_line_list = $this->sales_model->getOrderLineInfo(array_column($orders,'id'),$this->customer_id,$this->country_id,$error_info);
        // 获取log信息
        $failure_log_html = $this->sales_model->getOrderModifyFailureLog([CommonOrderProcessCode::CHANGE_ADDRESS,CommonOrderProcessCode::CHANGE_SKU,CommonOrderProcessCode::CANCEL_ORDER],array_column($orders,'id'),$line_id = null);
        //查看绑定关系是否还存在
        $associateOrder = $this->sales_model->checkAssociateOrder(array_column($orders,'id'));
        // bp 时间
        $beingProcessedTimes = app(CustomerSalesOrderRepository::class)->getBeingProcessedTimes(array_column($orders,'id'));
        //查看取消状态已删的绑定关系
        $orderAssociatedCancelRecords = $this->sales_model->getOrderAssociatedCancelRecords(array_column($orders,'id'));
        $salesOrderIdAssociatedCancelRecordsMap = [];
        foreach ($orderAssociatedCancelRecords as $orderAssociatedCancelRecord) {
            if (array_key_exists($orderAssociatedCancelRecord->sales_order_id, $salesOrderIdAssociatedCancelRecordsMap)) {
                $salesOrderIdAssociatedCancelRecordsMap[$orderAssociatedCancelRecord->sales_order_id][] = $orderAssociatedCancelRecord;
            } else {
                $salesOrderIdAssociatedCancelRecordsMap[$orderAssociatedCancelRecord->sales_order_id] = [
                    $orderAssociatedCancelRecord,
                ];
            }
        }

        //获取具体的信息
        $available_order_id = [];
        if(count($orders) == 0
            || ($this->country_id != 223 && isset($data['filter_orderStatus']) && $data['filter_orderStatus'] == 2)
            || (isset($data['filter_orderStatus']) && $data['filter_orderStatus'] == 32)
            || (isset($data['filter_orderStatus']) && $data['filter_orderStatus'] == 16)
        ){
            $filter_choose_tag = 0;
        }
        // 查询销售单的保单信息
        $safeguardBills = app(SafeguardBillRepository::class)->getSafeguardBillsBySaleIds(array_column($orders, 'id'));
        $salesOrderErrorLogs = SafeguardSalesOrderErrorLog::query()->whereIn('sales_order_id', array_column($orders, 'id'))->get()->keyBy('sales_order_id');
        foreach ($orders as $key => &$value) {
            $value['safeguard_bills'] = $safeguardBills[$value['id']] ?? [];
            $value['safeguard_bill_is_active'] = false;
            $value['safeguard_bill_is_pending'] = false;
            $value['safeguard_bill_pay_error'] = isset($salesOrderErrorLogs[$value['id']]);
            // 判断销售单是否有正在生效的保单
            if (!empty($value['safeguard_bills']) && $value['safeguard_bills']->contains('status', SafeguardBillStatus::ACTIVE)) {
                $value['safeguard_bills']->map(function ($bill) use (&$value) {
                    $bill['safeguard_claim_active_count'] = $bill->safeguardClaim->whereIn('status', [SafeguardClaimStatus::CLAIM_IN_PROGRESS, SafeguardClaimStatus::CLAIM_BACKED])->count();
                    $bill['safeguard_claim_succeed_count'] = $bill->safeguardClaim->whereIn('status', [SafeguardClaimStatus::CLAIM_SUCCEED, SafeguardClaimStatus::CLAIM_FAILED])->count();
                    $bill['safeguard_expiration_days'] = ceil(abs((strtotime($bill->expiration_time->toDateTimeString()) - time()) / (3600 * 24)));
                    $bill->safeguardConfig->title = app(SafeguardConfigRepository::class)->geiNewestConfig($bill->safeguardConfig->rid, customer()->getCountryId())->title;
                    if ($bill->effective_time->toDateTimeString() < date('Y-m-d H:i:s') && $bill->expiration_time->toDateTimeString() > date('Y-m-d H:i:s')) {
                        $value['safeguard_bill_is_active'] = true;
                    }
                    return $bill;
                });
            }
            //判断销售单是待生效的保单
            if (!empty($value['safeguard_bills']) && $value['safeguard_bills']->contains('status', SafeguardBillStatus::PENDING)) {
                $value['safeguard_bill_is_pending'] = true;
            }
            //订单状态
            $value['order_status_name'] = $this->order_status[$value['order_status']];
            //获取可以操作的action
            // canceled 只有虚拟支付情况下有rma。
            $value['btn_info'] = $this->action_btn[$value['order_status']];
            //地址拼接
            $ship_phone = ' ';
            if($value['ship_phone']) {
                $ship_phone = '(' . $value['ship_phone'] . ') ';
            }
            $value['detail_address'] =  $value['ship_name'].' '.$ship_phone
                .$value['ship_address1']
                . ',' . $value['ship_city'] . ',' . $value['ship_zip_code']
                . ',' . $value['ship_state'] . ',' . $value['ship_country'];
            $value['address_error'] = '';
            if(isset($error_info[$value['id']]['address_error']) && $error_info[$value['id']]['address_error'] !=''){
                $value['address_error'] = $error_info[$value['id']]['address_error'];
            }
            $value['freight_error'] = (isset($error_info[$value['id']]['freight_error']) && $error_info[$value['id']]['freight_error']) ?  $error_info[$value['id']]['freight_error'] : '';
            //line 表明细
            //$tmp = $this->sales_model->getOrderLineInfo($value['id'],$this->customer_id,$this->country_id,$error_info);
            if(isset($order_line_list[$value['id']]['line_list'])){
                $orders[$key]['line_list'] = $order_line_list[$value['id']]['line_list'];
                // canceled 订单下 只有虚拟支付情况下才有rma
                $purchase_list = isset($order_line_list[$value['id']]['line_list'][0]['purchase_order_list']) ? $order_line_list[$value['id']]['line_list'][0]['purchase_order_list'] : [];
                $value['rma_status'] = $this->sales_model->getRmaStatus($purchase_list,$value['order_status']);
                $value['rowspan'] = count($order_line_list[$value['id']]['line_list']);
                $value['sum'] = $order_line_list[$value['id']]['sum'];
                $value['discount_sum'] = $order_line_list[$value['id']]['discount_sum'];
                $value['final_sum'] = $order_line_list[$value['id']]['final_sum'];
            }
            // 获取总价
            // tracking info
            $saleOrderTrackingLine = [];
            $trackingInfo = [];
            //$tracking_array = $this->model_account_customer_order->getTrackingNumber($value['id'], $value['order_id']);
            $trackingRepository = app(TrackRepository::class);
            if (
                isset($tracking[$value['order_id']]) &&
                (
                    ($this->tracking_privilege && $value['order_status'] == CustomerSalesOrderStatus::COMPLETED ) ||
                    !$this->tracking_privilege
                )
            ) {
                foreach ($tracking[$value['order_id']] as $track) {
                    $trackNumber = explode(',',$track['trackingNo']);
                    $trackSize = count($trackNumber);
                    $trackStatus = ($track['status'] == 0) ? 0 : 1;
                    for ($i = 0; $i < $trackSize; $i++) {
                        //查询物流最新状态
                        $carrierStatus = $trackingRepository->getTrackingStatusByTrackingNumber($value['order_id'],$trackNumber[$i]);
                        $trackTemp = [
                            'trackingShipSku' => ! empty($track['ShipSku']) ? $track['ShipSku'] : '',
                            'trackingNumber' => $trackNumber[$i],
                            'trackingStatus' => $trackStatus,
                            'carrierStatus' => $carrierStatus
                        ];
                        //英国订单,物流单号为JD开头,显示Carrier是Yodel
                        if (isset($trackTemp[$i]) && $this->customer->getCountryId() == 222 && 'JD' == substr($trackTemp[$i],0,2) && in_array($track['carrierName'],CHANGE_CARRIER_NAME) ) {
                            $trackingInfo[$track['line_id']]['Yodel'][] = $trackTemp;
                        }elseif ($this->customer->getCountryId() == 222  && in_array($track['carrierName'],CHANGE_CARRIER_NAME) ){
                            $trackingInfo[$track['line_id']]['WHISTL'][] = $trackTemp;
                        }else{
                            $trackingInfo[$track['line_id']][$track['carrierName']][] = $trackTemp;
                        }
                    }
                }
            }
            $value['trackingInfo'] = $trackingInfo;

            if(isset($failure_log_html[$value['id']])){
                $value['failure_log'] = $failure_log_html[$value['id']];
            }else{
                $value['failure_log'] = '';
            }
            if(isset($associateOrder[$value['id']])){
                $value['rma_title_notice'] = $this->language->get('title_rma_notice');
                // canceled 订单下 c除了虚拟支付情况下有rma， 还有客服在B2B管理后台取消订单时，选择了保留绑定关系也展示RMA按钮
                $value['rma_status'] = true;
            }else{
                $value['rma_title_notice'] = '';
            }

            $value['checked'] = 0;
            $value['is_show'] = 0;
            $value['disabled'] = 1;
            if($filter_choose_tag){
                if(isset($data['filter_orderStatus']) &&
                    in_array($data['filter_orderStatus'],[CustomerSalesOrderStatus::CANCELED,CustomerSalesOrderStatus::COMPLETED]))
                {
                    // 无改变
                }elseif($value['order_status'] == CustomerSalesOrderStatus::BEING_PROCESSED
                    && $this->country_id != AMERICAN_COUNTRY_ID)
                {
                    // bp 需要校验是否允许
                    $value['is_show'] = 1;
                    $value['tips'] = $this->language->get('error_being_processing');
                }elseif ($value['order_status'] == CustomerSalesOrderStatus::BEING_PROCESSED
                    && $this->country_id == AMERICAN_COUNTRY_ID)
                {
                    // bp 需要看line 明细是否是 is_exported
                    if($order_line_list[$value['id']]['line_list'][0]['is_exported']){
                        $value['is_show'] = 1;
                        $value['tips'] = $this->language->get('error_can_cancel');
                    }else{
                        $available_order_id[] = $value['id'];
                        $value['checked'] = 1;
                        $value['is_show'] = 1;
                        $value['disabled'] = 0;
                    }

                } else{
                    $available_order_id[] = $value['id'];
                    $value['checked'] = 1;
                    $value['is_show'] = 1;
                    $value['disabled'] = 0;
                }
            }
            //获取费用单信息
            $feeOrderRepo = app(FeeOrderRepository::class);
            $feeOrderList = $feeOrderRepo->getCanCancelFeeOrderBySalesOrderId($value['id']);
            $bindOrderNos = [];
            $feeOrderPurchaseRunId = [];//暂存fee order 的purchase run id，用来查询是否有关联的采购单
            foreach ($feeOrderList as $item) {
                if ($item->fee_total > 0 || $item->fee_type != FeeOrderFeeType::STORAGE) {
                    $bindOrderNos[] = "-{$item->order_no}";
                    $feeOrderPurchaseRunId[] = $item->purchase_run_id;
                }
            }
            if (!empty($feeOrderPurchaseRunId)) {
                //有费用单才会提示取消采购单，所以限制获取
                //查询关联的未支付的采购单
                foreach ($feeOrderPurchaseRunId as $purchaseRunId) {
                    $ocOrders = app(OrderRepository::class)->getOrderBySalesOrderId($value['id'], 0, $purchaseRunId, 2);
                    foreach ($ocOrders as $order) {
                        //这里循环理论上只会执行一次，因为一个费用单只会和一个采购单关联
                        $bindOrderNos[] = "-{$order->order_id}";
                    }
                }
            }
            $bindOrderNos = array_filter(array_unique($bindOrderNos));// 过滤重复值
            $value['bind_order_nos'] = implode('<br>', $bindOrderNos);
            $value['bind_order_count'] = count($bindOrderNos);

            // 绑定记录
            $value['cancel_associated_records'] = [];
            if ($value['order_status'] == CustomerSalesOrderStatus::CANCELED && isset($salesOrderIdAssociatedCancelRecordsMap[$value['id']])) {
                $value['cancel_associated_records'] = $salesOrderIdAssociatedCancelRecordsMap[$value['id']];
            }

            // bp 时间
            $value['being_processed_time'] = $beingProcessedTimes[$value['id']] ?? null;
        }
        $data['available_order_id'] = json_encode($available_order_id);
        $data['is_europe'] = false;
        if ($this->country->isEuropeCountry($this->country_id)) {
            $data['is_europe'] = true;
        }
        $data['orders'] = $orders;
        $paramTotal['tracking_privilege'] = $this->tracking_privilege;
        $data['filter_choose_tag'] = $filter_choose_tag;
        // 分页信息
        $data['total'] = $this->sales_model->getSalesOrderTotal($this->customer_id, $paramTotal);
        $data['total_pages'] = ceil($data['total'] / $data['page_limit']);
        // other
        $data['isAutoBuyer'] = boolval(Customer()->getCustomerExt(1));
        $data['country_id'] = $this->country_id;
        $data['order_status'] = $order_status;
        $data['order_status_list'] = $order_status_list;
        $data['now'] = date('Y-m-d H:i:s');
        $ticketCategoryGroupList = $this->model_account_ticket->categoryGroupListKeyStr('buyer');
        $data['ticketCategoryGroupList']  = !$ticketCategoryGroupList ? '{}' : json_encode($ticketCategoryGroupList);
        //日本洲
        $data['is_japan'] = false;
        if ($this->country_id == JAPAN_COUNTRY_ID) {
            $data['is_japan'] = true;
        }
        $data['is_usa'] = customer()->isUSA();
        $data['delivey_status_list'] = TrackStatus::getSpecialViewItems();

        return $this->response->setOutput($this->load->view('account/sales_order/sales_order_list', $data));
    }

    public function getCountryInfo()
    {
        $keyword = $this->request->query->get('keyword');
        $json = $this->sales_model->getCountryList($keyword,$this->country_id);
        return $this->response->json($json);
    }

    /**
     * [notice message]
     */
    public function noticeMessage()
    {
        //获取订单错误提示信息
        $notice_message_arr=$this->sales_model->getAllOrderError($this->customer_id);
        $json=[
            'error'=>0,
            'data'=>$notice_message_arr
        ];
        return $this->response->json($json);
    }

    public function updateAddressChangeTips()
    {
        $order_id = $this->request->post['order_id'];
        $this->sales_model->updateAddressChangeTips($order_id,1);
        $json['error'] = 0;
        return $this->response->json($json);
    }

    public function updateAddressTips()
    {
        $order_id = $this->request->post['order_id'];
        $this->sales_model->updateAddressTips($order_id,1);
        $json['error'] = 0;
        return $this->response->json($json);
    }

    /**
     * [order address]订单地址信息
     */
    public function orderOldAddress()
    {
        $order_id=$this->request->post('order_id');
        $info=$this->sales_model->orderAddress($order_id);
        if ($info) {
            foreach ($info as $key=>$val){
                $info[$key]=trim($info[$key]);
                if($info[$key]=='NULL' || $info[$key]=='null' || $info[$key]==null){
                    $info[$key]='';
                }
            }
            $info['orignName']=$info['ship_name']!=''?$info['ship_name'].'  ':'';
            $info['orignName'].=$info['ship_phone']!=''?$info['ship_phone'].'  ':'';
            $info['orignName'].=$info['email'];
            $info['orignAddr']='';
            $info['orignAddr'].=$info['ship_address1']!=''?$info['ship_address1'].',':'';
            $info['orignAddr'].=$info['ship_city']!=''?$info['ship_city'].',':'';
            $info['orignAddr'].=$info['ship_zip_code']!=''?$info['ship_zip_code'].',':'';
            $info['orignAddr'].=$info['ship_state']!=''?$info['ship_state'].',':'';
            $info['orignAddr'].=$info['ship_country'];

            //如果是JP
            if ($this->country_id == JAPAN_COUNTRY_ID) {
                $info['state_exists'] = $this->sales_model->existsByCountyAndCountry(trim($info['ship_state']), JAPAN_COUNTRY_ID);
                $info['state'] = $this->sales_model->getStateByCountry(JAPAN_COUNTRY_ID);
                $info['is_japan'] = true;
            } else {
                $info['is_japan'] = false;
            }
        }
        $json=[
            'error'=>0,
            'info'=>$info
        ];
        return $this->response->json($json);
    }

    /**
     * [update order address] 修改订单地址
     */
    public function updateOrderAddress()
    {
        $data=$this->request->post;
        //保存数据
        $json=$this->sales_model->updateOrderAddress($data,$this->country_id, $this->customer_id,$this->customer->getGroupId());
        return $this->response->json($json);
    }

    /**
     * [salesOrderDownloadData description] excel 下载数据
     */
    public function salesOrderDownloadData()
    {
        set_time_limit(0);
        ini_set('memory_limit', '500M');
        $isEurope = $this->country->isEuropeCountry($this->country_id);
        $this->load->model('account/customer_order_import');
        $paramTotal = $this->request->request;
        $paramTotal['tracking_privilege'] = $this->tracking_privilege;
        $orders = $this->sales_model->getSalesOrderList($this->customer_id, $paramTotal);
        $content = [];

        $head = [
            'Sales Order ID', 'Item Code', 'Quantity', 'Sub-item Code', 'Sub-item Quantity',
            'Carrier Name', 'Tracking Number', 'Ship Date', 'OrderStatus', 'Grand Total', 'Purchase Order ID', 'Linked'
        ];
        if (customer()->isUSA()) {
            $head = [
                'Sales Order ID', 'Item Code', 'Quantity', 'Sub-item Code', 'Sub-item Quantity',
                'Carrier Name', 'Tracking Number', 'Delivery Status', 'Ship Date', 'OrderStatus', 'Grand Total', 'Purchase Order ID', 'Linked'
            ];
        }
        // 欧洲补运费需要补充这几个字段
        // 获取order下的订单，以及其中的运费订单
        $purchase_order_info =$this->sales_model->getPurchaseOrderInfoByOrderId(array_column($orders,'id'));
        //查看取消状态已删的绑定关系
        $orderAssociatedCancelRecords = $this->sales_model->getOrderAssociatedCancelRecords(array_column($orders,'id'));
        $salesOrderLineIdAssociatedCancelRecordsMap = [];
        foreach ($orderAssociatedCancelRecords as $orderAssociatedCancelRecord) {
            if (array_key_exists($orderAssociatedCancelRecord->sales_order_line_id, $salesOrderLineIdAssociatedCancelRecordsMap)) {
                $salesOrderLineIdAssociatedCancelRecordsMap[$orderAssociatedCancelRecord->sales_order_line_id][] = (array)$orderAssociatedCancelRecord;
            } else {
                $salesOrderLineIdAssociatedCancelRecordsMap[$orderAssociatedCancelRecord->sales_order_line_id] = [
                    (array)$orderAssociatedCancelRecord,
                ];
            }
        }
        if($isEurope){
            array_push($head,'International Order','Additional shipping Order');
        }else{
            array_push($head,'','');
        }
        $results = $this->model_account_customer_order_import->getTrackingNumberInfoByOrderParam(array_column($orders,'id'));
        //物流查询条件
        $filterDeliveryStatus = 0;
        $trackingStatusArr = [];
        if (isset($paramTotal['filter_delivery_status']) && $paramTotal['filter_delivery_status'] != -1) {
            $filterDeliveryStatus = (int)$paramTotal['filter_delivery_status'];
        }
        if (customer()->isUSA()) {
            $trackingStatusArr = app(TrackRepository::class)->getTrackingStatusBySalesOrder(array_unique(array_column($orders, 'order_id')) ?: null);
        }

        foreach ($results as $result) {
            if(isset($this->request->get['filter_tracking_number']) && $this->request->get['filter_tracking_number'] != 0){
                if(($this->tracking_privilege && $result['order_status'] == CustomerSalesOrderStatus::COMPLETED) || !$this->tracking_privilege){
                    $carrier_name = '';
                    if ($result['carrier_name'] != null) {
                        if (count(array_unique($result['carrier_name'])) == 1) {
                            $carrier_name = current($result['carrier_name']);
                        } else {
                            $carrier_name = implode(PHP_EOL, $result['carrier_name']);
                        }
                    }
                    $tracking_number = '';
                    $trackingStatus = '';
                    if (customer()->isUSA()) {
                        foreach ($result['tracking_number'] ?? [] as $key => $value) {
                            if ($filterDeliveryStatus) {
                                $tempTrackingStatus = $trackingStatusArr[$result['order_id'] . '_' . trim($value)] ?? [];
                                if ($tempTrackingStatus && $tempTrackingStatus['carrier_status'] == $filterDeliveryStatus) {
                                    if ($result['tracking_status'][$key] == 0) {
                                        $tracking_number .= $value . ' (invalid) ' . PHP_EOL;
                                    } else {
                                        $tracking_number .= $value . PHP_EOL;
                                    }
                                    $trackingStatus .= TrackStatus::getDescription($filterDeliveryStatus) . PHP_EOL;//通过某个状态查，只能展示此状态的数据
                                }
                            } else {
                                if ($result['tracking_status'][$key] == 0) {
                                    $tracking_number .= $value . ' (invalid) ' . PHP_EOL;
                                } else {
                                    $tracking_number .= $value . PHP_EOL;
                                }
                                if (isset($trackingStatusArr[$result['order_id'] . '_' . trim($value)])) {
                                    $trackingStatus .= ($trackingStatusArr[$result['order_id'] . '_' . trim($value)]['carrier_status_name'] ?? '') . PHP_EOL;
                                } else {
                                    $trackingStatus .= 'N/A' . PHP_EOL;
                                }
                            }
                        }
                    } else {
                        //以前逻辑
                        if ($result['tracking_number'] != null) {
                            foreach ($result['tracking_number'] as $key => $value) {
                                if ($result['tracking_status'][$key] == 0) {
                                    $tracking_number .= $value . ' (invalid) ' . PHP_EOL;
                                } else {
                                    $tracking_number .= $value . PHP_EOL;
                                }
                            }
                        }
                    }
                    $ShipDeliveryDate = '';
                    if ($result['ShipDeliveryDate'] != null) {
                        if (count(array_unique($result['ShipDeliveryDate'])) == 1) {
                            $ShipDeliveryDate = current($result['ShipDeliveryDate']);
                        } else {
                            $ShipDeliveryDate = implode(PHP_EOL, $result['ShipDeliveryDate']);
                        }
                    }
                }else{
                    $carrier_name = '';
                    $tracking_number = '';
                    $trackingStatus = '';
                    $ShipDeliveryDate = '';
                }

            }else{
                $carrier_name = '';
                $tracking_number = '';
                $trackingStatus = '';
                $ShipDeliveryDate = '';
            }

            if(isset($purchase_order_info['all'][$result['id']])){
                $purchase_order = implode(' ',explode(',',$purchase_order_info['all'][$result['id']]['order_id_str']));
                $isBind = 'Yes';
            } elseif ($result['order_status'] == CustomerSalesOrderStatus::CANCELED && isset($salesOrderLineIdAssociatedCancelRecordsMap[$result['id']])) {
                $purchase_order = join(' ', array_column($salesOrderLineIdAssociatedCancelRecordsMap[$result['id']], 'order_id'));
                $isBind = 'No';
            } else {
                $purchase_order = 'N/A';
                $isBind = 'N/A';
            }
            $grandTotal = 'N/A';
            if ($result['order_status'] == CustomerSalesOrderStatus::COMPLETED) {
                $purchase_info = $this->sales_model->getPurchaseOrderInfoByLineId($result['id'], $this->customer_id, $this->country_id);
                if ($purchase_info) {
                    $grandTotal = $purchase_info['final_total_price'];
                }
            }
            if($isEurope){
                //采购定案全部
                if(isset($purchase_order_info['freight'][$result['id']])){
                    $purchase_freight_order = implode(' ',explode(',',$purchase_order_info['freight'][$result['id']]['order_id_str']));
                }else{
                    $purchase_freight_order = null;
                }
                $content[] = [
                    $result['order_id'],
                    $result['sku'],
                    $result['line_qty'],
                    $result['child_sku'],
                    $result['all_qty'],
                    $carrier_name,
                    $tracking_number,
                    $ShipDeliveryDate,
                    $this->order_status[$result['order_status']],
                    $grandTotal,
                    $purchase_order,
                    $isBind,
                    $result['is_international'] == 1 ? 'Yes':'No',
                    $purchase_freight_order,
                    $result['cross_span'], // 注意此条非下载数据，，仅为判断条件
                ];

            } elseif (customer()->isUSA()) {
                $tempContent = [
                    $result['order_id'],
                    $result['sku'],
                    $result['line_qty'],
                    $result['child_sku'],
                    $result['all_qty'],
                    $carrier_name,
                    trim($tracking_number),
                    trim($trackingStatus), //美国,多个物流状态
                    $ShipDeliveryDate,
                    $this->order_status[$result['order_status']],
                    $grandTotal,
                    $purchase_order,
                    $isBind,
                    '',
                    '',
                    $result['cross_span'],// 注意此条非下载数据，，仅为判断条件
                ];
                if ($filterDeliveryStatus) { //如果通过运单状态去查，必然得展示出有运单号的数据
                    if (trim($tracking_number) && trim($trackingStatus) && trim($trackingStatus) != 'N/A') {
                        $content[] = $tempContent;
                    }
                } else {
                    $content[] = $tempContent;
                }
            }else{
                $content[] = [
                    $result['order_id'],
                    $result['sku'],
                    $result['line_qty'],
                    $result['child_sku'],
                    $result['all_qty'],
                    $carrier_name,
                    $tracking_number,
                    $ShipDeliveryDate,
                    $this->order_status[$result['order_status']],
                    $grandTotal,
                    $purchase_order,
                    $isBind,
                    '',
                    '',
                    $result['cross_span'],// 注意此条非下载数据，，仅为判断条件
                ];
            }

        }
        // 输出
        $file_name = "SalesOrderManagement" . date('YmdHis') . ".xls";
        $this->load->model('tool/excel');
        $this->model_tool_excel->setSalesOrderManagementExcel($head,$content,$file_name);

    }

    //平台导单指导页面
    public function downloadTemplateInterpretationFile()
    {
        //1:Shopify  2:Other External Platform 3:Bay
        switch ($this->request->get('method', '')) {
            case '1':
                return $this->render('account/corder_temp_shopify', []);
                break;
            case '2':
                return $this->render('account/corder_temp', [
                    'disable_ship_to_service' => DISABLE_SHIP_TO_SERVICE,
                    'app_version' => APP_VERSION,
                    'country_id' => $this->country_id,
                    'country_us_id' => AMERICAN_COUNTRY_ID,
                    'country_jp_id' => JAPAN_COUNTRY_ID,
                    'is_japan' => customer()->isJapan(),
                ]);
                break;
            case '3':
                return $this->render('account/corder_temp_ebay', [
                    'country_id' => $this->country_id,
                ]);
                break;
            default:
                return $this->response->redirectTo(url()->to(['error/not_found']));
                break;
        }
    }

    public function cityComparison()
    {
        $listGermany = [
            ['Country' => 'Albania', 'Code' => 'AL'],
            ['Country' => 'Andorra', 'Code' => 'AD'],
            ['Country' => 'Austria', 'Code' => 'AT'],
            ['Country' => 'Belgium', 'Code' => 'BE'],
            ['Country' => 'Bosnia & Herzegovina', 'Code' => 'BA'],
            ['Country' => 'Bulgaria', 'Code' => 'BG'],
            ['Country' => 'Croatia', 'Code' => 'HR'],
            ['Country' => 'Cyprus', 'Code' => 'CY'],
            ['Country' => 'Czech Republic', 'Code' => 'CZ'],
            ['Country' => 'Denmark', 'Code' => 'DK'],
            ['Country' => 'Estonia', 'Code' => 'EE'],
            ['Country' => 'Faroe Islands', 'Code' => 'FO'],
            ['Country' => 'Finland', 'Code' => 'FI'],
            ['Country' => 'France', 'Code' => 'FR'],
            ['Country' => 'Gibraltar', 'Code' => 'GI'],
            ['Country' => 'Greece', 'Code' => 'GR'],
            ['Country' => 'Hungary', 'Code' => 'HU'],
            ['Country' => 'Iceland', 'Code' => 'IS'],
            ['Country' => 'Ireland', 'Code' => 'IE'],
            ['Country' => 'Italy', 'Code' => 'IT'],
            ['Country' => 'Kosovo', 'Code' => 'KV'],
            ['Country' => 'Latvia', 'Code' => 'LV'],
            ['Country' => 'Liechtenstein', 'Code' => 'LI'],
            ['Country' => 'Lithuania', 'Code' => 'LT'],
            ['Country' => 'Luxembourg', 'Code' => 'LU'],
            ['Country' => 'Macedonia', 'Code' => 'MK'],
            ['Country' => 'Malta', 'Code' => 'MT'],
            ['Country' => 'Monaco', 'Code' => 'MC'],
            ['Country' => 'Montenegro', 'Code' => 'ME'],
            ['Country' => 'Netherlands', 'Code' => 'NL'],
            ['Country' => 'Norway', 'Code' => 'NO'],
            ['Country' => 'Poland', 'Code' => 'PL'],
            ['Country' => 'Portugal', 'Code' => 'PT'],
            ['Country' => 'Romania', 'Code' => 'RO'],
            ['Country' => 'San Marino', 'Code' => 'SM'],
            ['Country' => 'Serbia', 'Code' => 'RS'],
            ['Country' => 'Slovakia', 'Code' => 'SK'],
            ['Country' => 'Slovenia', 'Code' => 'SI'],
            ['Country' => 'Spain', 'Code' => 'ES'],
            ['Country' => 'Sweden', 'Code' => 'SE'],
            ['Country' => 'Switzerland', 'Code' => 'CH'],
            ['Country' => 'Turkey', 'Code' => 'TR'],
            ['Country' => 'United Kingdom', 'Code' => 'UK'],
            ['Country' => 'Vatican City', 'Code' => 'VA'],
        ];
        $listEngland = [
            ['Country' => 'Austria', 'Code' => 'AT'],
            ['Country' => 'Belgium', 'Code' => 'BE'],
            ['Country' => 'Bosnia', 'Code' => 'BA'],
            ['Country' => 'Bulgaria', 'Code' => 'BG'],
            ['Country' => 'Croatia', 'Code' => 'HR'],
            ['Country' => 'Czech Republi', 'Code' => 'CZ'],
            ['Country' => 'Denmark', 'Code' => 'DK'],
            ['Country' => 'Estonia', 'Code' => 'EE'],
            ['Country' => 'Finland', 'Code' => 'FI'],
            ['Country' => 'France', 'Code' => 'FR'],
            ['Country' => 'Germany', 'Code' => 'DE'],
            ['Country' => 'Greece', 'Code' => 'GR'],
            ['Country' => 'Hungary', 'Code' => 'HU'],
            ['Country' => 'Iceland', 'Code' => 'IS'],
            ['Country' => 'Italy', 'Code' => 'IT'],
            ['Country' => 'Latvia', 'Code' => 'LV'],
            ['Country' => 'Lithuania', 'Code' => 'LT'],
            ['Country' => 'Luxembourg', 'Code' => 'LU'],
            ['Country' => 'Netherlands', 'Code' => 'NL'],
            ['Country' => 'Norway', 'Code' => 'NO'],
            ['Country' => 'Poland', 'Code' => 'PL'],
            ['Country' => 'Portugal', 'Code' => 'PT'],
            ['Country' => 'Romania', 'Code' => 'RO'],
            ['Country' => 'Serbia', 'Code' => 'RS'],
            ['Country' => 'Slovakia', 'Code' => 'SK'],
            ['Country' => 'Slovenia', 'Code' => 'SI'],
            ['Country' => 'Spain', 'Code' => 'ES'],
            ['Country' => 'Sweden', 'Code' => 'SE'],
            ['Country' => 'Switzerland', 'Code' => 'CH'],
            ['Country' => 'Ukraine', 'Code' => 'UA']
        ];

        $data['list'] = [];
        switch ($this->customer->getCountryId()) {
            case 81://德国
                $data['list'] = $listGermany;
                break;
            case 222://英国
                $data['list'] = $listEngland;
                break;
            default:
                break;
        }

        $this->response->setOutput($this->load->view('account/city_comparison', $data));
    }

    public function downloadDPTemplateFile()
    {
        switch ($this->country_id){
            case JAPAN_COUNTRY_ID:
                $file = DIR_DOWNLOAD . 'dp/OrderTemplateJP.xls';
                break;
            case AMERICAN_COUNTRY_ID:
                $file = DIR_DOWNLOAD . 'dp/OrderTemplateUSA.xls';
                break;
            case CountryHelper::getCountryByCode('DEU'):
                $file = DIR_DOWNLOAD . 'dp/OrderTemplateDE.xls';
                break;
            case $this->country_id == CountryHelper::getCountryByCode('GBR'):
                $file = DIR_DOWNLOAD . 'dp/OrderTemplateUK.xls';
                break;
            default:
                $file = '';
        }

        if (!headers_sent()) {
            if (file_exists($file)) {
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . basename($file) . '"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Pragma: public');
                header('Content-Length: ' . filesize($file));

                if (ob_get_level()) {
                    ob_end_clean();
                }
                readfile($file, 'rb');
                exit();
            } else {
                exit('Error: Could not find file ' . $file . '!');
            }
        } else {
            exit('Error: Headers already sent out!');
        }
    }

    /**
     * eBay 导单模板下载
     * @return BinaryFileResponse
     */
    public function downloadEbayTemplate()
    {
        switch ($this->country_id) {
            case UK_COUNTRY_ID:
                $path = DIR_DOWNLOAD . "dp/eBayOrderTemplateUK.xlsx";
                break;
            case DE_COUNTRY_ID:
                $path = DIR_DOWNLOAD . "dp/eBayOrderTemplateDE.xlsx";
                break;
            case AMERICAN_COUNTRY_ID:
                $path = DIR_DOWNLOAD . "dp/eBayOrderTemplateUSA.xlsx";
                break;
            default:
                $path = '';
                break;
        }
        if (!headers_sent()) {
            if (file_exists($path)) {
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . basename($path) . '"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Pragma: public');
                header('Content-Length: ' . filesize($path));

                if (ob_get_level()) {
                    ob_end_clean();
                }
                readfile($path, 'rb');
                exit();
            } else {
                exit('Error: Could not find file ' . $path . '!');
            }
        } else {
            exit('Error: Headers already sent out!');
        }
    }

    /**
     * shopfiy 导单模板下载
     * @return BinaryFileResponse
     */
    public function downloadShopfiyTemplate()
    {
        $path = DIR_DOWNLOAD . "dp/Shopify OrderTemplate.csv";
        return $this->response->download($path);
    }

    /**
     * [upload order upload]
     * @throws Exception
     */
    public function salesOrderUpload()
    {
        $data=[];

        $data['country']=$this->country_id;
        //只有美国国别才有shopfiy的上传
        if($data['country'] == AMERICAN_COUNTRY_ID){
            $data['order_from_history'] = $this->session->get('order_from_history') ?? 2;
        }else{
            $data['order_from_history']=2;
        }
        $data['disable_ship_to_service'] = DISABLE_SHIP_TO_SERVICE;
        //根据国别获取数据
        if ($this->country->isEuropeCountry($this->country_id)) {
            $data['is_europe'] = true;
        }else{
            $data['is_europe'] = false;
        }

        //日本洲
        if ($this->country_id == JAPAN_COUNTRY_ID) {
            $data['state'] = $this->sales_model->getStateByCountry(JAPAN_COUNTRY_ID);
            $data['is_japan'] = true;
        } else {
            $data['is_japan'] = false;
        }
        $data['show_placeholder'] = $data['is_japan'] ? 1:0;
        // 29569回退
//        // 是否美国一件代发账号
//        $data['is_usa_wholesale'] =  ($this->country_id == AMERICAN_COUNTRY_ID && app(BuyerRepository::class)->getTypeById($this->customer_id) == BuyerType::DROP_SHIP) ? 1 : 0;
//        if ($data['is_usa_wholesale']) {
//            // 美国一件代发账号获取对应的州列表
//            $data['state_list'] = CountryState::where('country_id', AMERICAN_COUNTRY_ID)->orderBy('abbr')->get();
//        }

        $this->response->setOutput($this->load->view('account/sales_order/sales_order_upload',$data));
    }

    /**
     * [save single order upload]
     */
    public function singleOrderUpload()
    {
        $data = $this->request->post();
        //校验处理并保存数据
        $json = $this->sales_model->saveSingleOrderUpload($data, $this->country_id, $this->customer_id);
        return $this->response->json($json);
    }

    /**
     * [Files Upload Records]
     * @throws Exception
     */
    public function salesOrderRecord()
    {
        $this->load->model('account/customer_order_import');
        $data = [];
        $data['country_id'] = $this->country_id;
        //当前页
        $page_num = isset($this->request->get['page_num']) ? $this->request->get['page_num'] : 1;
        // 每页显示数目
        $page_limit = isset($this->request->get['page_limit']) ? $this->request->get['page_limit'] : 20;
        //搜索，默认搜索一年
        if(isset($this->request->get['filter_orderDate_from'])){
            $data['filter_orderDate_from'] = $this->request->get['filter_orderDate_from'];
        }else{
            $data['filter_orderDate_from'] = date('Y-m-d H:i:s',strtotime('-1 year'));
        }
        if(isset($this->request->get['filter_orderDate_to'])){
            $data['filter_orderDate_to'] = $this->request->get['filter_orderDate_to'];
        }else{
            $data['filter_orderDate_to'] = date('Y-m-d H:i:s',time());
        }
        //获取数据
        $total = $this->model_account_customer_order_import->getSuccessfullyUploadHistoryTotal($data);
        $result = $this->model_account_customer_order_import->getSuccessfullyUploadHistory($data, $page_num, $page_limit);
        //分页
        $total_pages = ceil(intval($total) / intval($page_limit));
        $data['total_pages'] = $total_pages;
        $data['page_num'] = $page_num;
        $data['total'] = $total;
        $data['page_limit'] = $page_limit;
        //列表数据整理
        $data['results'] = sprintf($this->language->get('text_pagination'), ($total) ? (($page_num - 1) * $page_limit) + 1 : 0, ((($page_num - 1) * $page_limit) > (intval($total) - intval($page_limit))) ? $total : ((($page_num - 1) * $page_limit) + $page_limit), $total, $total_pages);
        $data['historys'] = $result;
        $this->response->setOutput($this->load->view('account/sales_order/sales_order_record', $data));
    }

    /**
     * shopify 导单上传  .csv
     */
    public function uploadShopifyFile()
    {
        $this->session->set('order_from_history', 1);
        //验证文件类型
        $upload_type = $this->request->post('upload_type');
        $verify_ret = $this->sales_model->verifyUploadFile($this->request->files['file'], $upload_type,1);
        if ($verify_ret) {
            $json['error'] = $this->language->get($verify_ret);
            return $this->response->json($json);
        }
        //记录上传文件数据
        $save_info = $this->sales_model->saveUploadFile($this->request->files['file'], $this->customer_id, $upload_type);
        $json['text'] = $this->language->get('text_upload');
        $json['run_id'] = $save_info['run_id'];
        $json['next'] = url()->to(['account/sales_order/sales_order_management/saveShopifyOrder', 'run_id' => $save_info['run_id'], 'import_mode' => $save_info['import_mode'], 'file_id' => $save_info['file_id']]);
        return $this->response->json($json);
    }

    /**
     * [uploadFile description] 批量导入订单 xlsx,xls
     */
    public function uploadFile()
    {
        $this->session->set('order_from_history', 2);
        //验证文件
        $upload_type = $this->request->post['upload_type'];
        $verify_ret = $this->sales_model->verifyUploadFile($this->request->files['file'], $upload_type,2);
        if ($verify_ret) {
            $json['error'] = $this->language->get($verify_ret);
            return $this->response->json($json);
        }
        //记录上传文件数据
        $save_info = $this->sales_model->saveUploadFile($this->request->files['file'], $this->customer_id, $upload_type);
        $json['text'] = $this->language->get('text_upload');
        $json['run_id'] = $save_info['run_id'];
        $json['next'] = url()->to(['account/sales_order/sales_order_management/saveOrder', 'run_id' => $save_info['run_id'], 'import_mode' => $save_info['import_mode'], 'file_id' => $save_info['file_id']]);
        return $this->response->json($json);
    }

    //eBay订单导入
    public function uploadEbayFile()
    {
        $this->session->set('order_from_history', 3);
        $fileInfo = $this->request->file('file');
        //检测文件合法信息
        if ($fileInfo->isValid()) {
            $fileType = $fileInfo->getClientOriginalExtension();
            if (!in_array(strtolower($fileType), ['xls', 'xlsx'])) {
                return $this->response->json(['error' => 'The order file from eBay should be .xls or .xlsx']);
            }
            if ($fileInfo->getError() != UPLOAD_ERR_OK) {
                return $this->response->json(['error' => $this->language->get('error_upload_' . $fileInfo->getError())]);
            }
            //记录上传文件数据
            $save_info = $this->sales_model->saveUploadFile($fileInfo, $this->customer_id, HomePickImportMode::IMPORT_MODE_EBAY);
            return $this->response->json([
                'text' => $this->language->get('text_upload'),
                'run_id' => $save_info['run_id'],
                'next' => url([
                    'account/sales_order/sales_order_management/saveEbayOrder',
                    'run_id' => $save_info['run_id'],
                    'import_mode' => $save_info['import_mode'],
                    'file_id' => $save_info['file_id']
                ]),
            ]);
        }
        return $this->response->json(['error' => $this->language->get('error_upload')]);
    }

    //保存eBay订单保存数据库
    public function saveEbayOrder()
    {
        $this->load->model('tool/excel');
        $get = $this->request->get();
        //获取上传文件的路径
        $file_info = $this->sales_model->getUploadFileInfo($get);
        //使用时需要使用临时文件夹中的地址，使用完成之后删除文件
        $file_path = StorageCloud::orderCsv()->getLocalTempPath($file_info['file_path']);
        //根据上传文件的路径来处理excel的数据
        $upload_data = $this->model_tool_excel->getExcelData($file_path);
        //校验数据
        $ebayRowValidate = new EbayRowValidate($upload_data, $get['run_id']);
        $validateResult = $ebayRowValidate->validate();
        if ($validateResult != '') {
            //更新文件上传状态
            $this->sales_model->updateUploadInfoStatus($get['run_id'], $this->customer_id, ['handle_status' => 0, 'handle_message' => 'upload failed, ' . $validateResult]);
            return $this->response->json(['error' => $validateResult]);
        }
        //写入数据
        $res = app(SalesOrderService::class)->saveEbayOrder($ebayRowValidate->getData(), $ebayRowValidate->getTransactionIds(), $get['run_id'], $this->customer_id);
        if ($res['success'] != 1 || !isset($res) || empty($res) || empty($res['insertList'])) {
            //更新文件上传状态
            $error_msg = 'something wrong happened. Please try it again.';
            $this->sales_model->updateUploadInfoStatus($get['run_id'], $this->customer_id, ['handle_status' => 0, 'handle_message' => 'upload failed, ' . $error_msg]);
            return $this->response->json(['error' => $error_msg]);
        }

        // 更新状态为ltl check
        $this->sales_model->updateSalesOrderLTL($get['run_id'], $this->customer_id);
        $ltl_ret = $this->sales_model->judgeIsAllLtlSku($get['run_id'], $this->customer_id);
        //更新文件上传状态
        $this->sales_model->updateUploadInfoStatus($get['run_id'], $this->customer_id, ['handle_status' => 1, 'handle_message' => 'uploaded successfully.']);
        StorageCloud::orderCsv()->deleteLocalTempFile($file_path);
        return $this->response->json([
            'text' => $ltl_ret['is_all_ltl'] == 1 ? 'LTL Confirm' : 'Orders Processing',
            'is_all_ltl' => $ltl_ret['is_all_ltl'],
            'order_id_list' => $res['insertList'],//导入成功的销售单id数组
            'next' => url([
                'account/sales_order/sales_order_management/orderPurchase',
                'run_id' => $get['run_id'],
                'import_mode' => HomePickImportMode::IMPORT_MODE_EBAY,
            ]),
        ]);
    }

    /**
     * 保存 shopify订单保存数据库
     */
    public function saveShopifyOrder()
    {
        $get = $this->request->get();
        //获取上传文件的路径
        $file_info = $this->sales_model->getUploadFileInfo($get);
        //使用时需要使用临时文件夹中的地址，使用完成之后删除文件
        $file_path = StorageCloud::orderCsv()->getLocalTempPath($file_info['file_path']);
        //根据上传文件的路径来处理excel的数据
        $this->load->model('tool/csv');
        $csv_data = $this->model_tool_csv->readCsvLines($file_path);
        try {
            // 订单的核心内容处理上传文件中的内容
            $ret = $this->sales_model->dealWithFileShopifyData($csv_data, $get, $this->country_id, $this->customer_id);
        } catch (Exception $e) {
            $ret = $this->language->get('error_happened');
            logger::salesOrder($e, 'error');
        }
        // 更新文件上传的状态
        if (!is_array($ret)) {
            $this->sales_model->updateUploadInfoStatus($get['run_id'], $this->customer_id, ['handle_status' => 0, 'handle_message' => 'upload failed, ' . $ret]);
            return $this->response->json(['error' => $ret]);
        }
        // 更新状态为ltl check
        $this->sales_model->updateSalesOrderLTL($get['run_id'], $this->customer_id);
        $ltl_ret = $this->sales_model->judgeIsAllLtlSku($get['run_id'], $this->customer_id);
        $this->sales_model->updateUploadInfoStatus($get['run_id'], $this->customer_id, ['handle_status' => 1, 'handle_message' => 'uploaded successfully.']);
        StorageCloud::orderCsv()->deleteLocalTempFile($file_path);
        return $this->response->json([
            'text' => $ltl_ret['is_all_ltl'] == 1 ? 'LTL Confirm' : 'Orders Processing',
            'is_all_ltl' => $ltl_ret['is_all_ltl'],
            'order_id_list' => $ret,//导入成功的销售单id数组
            'next' => url([
                'account/sales_order/sales_order_management/orderPurchase',
                'run_id' => $get['run_id'],
                'import_mode' => $get['import_mode'],
            ]),
        ]);
    }

    /**
     * [saveOrder description] save order 将整体导入订单的数据进行处理插入数据表中
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    public function saveOrder()
    {
        trim_strings($this->request->get);
        $this->load->model('tool/excel');
        $get = $this->request->get;
        //获取上传文件的路径
        $file_info = $this->sales_model->getUploadFileInfo($get);
        //使用时需要使用临时文件夹中的地址，使用完成之后删除文件
        $file_path = StorageCloud::orderCsv()->getLocalTempPath($file_info['file_path']);
        //根据上传文件的路径来处理excel的数据
        $excel_data = $this->model_tool_excel->getExcelData($file_path);
        try {
            // 订单的核心内容处理上传文件中的内容
            $ret = $this->sales_model->dealWithFileData($excel_data, $get, $this->country_id, $this->customer_id);

        } catch (Exception $e) {
            $ret = $this->language->get('error_happened');
            logger::salesOrder($e,'error');
        }
        // 更新文件上传的状态
        if (!is_array($ret)) {
            $update_info = [
                'handle_status' => 0,
                'handle_message' => 'upload failed, ' . $ret,
            ];
            $this->sales_model->updateUploadInfoStatus($get['run_id'], $this->customer_id, $update_info);
            $json['error'] = $ret;
            return  $this->response->json($json);
        }
         // 更新状态为ltl check
        $this->sales_model->updateSalesOrderLTL($get['run_id'], $this->customer_id);
        $ltl_ret = $this->sales_model->judgeIsAllLtlSku($get['run_id'], $this->customer_id);
        $update_info = [
            'handle_status' => 1,
            'handle_message' => 'uploaded successfully.',
        ];
        $json['is_all_ltl'] = $ltl_ret['is_all_ltl'];
        $json['order_id_list'] = $ret;
        $json['next'] = url()->to(['account/sales_order/sales_order_management/orderPurchase', 'run_id' => $get['run_id'], 'import_mode' => $get['import_mode']]);
        $this->sales_model->updateUploadInfoStatus($get['run_id'], $this->customer_id, $update_info);
        $json['text'] = $ltl_ret['is_all_ltl'] == 1 ? 'LTL Confirm':'Orders Processing';
        StorageCloud::orderCsv()->deleteLocalTempFile($file_path);
        return $this->response->json($json);
    }

    /**
     * [customerOrderSalesOrderDetails description] 导单优化中不更改的部分view
     * @throws ReflectionException
     * @throws Exception
     */
    public function customerOrderSalesOrderDetails()
    {
        $this->load->language('account/customer_order_import');
        $this->load->language('account/customer_order');
        $this->load->language('common/cwf');
        $this->load->model("account/customer_order_import");
        $this->load->model("account/customer_order");
        $country_id = $this->country_id;
        $this->document->setTitle($this->language->get('text_heading_title_details'));
        // 头部面包屑信息
        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => url()->to(['common/home'])
            ],
            [
                'text' => $this->language->get('text_customer_order'),
                'href' => url()->to(['account/sales_order/sales_order_management'])
            ],
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_heading_title_details'),
            'href' => 'javascription:void(0)'
        ];
        // 详情页信息 & order_status_label
        $order_header_id = $this->request->get('id');
        $res = $this->model_account_customer_order_import->getCustomerOrderAllInformation($order_header_id);
        $order_status_label = $this->model_account_customer_order->getSalesOrderStatusLabel($order_header_id);

        //不能越权查看别人的订单信息
        if ($res['base_info']['buyer_id'] != $this->customer->getId()) {
            $this->redirect(['account/customer_order'])->send();
        }

        //是否为欧洲
        $isEurope = false;
        if ($this->country->isEuropeCountry($country_id)) {
            $isEurope = true;
        }
        $data['service_type'] = SERVICE_TYPE;
        $data['isCollectionFromDomicile'] = $this->customer->isCollectionFromDomicile();
        $data['isEurope'] = $isEurope;
        $data['base_info'] = $res['base_info'];
        $data['safeguard_bills'] = app(SafeguardBillRepository::class)->getSafeguardBillsBySaleId($order_header_id);
        $data['now'] = date('Y-m-d H:i:s');
        $data['item_list'] = $res['item_list'];
        $data['shipping_information'] = $res['shipping_information'];
        $data['signature_list'] = $res['signature_list'];
        $data['sub_total'] = $res['sub_total'];
        $data['fee_total'] = $res['fee_total'];
        $data['all_total'] = $res['all_total'];
        $data['item_total_price'] = $res['item_total_price'];
        $data['item_final_total_price'] = $res['item_final_total_price'];
        $data['item_discount_amount'] = $res['item_discount_amount'];
        $data['shipping_address'] = implode(',', array_filter([$res['base_info']['ship_address1'], $res['base_info']['ship_city'], $res['base_info']['ship_state'], $res['base_info']['ship_zip_code'], $res['base_info']['ship_country']]));
        $data['country_id'] = $country_id;
        $data['trackingSearchShow'] = ($this->country_id == AMERICAN_COUNTRY_ID && !$this->isCollectionFromDomicile);
        // 路由跳转
        $data['href_go_back'] = url()->to(['account/sales_order/sales_order_management']);
        $data['order_status_label'] = $order_status_label;
        return $this->render('account/customer_order_sales_orderDetails', $data,[
            'column_left' => 'common/column_left',
            'column_right' => 'common/column_right',
            'content_top' => 'common/content_top',
            'content_bottom' => 'common/content_bottom',
            'footer' => 'common/footer',
            'header' => 'common/header',
        ]);
    }

    public function checkIsExistAssociate()
    {
        $order_id = $this->request->input->get('order_id');
        $json = $this->sales_model->checkIsExistAssociate($order_id);
        return $this->response->json($json);
    }


    /**
     * 一件代发销售单取消订单
     */
    public function cancelOrder()
    {
        $orderId = Request('id');
        $json = $this->sales_model->cancelSalesOrder($orderId);
        return $this->response->json($json);
    }

    public function batchCancelOrder()
    {
        $orderIds = Request('id');
        try {
            $this->orm->getConnection()->beginTransaction();
            $json = $this->sales_model->batchCancelSalesOrder($orderIds);
            $this->orm->getConnection()->commit();
        }catch (Exception $e) {
            Logger::salesOrder('cancel 订单错误.','error');
            Logger::salesOrder($e,'error');
            $this->orm->getConnection()->rollBack();
            $json = $this->language->get('error_can_cancel');
        }
        $this->response->json($json);

    }

    /**
     * [releaseOrder description]
     */
    public function releaseOrder()
    {
        trim_strings($this->request->post);
        $post = $this->request->post;
        $order_info = $this->sales_model->getReleaseOrderInfo($post['id']);
        $this->sales_model->releaseOrder($post['id'], $order_info['order_status'], $order_info['type'],$this->customer_id);
        $order_info_ret = $this->sales_model->getReleaseOrderInfo($post['id']);
        if ($order_info_ret['order_status'] == CustomerSalesOrderStatus::TO_BE_PAID) {
            $json['msg'] = $this->language->get('text_success_release');
        } elseif ($order_info_ret['order_status'] == CustomerSalesOrderStatus::COMPLETED) {
            $json['msg'] = $this->language->get('text_success_release');
        } else {
            $json['msg'] = $this->language->get('text_error_release');
        }
        return $this->response->json($json);
    }

    /**
     * [batchReleaseOrder description] onhold 情况下批量release 订单
     */
    public function batchReleaseOrder()
    {
        trim_strings($this->request->post);
        $post = $this->request->post;
        $post['id'] = $post['id'] ?? 0;
        try {
            $this->orm->getConnection()->beginTransaction();
            $order_info = $this->sales_model->getBatchReleaseOrderInfo($post['id']);
            $this->sales_model->batchReleaseOrder($order_info,$this->customer_id);
            $order_info_ret = $this->sales_model->getBatchReleaseOrderInfo($post['id']);
            $order_status_num = array_sum(array_unique(array_column($order_info_ret['order_status'],'order_status')));
            $this->orm->getConnection()->commit();
        } catch (Exception $e) {
            $this->log->write('release 订单错误.');
            $this->log->write($post);
            $this->log->write($e);
            $this->orm->getConnection()->rollBack();
            $order_status_num = 0;
        }
        if ($order_status_num = 1 || $order_status_num = 64 || $order_status_num == 65) {
            $json['msg'] = $this->language->get('text_success_release');
        } else {
            $json['msg'] = $this->language->get('text_error_release');
        }
        return $this->response->json($json);
    }

    public function getB2bCodeBySearch()
    {
        $q = get_value_or_default($this->request->get, 'q', '');
        $page = get_value_or_default($this->request->get, 'page', 1);
        $line_id = get_value_or_default($this->request->get, 'line_id', '');
        $results = $this->sales_model->getSearchSku($q,$page,$line_id,$this->customer_id,$this->country_id);
        return $this->response->json($results);
    }

    public function salesOrderSkuChange()
    {
        trim_strings($this->request->post);
        $json = [];
        $posts = $this->request->post;
        // 只有ltl 和new order的订单才能够更改修改sku
        // order_id line_id product_id
        list($ret, $msg) = $this->sales_model->updateSalesOrderLineSku($posts['order_id'], $posts['line_id'], $posts['product_id']);
        if (!$ret) {
            $json['success'] = 0;
            $json['msg'] = $msg ?: $this->language->get('text_modify_sku_failed');
        } else {
            $json['success'] = 1;
            $json['msg'] =  $msg ?: $this->language->get('text_modify_sku_success');
        }
        $this->response->returnJson($json);
    }

    public function salesOrderCheckLtl()
    {
        //获取checkLtl数据
        trim_strings($this->request->get);
        $get = $this->request->get;
        $list = $this->sales_model->getLtlCheckInfoByOrderId($get['id']);
        $data['list'] = $list;
        $data['id'] = $get['id'];
        $data['app_version'] = APP_VERSION;
        $this->response->setOutput($this->load->view('account/sales_order/sales_order_check_ltl', $data));
    }

    public function salesOrderLtlUpdate()
    {
        trim_strings($this->request->post);
        $post = $this->request->post;
        try {
            $this->orm->getConnection()->beginTransaction();
            $json = $this->sales_model->changeLtlStatus($post['id']);
            $this->orm->getConnection()->commit();
        } catch (Exception $e) {
            $this->log->write('ltl update 订单错误.');
            $this->log->write($post);
            $this->log->write($e);
            $this->orm->getConnection()->rollBack();
            $json['msg'] = $this->language->get('error_can_ltl');
        }
        return $this->response->json($json);
    }

    /*******************************下单页**************************************/
    public function salesOrderPurchaseOrderManagement()
    {
        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => url()->to(['common/home'])
        ];
        $this->load->model('account/customer_order');
        $runId = $this->request->get('run_id', 0);
        $orderId = $this->request->get('order_id', 0);//采购单ID，用于采购单过来二次支付使用
        $source = $this->request->get('source');

        if ($runId == 0) {
            return $this->response->redirectTo(url()->to(['account/account']));
        }

        if ($source == 'fee_order') {
            //费用单过来的
            $this->document->setTitle($this->language->get('heading_title_charges'));
            $data['breadcrumbs'][] = [
                'text' => $this->language->get('heading_title_charges'),
                'href' => url()->to(['account/order', '#' => 'tab_fee_order'])
            ];
        } elseif ($orderId > 0) {
            //大于0说明是从采购单过来的
            $this->document->setTitle($this->language->get('heading_title_charges'));
            $data['breadcrumbs'][] = [
                'text' => $this->language->get('heading_title_charges'),
                'href' => url()->to(['account/order'])
            ];
        } else {
            //销售订单过来的
            $this->document->setTitle($this->language->get('heading_title_drop_shipping'));
            $data['breadcrumbs'][] = [
                'text' => $this->language->get('heading_title_drop_shipping'),
                'href' => url()->to(['account/sales_order/sales_order_management'])
            ];
        }
        //region 构建页面主要数据
        $this->load->model('account/sales_order/match_inventory_window');
        $this->load->model('tool/image');

        $matchModel = $this->model_account_sales_order_match_inventory_window;
        //获取下单页数据
        $purchaseRecords = $matchModel->getPurchaseRecord($runId, $this->customer_id, false);
        //没有订单信息
        if (empty($purchaseRecords)) {
            return $this->response->redirectTo(url()->to(['account/account']));
        }
        $salesHeaderId = array_column($purchaseRecords,'order_id');

        //销售订单地址信息
        $shipInfos = $matchModel->getSalesOrderInfo(array_unique($salesHeaderId));
        $shipInfoArr = array_column($shipInfos, null, 'id');
        //记录各明细的价格信息
        $countryId = $this->customer->getCountryId();
        //region 获取仓租费分配
        $orderAssociatedIds = [];//暂存请求仓租用的参数
        $getProductDataInfo = [];//暂存获取商品信息的参数
        $purchasePayProductList = [];
        foreach ($purchaseRecords as &$purchaseRecord) {
            //只查询已有库存的
            if ($purchaseRecord['sales_order_quantity'] > $purchaseRecord['quantity']) {
                $associatedPreList = $matchModel->getSalesOrderAssociatedPre($purchaseRecord['order_id'], $purchaseRecord['line_id'], $runId, 1);
                $purchaseRecord['purchase_order_product_cost_total'] = 0;
                $purchaseRecord['purchase_order_product_freight_total'] = 0;
                $purchaseRecord['purchase_order_product_service_total'] = 0;
                foreach ($associatedPreList as $associatedPreItem) {
                    $orderAssociatedIds[] = $associatedPreItem->id;
                    $purchaseRecord['order_product_id'][] = $associatedPreItem->order_product_id;
                    $orderProduct = app(OrderRepository::class)->getOrderProductPrice($associatedPreItem->order_product_id);
                    $purchaseRecord['purchase_order_product_cost_total'] += $orderProduct->price * $associatedPreItem->qty;
                    $purchaseRecord['purchase_order_product_freight_total'] += ($orderProduct->freight_per + $orderProduct->package_fee) * $associatedPreItem->qty;
                    $purchaseRecord['purchase_order_product_service_total'] += $orderProduct->service_fee_per  * $associatedPreItem->qty;
                }
            }
            if ($purchaseRecord['quantity'] > 0) {
                if ($purchaseRecord['type_id'] == ProductTransactionType::MARGIN) {
                    $advanceOrderProduct = app(MarginRepository::class)->getAdvanceOrderProductByAgreementId($purchaseRecord['agreement_id']);
                    if ($advanceOrderProduct) {
                        $purchaseRecord['order_product_id'][] = $advanceOrderProduct->order_product_id;
                    }
                }
            }
            $getProductDataInfo[$purchaseRecord['item_code']] = $purchaseRecord['seller_id'];
            if ($purchaseRecord['quantity'] > 0) {
                // 需要采购的数量大于0，构建合并请求价格信息的数组
                $__productKey = "{$purchaseRecord['product_id']}_{$purchaseRecord['type_id']}_{$purchaseRecord['agreement_id']}";
                if (!isset($purchasePayProductList[$__productKey])) {
                    $purchasePayProductList[$__productKey] = [
                        'type_id' => $purchaseRecord['type_id'],
                        'agreement_id' => $purchaseRecord['agreement_id'],
                        'product_id' => $purchaseRecord['product_id'],
                        'seller_id' => $purchaseRecord['seller_id'],
                        'customer_id' => $this->customer_id,
                        'country_id' => $countryId,
                        'quantity' => 0,
                    ];
                }
                $purchasePayProductList[$__productKey]['quantity'] += $purchaseRecord['quantity'];
            }
            unset($__productKey);
        }
        unset($purchaseRecord);
        //endregion
        //region 获取需要采购的产品价格信息
        foreach ($purchasePayProductList as &$purchasePayProductItem) {
            $lineTotal = $matchModel->getLineTotal($purchasePayProductItem);
            if ($purchasePayProductItem['type_id'] == ProductTransactionType::SPOT) {
                $lineTotal['quote_amount'] = $lineTotal['product_price_per'];
            }
            $purchasePayProductItem['line_total'] = $lineTotal;
        }
        unset($purchasePayProductItem);
        //endregion
        //创建返回数据存放数组
        $feeOrderList = app(FeeOrderRepository::class)->getCanPayFeeOrderByRunId($runId, $this->customer_id, FeeOrderFeeType::STORAGE);
        if (!empty($feeOrderList)) {
            //如果有未支付的费用单，优先用费用单的信息
            $feeOrderList = app(StorageFeeRepository::class)->getDetailsByFeeOrder(array_values($feeOrderList), true);
            foreach ($feeOrderList as $feeOrderDetails) {
                foreach ($feeOrderDetails as $feeOrderDetail) {
                    $orderStorageFeeInfos[$feeOrderDetail['fee_order_id']][$feeOrderDetail['order_product_id']] = $feeOrderDetail;
                }
            }
        } else {
            $orderStorageFeeInfos = app(StorageFeeRepository::class)->getAllCanBind($orderAssociatedIds, $purchaseRecords,true);
        }
        $isFeeOrder = true;//是否只是费用单，纯费用单，不需要创建采购订单
        $purchaseRecordsShow = [];
        //获取商品数据（图片 tags）
        $productInfoList = app(ProductRepository::class)->getProductInfoBySellerItemCode($getProductDataInfo);
        foreach ($purchaseRecords as $purchaseRecord) {
            $headerId = $purchaseRecord['order_id'];
            if(!isset($purchaseRecordsShow[$headerId])) {
                $purchaseRecordsShow[$headerId] = $shipInfoArr[$headerId];
                //规则：Name（Phone）Address Detail+City+State+Country
                $purchaseRecordsShow[$headerId]['ship_info'] = "{$shipInfoArr[$headerId]['ship_name']}({$shipInfoArr[$headerId]['ship_phone']}), {$shipInfoArr[$headerId]['ship_address1']}{$shipInfoArr[$headerId]['ship_address2']}, ";
                $purchaseRecordsShow[$headerId]['ship_info'] .= "{$shipInfoArr[$headerId]['ship_city']}, {$shipInfoArr[$headerId]['ship_state']}, {$shipInfoArr[$headerId]['ship_zip_code']}, {$shipInfoArr[$headerId]['ship_country']}";
            }
            $lineData = [
                'type_id' => $purchaseRecord['type_id'],
                'agreement_id' => $purchaseRecord['agreement_id'],
                'product_id' => $purchaseRecord['product_id'],
                'sales_order_quantity' => $purchaseRecord['sales_order_quantity'],
                'quantity' => $purchaseRecord['quantity'],
                'quantity_available' => $purchaseRecord['sales_order_quantity'] - $purchaseRecord['quantity'],
                'customer_id' => $this->customer_id,
                'country_id' => $countryId,
                'screenname' => $purchaseRecord['screenname'],
                'purchase_order_product_cost_total' => $purchaseRecord['purchase_order_product_cost_total'] ?? 0,
                'purchase_order_product_service_total' => $purchaseRecord['purchase_order_product_service_total'] ?? 0,
                'purchase_order_product_freight_total' => $purchaseRecord['purchase_order_product_freight_total'] ?? 0,
                'item_code' => $purchaseRecord['item_code'],
                'seller_id' => $purchaseRecord['seller_id'],
                'storage_fee_total' => 0,
                'is_storage_fee' => false, //标记是否有仓租
            ];
            $lineInfo = [];
            if ($purchaseRecord['quantity'] > 0) {
                // 获取之前组装好的价格信息
                $__productKey = "{$purchaseRecord['product_id']}_{$purchaseRecord['type_id']}_{$purchaseRecord['agreement_id']}";
                if (empty($purchasePayProductList[$__productKey]['line_total'])) {
                    throw new NotFoundException('Product Exception!');
                }
                $lineInfo = $purchasePayProductList[$__productKey]['line_total'];
            }
            //仓租信息
            if (isset($orderStorageFeeInfos[$headerId])) {
                foreach ($orderStorageFeeInfos[$headerId] as $orderProductId => $orderStorageFeeInfo) {
                    if (isset($purchaseRecord['order_product_id']) && in_array($orderProductId, $purchaseRecord['order_product_id'])) {
                        if (!$orderStorageFeeInfo) {
                            continue;
                        }
                        $lineData['storage_fee_infos'][] = $orderStorageFeeInfo;
                        if ($orderStorageFeeInfo['need_pay'] > 0) {
                            $lineData['is_storage_fee'] = true;
                        }
                    }
                }
            }
            if (!$purchaseRecord['product_id'] && !empty($lineData['storage_fee_infos'])) {
                //无需购买且有仓租信息
                $lineData['product_id'] = $lineData['storage_fee_infos'][0]['product_id'];
                $productInfo = app(ProductRepository::class)->getProductInfoByProductId($lineData['product_id']);
            } else {
                $productInfo = $productInfoList[$purchaseRecord['item_code'] . '-' . $purchaseRecord['seller_id']] ?? [];
            }
            //图片和tags赋值
            $lineData['image'] = $this->model_tool_image->resize($productInfo['image'] ?? null, 40, 40);
            $lineData['tags'] = $productInfo['tags'] ?? [];
            $lineData['product_type'] = $productInfo['product_type'] ?? ProductType::NORMAL;
            //记录中包含几种情况
            if ($purchaseRecord['quantity'] <= 0) {
                //1：无需购买
                $lineData['is_buy'] = false;
                $purchaseRecordsShow[$headerId]['lines'][] = $lineData;
            } elseif ($purchaseRecord['sales_order_quantity'] == $purchaseRecord['quantity']) {
                //2：全部需要购买
                $lineData['is_buy'] = true;
                $isFeeOrder = false;
                $purchaseRecordsShow[$headerId]['lines'][] = array_merge($lineData, $lineInfo);
            } else {
                //3：一部分需要购买，即quantity > 0 and sales_order_quantity > 即quantity,这种记录需要拆分成两组数据
                $lineData['is_buy'] = true;
                $isFeeOrder = false;
                $purchaseRecordsShow[$headerId]['lines'][] = array_merge($lineData, $lineInfo);
            }
        }
        // 获取保障服务费用单
        $secondPayment = false; // 是否二次支付
        $safeguardFeeOrderList = app(FeeOrderRepository::class)->getFeeOrderByRunId($runId, $this->customer_id, FeeOrderFeeType::SAFEGUARD);
        if($safeguardFeeOrderList->isNotEmpty()){
            $secondPayment = true;
            $safeguardFeeOrderList->load('safeguardDetails');
            foreach ($safeguardFeeOrderList as $safeguardFeeOrder) {
                foreach ($safeguardFeeOrder->safeguardDetails as $safeguardDetail) {
                    $safeguardFeeOrderConfigList[$safeguardFeeOrder->order_id][] = $safeguardDetail->safeguard_config_id;
                }
            }
        }


        //数据明细汇总
        //合计展示的数据
        $itemPriceTotal = 0;//总商品金额
        $subTotal = 0;//总费用
        $totalFreight = 0;//总运费
        $totalServiceFee = 0;//总服务费
        $totalStorageFee = 0;//总仓租费
        $purchaseProducts = [];//需要采购的产品列表
        foreach ($purchaseRecordsShow as $key => &$purchaseRecordShow) {
            if (empty($purchaseRecordShow['lines'])) {
                unset($purchaseRecordsShow[$key]);
                continue;
            }
            $itemCostTotalLine = 0;//该订单的总费用
            $freightTotalLine = 0;//该订单的总运费
            $priceTotalLine = 0;//该订单的总货款
            $depositTotalLine = 0;//该订单的头款
            $storageFeeTotalLine = 0;//该订单的总仓租
            $serviceTotalLine = 0;//该订单的总服务费
            $purchaseItemCostTotalLine = 0;//该订单的囤货的总货值
            $purchaseFreightTotalLine = 0;//该订单的囤货的总物流费
            $purchaseServiceTotalLine = 0;//该订单的囤货的总服务费
            $allIsStorageFee = false;//所有sku里是否有需要仓租费的
            $isBuy = false;//用来订单内是否有需要购买的sku
            foreach ($purchaseRecordShow['lines'] as $key=>&$lineData){
                $lineData['line_item_cost'] = '';//该sku的总货款
                $lineData['line_freight'] = '';//该sku的总运费
                $lineData['line_product_price'] = '';//该sku的商品金额
                $lineData['line_service_fee'] = '';//该sku的总服务费
                $lineData['line_storage_fee'] = '';//该sku的总仓租费
                $lineData['line_total_cost_freight'] = '';//该sku的总货款+总运费
                $lineData['line_total_fee'] = '';//该sku的总费用
                if ($lineData['is_storage_fee']) {
                    //只要有一个需要支付仓租费的都标记为需要，主要是为了twig显示
                    $allIsStorageFee = true;
                }
                //需要支付仓租
                $needPayStorageFee = BCS::create(0, ['scale' => 4]);
                $needPayStorageFeeInStock = BCS::create(0, ['scale' => 4]);// 使用囤货库存的仓租
                $needPayStorageFeeNotStock = BCS::create(0, ['scale' => 4]);// 不使用囤货库存的仓租
                //判断是否有仓租
                if ($lineData['is_storage_fee']) {
                    foreach ($lineData['storage_fee_infos'] as $storageFeeInfo) {
                        $needPayStorageFee->add($storageFeeInfo['need_pay']);
                        if ($storageFeeInfo['is_in_stock']) {
                            $needPayStorageFeeInStock->add($storageFeeInfo['need_pay']);
                        } else {
                            $needPayStorageFeeNotStock->add($storageFeeInfo['need_pay']);
                        }
                    }
                }
                $storageFeeTotalLine += $needPayStorageFee = $needPayStorageFee->getResult();
                $lineData['line_storage_fee'] = $needPayStorageFee;
                $lineData['line_need_pay_storage_fee_in_stock'] = $needPayStorageFeeInStock->getResult();
                $lineData['line_need_pay_storage_fee_not_stock'] = $needPayStorageFeeNotStock->getResult();
                $lineTotalCostFreightFee = 0;
                //只有需要采购数量大于0的才记录总费用
                if ($lineData['quantity'] > 0) {
                    $purchaseProducts[] = $lineData;
                    $priceTotalLine += $lineData['line_product_price'] =  $lineData['product_price_per'] * $lineData['quantity'];//
                    $serviceTotalLine += $lineData['line_service_fee'] = $lineData['service_fee_per'] * $lineData['quantity'];
                    $lineData['line_freight_cost'] = $lineData['freight'] + $lineData['package_fee'];//单件运费
                    $freightTotalLine += $lineData['line_freight'] = $lineData['line_freight_cost'] * $lineData['quantity'];
                    $itemCostTotalLine += $lineData['line_item_cost'] = $lineData['price'] * $lineData['quantity'];
                    $lineTotalCostFreightFee = $lineData['line_item_cost'] + $lineData['line_freight'];
                    $depositTotalLine += ($lineData['deposit_per'] * $lineData['quantity']);
                }
                $purchaseItemCostTotalLine  += $lineData['purchase_order_product_cost_total'];
                $purchaseFreightTotalLine  += $lineData['purchase_order_product_freight_total'];
                $purchaseServiceTotalLine  += $lineData['purchase_order_product_service_total'];
                if ($lineData['product_type'] == ProductType::COMPENSATION_FREIGHT) {
                    $freightProductCount++;
                }
                $lineData['line_total_cost_freight'] = $lineTotalCostFreightFee;
                $lineTotalFee = $lineTotalCostFreightFee + $needPayStorageFee;
                $lineData['line_total_fee'] = $lineTotalFee;

                if($lineData['is_buy']){
                    $isBuy = true;
                }
            }
            $purchaseRecordShow['item_cost_total'] = $itemCostTotalLine;
            $purchaseRecordShow['freight_total'] = $freightTotalLine;
            $purchaseRecordShow['storage_fee_total'] = $storageFeeTotalLine;
            $totalAmount = $itemCostTotalLine + $freightTotalLine + $storageFeeTotalLine;//总费用+总运费
            $costAndFreightAmount = $itemCostTotalLine + $freightTotalLine + $depositTotalLine + $purchaseItemCostTotalLine + $purchaseFreightTotalLine + $purchaseServiceTotalLine;//货值+物流费
            $subTotal += $totalAmount;
            $itemPriceTotal += $priceTotalLine;
            $totalFreight += $freightTotalLine;
            $totalServiceFee += $serviceTotalLine;
            $totalStorageFee += $storageFeeTotalLine;
            $purchaseRecordShow['total_amount'] = $totalAmount;
            $purchaseRecordShow['costAndFreightAmount'] = $costAndFreightAmount;
            $purchaseRecordShow['all_is_storage_fee'] = $allIsStorageFee;
            $purchaseRecordShow['sale_order_qty'] = CustomerSalesOrderLine::query()->where('header_id',$purchaseRecordShow['id'])->sum('qty');
            $purchaseRecordShow['sale_order_qty_max'] = CustomerSalesOrderLine::query()->where('header_id',$purchaseRecordShow['id'])->max('qty');
            $purchaseRecordShow['is_buy'] = $isBuy;
            $purchaseRecordShow['safeguard_config_ids'] = $safeguardFeeOrderConfigList[$purchaseRecordShow['id']] ?? [];
        }
//        if (empty($purchaseRecordsShow)) {
//            //如果没有需要支付的
//            return $this->response->redirectTo(url()->to(['account/account']));
//        }
        //region 合计数据
        $data['itemPriceTotal'] = $itemPriceTotal;
        $data['totalFreight'] = $totalFreight;
        $data['totalServiceFee'] = $totalServiceFee;
        $data['totalStorageFee'] = $totalStorageFee;
        $data['purchaseRecords'] = $purchaseRecordsShow;
        //endregion
        //endregion
        //region 页面附加参数
        //是否是欧洲用户
        $data['isEurope'] = $this->country->isEuropeCountry($this->customer->getCountryId());
        $data['run_id'] = $runId;
        $data['is_fee_order'] = $isFeeOrder;
        $data['currency'] = $this->session->get('currency');
        $data['currency_symbol'] = $this->currency->getSymbolLeft($data['currency']) . $this->currency->getSymbolRight($data['currency']);
        $data['addCartUrl'] = url()->to(['checkout/cart/addCartByRunId']);
        $data['checkCartUrl'] = url()->to(['checkout/cart/checkDropShipCart', 'run_id' => $runId]);
        $data['createOrderUrl'] = url()->to(['checkout/confirm/createOrderForPurchaseList', 'run_id' => $runId]);
        $data['createStorageFeeOrderUrl'] = url()->to(['account/fee_order/fee_order/createStorageFeeOrder']);
        $data['judgePurchaseSalesOrderCanPayUrl'] = url()->to(['account/sales_order/sales_order_management/judgePurchaseSalesOrderCanPay']);
        $data['toPayUrl'] = url()->to(['checkout/confirm/toPay']);
        $data['order_id'] = $orderId;
        $data['header_tips_is_show'] = $orderId  > 0;
        $data['storage_fee_description_id'] = app(StorageFeeRepository::class)->getStorageFeeDescriptionId($countryId);
        // 满减活动和优惠券处理
        $this->load->model('extension/total/promotion_discount');
        $this->load->model('extension/total/giga_coupon');
        $total = 0;
        $gifts = []; // 满送
        $discounts = []; // 满减
        $totalData = array(
            'totals' => &$totals,
            'total' => &$total,
            'gifts' => &$gifts,
            'discounts' => &$discounts,
        );
        list($purchaseTotal, $purchaseProducts) = $this->handlePurchaseProducts($purchaseProducts);
        $this->model_extension_total_promotion_discount->getTotalByProducts($totalData, $purchaseProducts);
        $this->model_extension_total_giga_coupon->getTotalByProducts($totalData, $purchaseProducts);
        $collection = collect($totalData['totals'])->keyBy('code');
        $data['campaign_discount'] = $collection['promotion_discount']['value'] ?? 0; // 满减金额
        $data['campaigns'] = $discounts; // 促销活动
        array_map(function ($gift) {
            $gift->is_coupon = !empty($gift->conditions[$gift->id]->couponTemplate);
            $gift->condition_remark = $gift->conditions[$gift->id]->remark;
            if ($gift->is_coupon) {
                $gift->format_coupon_price =  $this->currency->formatCurrencyPrice($gift->conditions[$gift->id]->couponTemplate->denomination, $this->session->get('currency'));
            }
        }, $gifts);
        if ($orderId) {
            $coupons = app(CouponRepository::class)->getCouponByOrderId($orderId);
            if ($coupons->isNotEmpty()) {
                $data['selected_coupon']['coupon_ids'] = $coupons->pluck('id')->toArray();
                $data['selected_coupon']['value'] = $coupons->sum('denomination') * -1;
                $data['selected_coupon']['denomination'] = $coupons[0]->denomination ?? null;
                $data['selected_coupon']['order_amount'] = $coupons[0]->order_amount ?? null;
            } else {
                $data['selected_coupon'] = $collection['giga_coupon'];
            }
        } else {
            $data['selected_coupon'] = $collection['giga_coupon'];
        }
        $data['gifts'] = $gifts; // 满送
        $data['coupon'] = $this->modelPreOrder->getPreOrderCoupons($purchaseTotal, $data['selected_coupon']['coupon_ids'], abs($data['campaign_discount']));
        $data['originSubTotal'] = $subTotal;
        $data['totalSave'] = BCS::create($data['selected_coupon']['value'], ['scale' => 4])->add($data['campaign_discount'])->getResult();
        $data['subTotal'] = BCS::create($subTotal, ['scale' => 4])->add($data['totalSave'])->getResult();
        $data['symbolLeft'] = $this->currency->getSymbolLeft($data['currency']);
        $data['symbolRight'] = $this->currency->getSymbolRight($data['currency']);
        $data['precision'] = $this->customer->getCountryId() == JAPAN_COUNTRY_ID ? 0 : 2;
        $data['safeguardConfigs'] = app(SafeguardConfigRepository::class)->getBuyerConfigs($this->customer_id);
        $data['second_payment'] = $secondPayment;
        //endregion
        return $this->render('account/sales_order/sales_order_purchase_order_management', $data, [
            'column_left' => 'common/column_left',
            'column_right' => 'common/column_right',
            'content_top' => 'common/content_top',
            'content_bottom' => 'common/content_bottom',
            'footer' => 'common/footer',
            'header' => 'common/header',
        ]);
    }

    public function handlePurchaseProducts($orderProducts)
    {
        $orderProducts = collect($orderProducts)->where('product_type', 0);
        $total = 0;
        $data = [];
        foreach ($orderProducts as $item) {
            if ($item['product_id']) {
                $price = $item['current_price'] ?? $item['price'];
                // 销售订单后期有暂时没有处理议价
//            if ($item['type_id'] == ProductTransactionType::SPOT) {
//                $price = $item['quote_amount'] ?? $item['spot_price'];
//            }
                $total += $price * $item['quantity'];
                $data[] = $item;
            }
        }
        return [$total, $data];
    }

    /**
     * 校验订单状态是否为可支付
     *
     * @return JsonResponse
     */
    public function judgePurchaseSalesOrderCanPay()
    {
        $runId = $this->request->get('run_id', 0);
        //获取下单页数据
        $this->load->model('account/sales_order/match_inventory_window');
        $purchaseRecords = $this->model_account_sales_order_match_inventory_window->getPurchaseRecord($runId, $this->customer_id, false);
        $salesOrderIdArr = array_unique(array_column($purchaseRecords, 'order_id'));
        if (empty($salesOrderIdArr)) {
            return $this->jsonFailed('Sales Order information has been changed and is no longer valid.  Please place a new Sales Order.', ['url' => url()->to(['account/sales_order/sales_order_management'])]);
        }
        $checkStatusSalesOrderIdArr = app(CustomerSalesOrderRepository::class)->checkOrderStatus($salesOrderIdArr, CustomerSalesOrderStatus::TO_BE_PAID);
        if (count($checkStatusSalesOrderIdArr) != count($salesOrderIdArr)) {
            return $this->jsonFailed('Sales Order information has been changed and is no longer valid.  Please place a new Sales Order.', ['url' => url()->to(['account/sales_order/sales_order_management'])]);
        }
        return $this->jsonSuccess();
    }

}
