<?php

use App\Components\Locker;
use App\Components\RemoteApi\Yzcm\SalesOrderApi;
use App\Components\Storage\StorageCloud;
use App\Enums\Common\YesNoEnum;
use App\Enums\Customer\CustomerAccountingType;
use App\Enums\ModifyLog\CommonOrderActionStatus;
use App\Enums\SalesOrder\CustomerSalesOrderSynMode;
use App\Enums\ModifyLog\CommonOrderProcessCode;
use App\Enums\Product\ProductTransactionType;
use App\Enums\SalesOrder\CustomerSalesOrderLineItemStatus;
use App\Enums\SalesOrder\CustomerSalesOrderMode;
use App\Enums\SalesOrder\CustomerSalesOrderPickUpStatus;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Enums\SalesOrder\HomePickCarrierType;
use App\Enums\SalesOrder\HomePickImportMode;
use App\Enums\SalesOrder\HomePickLabelReviewStatus;
use App\Enums\SalesOrder\HomePickLabelContainerType;
use App\Enums\SalesOrder\HomePickOtherLabelType;
use App\Enums\SalesOrder\HomePickPlatformType;
use App\Enums\SalesOrder\HomePickUploadType;
use App\Enums\Track\TrackStatus;
use App\Enums\Warehouse\SellerType;
use App\Helper\CountryHelper;
use App\Helper\GigaOnsiteHelper;
use App\Helper\StringHelper;
use App\Logging\Logger;
use App\Models\Link\OrderAssociatedDeletedRecord;
use App\Models\Product\Tag;
use App\Models\SalesOrder\CustomerOrderModifyLog;
use App\Models\SalesOrder\CustomerSalesOrder;
use App\Models\SalesOrder\CustomerSalesOrderPickUp;
use App\Models\SalesOrder\CustomerSalesOrderPickUpLineChange;
use App\Models\SalesOrder\HomePickLabelDetails;
use App\Repositories\Customer\CustomerRepository;
use App\Repositories\FeeOrder\StorageFeeRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Safeguard\SafeguardBillRepository;
use App\Repositories\SalesOrder\CustomerSalesOrderPickUpLineRepository;
use App\Repositories\SalesOrder\CustomerSalesOrderRepository;
use App\Repositories\SalesOrder\ManifestRepository;
use App\Repositories\SalesOrder\Validate\salesOrderSkuValidate;
use App\Repositories\Warehouse\ReceiptRepository;
use App\Repositories\Warehouse\WarehouseRepository;
use App\Services\FeeOrder\FeeOrderService;
use App\Services\FeeOrder\StorageFeeService;
use App\Services\SalesOrder\SalesOrderPickUpService;
use App\Services\SalesOrder\SalesOrderService;
use App\Services\SalesOrder\CancelOrder\DropshipCancelOrderService;
use App\Services\Stock\BuyerStockService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Catalog\model\account\sales_order\SalesOrderManagement as sales_model;
use Framework\App;
use Framework\DataProvider\Paginator;
use Framework\DataProvider\Sort;
use Framework\Helper\Json;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class ControllerAccountCustomerOrder
 * 上门取货Buyer导入销售单
 * @property ModelAccountAddress $model_account_address
 * @property ModelAccountCustomerOrder $model_account_customer_order
 * @property ModelAccountCustomerOrderImport $model_account_customer_order_import
 * @property ModelCheckoutCart $model_checkout_cart
 * @property ModelExtensionModulePrice $model_extension_module_price
 * @property ModelLocalisationZone $model_localisation_zone
 * @property ModelToolCsv $model_tool_csv
 * @property ModelToolImage $model_tool_image
 * @property ModelToolEXCEL $model_tool_excel
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelCheckoutOrder $model_checkout_order
 * @property ModelAccountCustomerOrderImport $customerOrderImport
 * @property ModelLocalisationCountry $model_localisation_country
 * @property ModelAccountMappingWarehouse $model_account_mapping_warehouse
 */
class ControllerAccountCustomerOrder extends Controller
{
    const OMD_ORDER_CANCEL_UUID = 'c1996d6f-30df-46dc-b8ba-68a28efb83d7';
    const YZCM_AUTH_TEST = 'admin:123456';
    const NUM = 4; //
    const EXCEL_SUFFIX = ['xls', 'xlsx'];
    const CSV_SUFFIX = ['csv'];
    const PDF_SUFFIX = ['pdf'];

    private $sales_model;
    protected $tracking_privilege;
    protected $is_auto_buyer;

    /**
     * ControllerAccountCustomerOrder constructor.
     * @param Registry $registry
     * @throws Exception
     */
    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        if (!$this->customer->isLogged()) {
            $this->session->set('redirect', url()->to(['account/customer_order']));
            return $this->response->redirectTo(url()->to(['account/login']))->send();
        }
        if ($this->customer->isPartner()) {
            return $this->response->redirectTo(url()->to(['account/customerpartner/productlist']))->send();
        }
        load()->language('account/sales_order/sales_order_management');
        $this->sales_model = new sales_model($registry);
        load()->model('account/customer_order_import');
        load()->model('account/customer_order');
        //3150 自动购买用户 通过配置限制
        $this->is_auto_buyer = boolval($this->customer->getCustomerExt(1));
        $this->tracking_privilege = $this->model_account_customer_order_import->getTrackingPrivilege($this->customer->getId(), $this->customer->isCollectionFromDomicile(), $this->customer->getCountryId());
    }

    /**
     * @return string|\Symfony\Component\HttpFoundation\RedirectResponse
     * @throws Throwable
     */
    public function index()
    {
        if (!$this->customer->isCollectionFromDomicile()) {
            return $this->response->redirectTo(url()->to(['account/sales_order/sales_order_management']));
        }
        // 载入语言包
        load()->language('account/customer_order');
        // 载入模型
        load()->model('account/customer_order');
        // 预定义变量
        $get = $this->request->query;
        $data = [];
        $data['guide'] = $get->get('action') == 'guide' ? 1 : 0;
        $data['label_tag'] = $get->get('label_tag', 0);
        //激活的页签  index从0开始计数
        $data['tab_index'] = (int)$get->get('tabIndex', 0);
        $data['init_filter'] = (int)$get->get('initFilter', 1);
        $data['filter_flag'] =  request('filter_flag', 0);
        $data['filter_orderStatus'] = request('filter_orderStatus', null);
        // title
        $this->document->setTitle($this->language->get('heading_title'));
        // breadcrumbs
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => url()->to(['common/home'])
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => url()->to(['account/customer_order'])
        );
        $moduleShipmentTimeStatus = configDB('module_shipment_time_status');
        if ($moduleShipmentTimeStatus) {
            $data['module_shipment_time_status'] = $moduleShipmentTimeStatus;
            $data['shipment_time_url'] = url()->to(['information/information', 'shipmentTime' => 1]);
        }
        $data['continue'] = url()->to(['account/account']);
        //自动购买的Buyer账号，B2B订单导入入口封闭掉 xxl edit
        $data['autoBuyer'] = $this->is_auto_buyer;
        //3150 导单入口通过配置判断
        $data['importOrder'] = boolval($this->customer->getCustomerExt(2));
        //end xxl
        if ($get->has('checkoutViewBp')) {
            $data['checkoutViewBp'] = true;
            $data['bpViewUrl'] = url()->to(['account/customer_order/customerOrderTable', 'filter_orderStatus' => CustomerSalesOrderStatus::BEING_PROCESSED, 'page_num' => 1]);
        }
        //需求13548 RMA Management 页面跳转 edit by xxl
        if (isset($this->request->get['purchase_order_id'])) {
            $order_id = $this->request->get['purchase_order_id'];
            $data['fromRMALink'] = true;
            $data['order_id'] = $order_id;
            $data['rmaViewUrl'] = url()->to(['account/customer_order/customerOrderTable', 'filter_orderId' => $order_id, 'filter_tracking_number' => 2]);
        }
        //end 13548
        //需求101348 从buyer_central跳转
        if (isset($this->request->get['from_buyer_central'])) {
            $query = '&page_num=1';
            if (isset($this->request->get['filter_orderStatus'])) {
                $query .= '&filter_orderStatus=' . $this->request->get['filter_orderStatus'];
            }
            if (isset($this->request->get['filter_tracking_number'])) {
                $query .= '&filter_tracking_number=' . $this->request->get['filter_tracking_number'];
            }
            $data['from_buyer_central_query'] = $query;
        }
        //end1 101348
        $data['japan_home_pick'] = (bool)($this->customer->isCollectionFromDomicile() && $this->customer->isJapan());
        $data['is_usa'] = $this->customer->isUSA();
        $this->response->setOutput(load()->view('account/customer_order', $data));
        return $this->render('account/customer_order', $data, [
            'column_left' => 'common/column_left',
            'column_right' => 'common/column_right',
            'content_top' => 'common/content_top',
            'content_bottom' => 'common/content_bottom',
            'footer' => 'common/footer',
            'header' => 'common/header',
        ]);
    }

    /**
     * 判断Buyer模式 显示不同的视图
     * @return string
     * @throws Exception
     */
    public function customerOrderImport()
    {

        //1.做一个dropship 权限判断。
        //2.上传csv 放置问题，以及错误显示。
        //3.创建临时表和正式表。
        //4.插入信息时进行判断。
        load()->language('account/customer_order_import');
        load()->model('account/customer_order_import');
        $data['country_id'] = $this->customer->getCountryId();
        //customer 中字段 user_mode
        $user_mode = $this->customer->getUserMode();
        $data['downloadTemplateHref'] = url()->to(['account/customer_order/downloadTemplateFile']);
        $data['downloadAmazonTemplateHref'] = url()->to(['account/customer_order/downloadAmazonTemplateFile']);
        $data['downloadDPTemplateHref'] = url()->to(['account/customer_order/downloadDPTemplateFile']);
        $data['downloadOtherTemplateHref'] = url()->to(['account/customer_order/downloadOtherTemplateFile']);
        $data['downloadInterpretationHref'] = url()->to(['account/customer_order/downloadTemplateInterpretationFile']);
        $data['otherInstructionHref'] = url()->to(['account/customer_order/otherInstructionHref']);
        $data['walmartInstructionHref'] = url()->to(['account/customer_order/walmartInstruction']);
        $data['europeWayfairInstructionHref'] = url()->to(['account/customer_order/europeWayfairInstruction']);


        //获取上传历史数据
        $result = $this->model_account_customer_order_import->getUploadHistory();
        array_walk($result, function (&$value, $key) {
            if (StorageCloud::orderCsv()->fileExists($value['file_path'])) {
                // 需要处理数据库里存储的path
                $value['file_path'] = StorageCloud::orderCsv()->getUrl($value['file_path']);
            }
        });
        $last_import_mode = isset($result[0]) ? $result[0]['import_mode'] : 0;
        $data['last_import_mode'] = $last_import_mode;
        $data['historys'] = $result;
        $data['upload_history_records'] = url()->to(['account/customer_order/uploadHistoryRecords']);



        $tip = 'Giga Pickup orders from other external platform should be input to Giga Cloud marketplace in required form. Customer service will then process manually.';
        $order_from_list = [];
        switch ($this->customer->getCountryId()) {
            case AMERICAN_COUNTRY_ID:
                //美国
                $order_from_list = [
                    [
                        'name' => 'upload_type',
                        'value' => '0',
                        'checked' => $last_import_mode == HomePickImportMode::IMPORT_MODE_AMAZON ? true : false,
                        'labelauty' => 'Amazon Dropship Order',
                        'text' => 'Amazon',
                    ],
                    [
                        'name' => 'upload_type',
                        'value' => '1',
                        'checked' => $last_import_mode == HomePickImportMode::IMPORT_MODE_WAYFAIR ? true : false,
                        'labelauty' => 'Wayfair Dropship Order',
                        'text' => 'Wayfair',
                    ],
                    [
                        'name' => 'upload_type',
                        'value' => '3',
                        'checked' => $last_import_mode == HomePickImportMode::IMPORT_MODE_WALMART ? true : false,
                        'labelauty' => 'Walmart Dropship Order',
                        'text' => 'Walmart',
                    ],
                    [
                        'name' => 'upload_type',
                        'value' => '2',
                        'checked' => $last_import_mode == HomePickImportMode::US_OTHER ? true : false,
                        'labelauty' => 'General Dropship Order',
                        'text' => 'Other External Platform',
                        'tip' => $this->language->get('text_upload_tips'),
                    ],
                    [
                        'name' => 'upload_type',
                        'value' => '4',
                        'checked' => $last_import_mode == HomePickImportMode::IMPORT_MODE_BUYER_PICK_UP ? true : false,
                        'labelauty' => 'General Dropship Order',
                        'text' => HomePickImportMode::getDescription(HomePickImportMode::IMPORT_MODE_BUYER_PICK_UP),//自提货
                        'tip' => 'If you drive to the warehouse to pick up the goods by yourself, please select \'Buyer Pick-up\' and import your sales order for pick-up.',
                    ],
                ];
                break;
            case HomePickUploadType::BRITAIN_COUNTRY_ID:
                //英国
                $order_from_list = [
                    [
                        'name' => 'upload_type',
                        'value' => '0',
                        'checked' => $last_import_mode == HomePickImportMode::IMPORT_MODE_AMAZON ? true : false,
                        'labelauty' => 'Amazon Dropship Order',
                        'text' => 'Amazon',
                    ],
                    [
                        'name' => 'upload_type',
                        'value' => '1',
                        'checked' => $last_import_mode == HomePickImportMode::IMPORT_MODE_WAYFAIR ? true : false,
                        'labelauty' => 'Wayfair Dropship Order',
                        'text' => 'Wayfair',
                    ],
                    [
                        'name' => 'upload_type',
                        'value' => '2',
                        'checked' => $last_import_mode == HomePickImportMode::US_OTHER ? true : false,
                        'labelauty' => 'General Dropship Order',
                        'text' => 'Other External Platform',
                        'tip' => $tip,
                    ],
                ];
                break;
            case HomePickUploadType::GERMANY_COUNTRY_ID:
                //德国
                $order_from_list = [
                    [
                        'name' => 'upload_type',
                        'value' => '1',
                        'checked' => $last_import_mode == HomePickImportMode::IMPORT_MODE_WAYFAIR ? true : false,
                        'labelauty' => 'Wayfair Dropship Order',
                        'text' => 'Wayfair',
                    ],
                    [
                        'name' => 'upload_type',
                        'value' => '2',
                        'checked' => $last_import_mode == HomePickImportMode::US_OTHER ? true : false,
                        'labelauty' => 'General Dropship Order',
                        'text' => 'Other External Platform',
                        'tip' => $tip,
                    ],
                ];
                break;
            case JAPAN_COUNTRY_ID:
                //日本
                $order_from_list = [];//不显示
                break;
            default:
                break;
        }
        $data['order_from_list'] = $order_from_list;
        $data['app_version'] = APP_VERSION;
        if ($user_mode) {
            // Flatfair 订单导入模式
            load()->model('localisation/country');
            // 获取国家
            $data['countries'] = $this->model_localisation_country->getCountries();
            // 获取地址
            load()->model('account/address');
            $data['addresses'] = $this->model_account_address->getAddresses();
            // 获取SellerName
            $data['sellers'] = $this->model_account_customer_order_import->getAllSellerName();
            return $this->render('account/corder_import_flatfair', $data);
            // 获取SellerName
        } else {
            // 基本用户订单导入模式
            return $this->render('account/corder_import_basic', $data);
        }

    }

    /**
     * [uploadHistoryRecords description] 文件上传的记录 page
     * @return string
     * @throws Exception
     */
    public function uploadHistoryRecords()
    {

        load()->language('account/customer_order_import');
        load()->language('account/customer_order');
        $url = '';
        $param = [];
        $filter_orderDate_from = Request('filter_orderDate_from', '');
        $filter_orderDate_to = Request('filter_orderDate_to', '');
        if ($filter_orderDate_from) {
            $param['filter_orderDate_from'] = $data['filter_orderDate_from'] = $filter_orderDate_from;
            $url .= '&filter_orderDate_from=' . $filter_orderDate_from;
        }

        if ($filter_orderDate_to) {
            $param['filter_orderDate_to'] = $data['filter_orderDate_to'] = $filter_orderDate_to;
            $url .= '&filter_orderDate_to=' . $filter_orderDate_to;
        }

        $page = Request('page', 1);
        $perPage = Request('page_limit', 100);

        $total = $this->model_account_customer_order_import->getSuccessfullyUploadHistoryTotal($param);
        $result = $this->model_account_customer_order_import->getSuccessfullyUploadHistory($param, $page, $perPage);

        $pagination = new Pagination();
        $pagination->total = $total;
        $pagination->page = $page;
        $pagination->limit = $perPage;
        $data['submit_url'] = url()->to(['account/customer_order/uploadHistoryRecords']);
        $pagination->url = $this->url->link('account/customer_order/uploadHistoryRecords/' . $url, '&page={page}', true);
        $data['pagination'] = $pagination->render();
        $data['results'] = sprintf($this->language->get('text_pagination'), ($total) ? (($page - 1) * $perPage) + 1 : 0, ((($page - 1) * $perPage) > ((int)$total - $perPage)) ? $total : ((($page - 1) * $perPage) + $perPage), $total, ceil((int)$total / $perPage));
        $data['historys'] = $result;
        $data['page'] = $page;

        return $this->render('account/corder_upload_history_records', $data, [
            'footer' => 'common/footer',
            'header' => 'common/header',
        ]);

    }

    /**
     * sales order management  => sales order
     * @return string
     * @throws Exception
     */
    public function customerOrderTable()
    {
        load()->language('account/customer_order_import');
        load()->language('account/customer_order');
        load()->model('tool/image');
        $country = session('country', 'USA');
        $countryId = CountryHelper::getCountryByCode($country);
        // 用户ID
        $customer_id = Customer()->getId();
        $country_id = Customer()->getCountryId();
        date_default_timezone_set('America/Los_Angeles');
        $data = [];
        $filter_flag = (bool)request('filter_flag', false);
        $filter_button = Request('filter_button', 0);
        $filter_orderStatus = Request('filter_orderStatus', null);
        $filter_orderId = Request('filter_orderId', null);
        $filter_orderDate_from = Request('filter_orderDate_from', null);
        $filter_orderDate_to = Request('filter_orderDate_to', null);
        $filterDeliveryStatus = $this->request->get('filter_delivery_status',-1);
        //获取tracking_number 和 item_code
        $filter_item_code = Request('filter_item_code', null);
        if ($filter_orderStatus
            || $filter_orderId
            || $filter_orderDate_from
            || $filter_orderDate_to
            || ($filterDeliveryStatus != -1)
            || $filter_item_code) {
            $filter_flag = true;
        }
        $filter_tracking_number = Request('filter_tracking_number', 2);
        $filter_import_mode = Request('filter_import_mode', '-1');
        $filter_cancel_not_applied_rma = Request('filter_cancel_not_applied_rma', '-1');
        $filter_pick_up_status = Request('filter_pick_up_status', '-1');
        if (Request('filter_cancel_not_applied_rma')) {
            $filter_flag = true;
        }

        //N-1104
        $label_tag = Request('label_tag', YesNoEnum::NO);
        /* 分页 */
        $paginator = new Paginator(['pageParam' => 'page_num']);
        $data['paginator'] = $paginator;

        // 首次加载时，存在 initFilter 参数时
        if (Request('initFilter') == 1 && !$filter_orderStatus) {
            // 首次进入，若存在费用待支付的，默认显示费用待支付的列表
            $hasFeeToBePaid = app(CustomerSalesOrderRepository::class)
                    ->getCustomerSalesOrderCountByStatus($customer_id, CustomerSalesOrderStatus::PENDING_CHARGES) > 0;
            if ($hasFeeToBePaid) {
                $filter_orderStatus = CustomerSalesOrderStatus::PENDING_CHARGES;
            }
            $filter_flag = true;
        }

        $sort = new Sort([
            'enableMultiple' => false,
            'defaultOrder' => ['created_time' => SORT_DESC],
            'rules' => [
                'created_time' => 'c.create_time',
            ],
        ]);
        $data['sort'] = $sort;
        //过滤参数
        $currentSort = $sort->getCurrentSortWithAttribute();
        $page_start = $paginator->getOffset();
        $page_limit = $paginator->getLimit();
        $param = array(
            'filter_orderStatus' => $filter_orderStatus,
            'filter_orderId' => trim($filter_orderId),
            'filter_orderDate_from' => $filter_orderDate_from,
            'filter_orderDate_to' => $filter_orderDate_to,
            'filter_tracking_number' => $filter_tracking_number,
            'filter_delivery_status' => $filterDeliveryStatus,
            'filter_item_code' => trim($filter_item_code),
            'filter_import_mode' => $filter_import_mode,
            'label_tag' => $label_tag, // 假如传入了label tag 则定义为美国上门取货需要手动审核label部分
            'filter_cancel_not_applied_rma' => $filter_cancel_not_applied_rma,
            'sort' => count($currentSort) > 0 ? $currentSort[0]['attribute'] : '',
            'order' => count($currentSort) > 0 ? strtoupper($currentSort[0]['direction']) : '',
            'start' => $page_start,
            'limit' => $page_limit,
            'customer_id' => $customer_id,
            'tracking_privilege' => $this->tracking_privilege,
            'delivery_type' => $this->customer->isCollectionFromDomicile() ? 1 : 0,
            'filter_pick_up_status' => $filter_pick_up_status,
        );
        // 上门取货
        switch ($country_id) {
            case AMERICAN_COUNTRY_ID://美
                $platformKeyList = HomePickPlatformType::getALLPlatformTypeViewItems();
                break;
            case HomePickUploadType::BRITAIN_COUNTRY_ID://英
                $platformKeyList = [
                    HomePickPlatformType::DEFAULT => 'Other External Platform',
                    HomePickPlatformType::AMAZON => 'Amazon',
                    HomePickPlatformType::WAYFAIR => 'Wayfair',
                ];
                break;
            case HomePickUploadType::GERMANY_COUNTRY_ID://德
                $platformKeyList = [
                    HomePickPlatformType::DEFAULT => 'Other External Platform',
                    HomePickPlatformType::WAYFAIR => 'Wayfair',
                ];
                break;
            case JAPAN_COUNTRY_ID://日
                $platformKeyList = [];
                break;
            default:
                $platformKeyList = HomePickPlatformType::getALLPlatformTypeViewItems();
                break;
        }
        $data['platformKeyList'] = $platformKeyList;

        // 欧洲的上门取货需要显示manifest且需要判断是否有未上传的file
        $data['has_manifest'] = YesNoEnum::NO;
        if (in_array($country_id, EUROPE_COUNTRY_ID)) {
            $data['has_manifest'] = YesNoEnum::YES;
            $data['has_manifest_tips'] = $this->model_account_customer_order_import->judgeOrderManifestFile($customer_id);
        }
        if ($filter_button == 1 || ($filter_button == 0 && $filter_flag)) {
            load()->model('catalog/product');
            load()->model('account/deliverySignature');
            $results = [];
            $tmp = $this->model_account_customer_order->queryOrderNum($param, true);
            $paginator->setTotalCount($tmp['total']);
            $page_num = $paginator->getTotalCount();
            if ($tmp['idStr']) {
                $idArr = explode(',', trim($tmp['idStr'], ','));
                if (strlen($tmp['idStr']) >= 102400) {
                    $idArrLimit = array_splice($idArr, $page_start, $page_limit);//分页效果
                    $sub_total_pages = ceil(count($idArr) / $page_limit);
                    $idStr = ($sub_total_pages - 2) <= $page_num ? implode(',', $idArrLimit) : null;//避免数据库返回的idStr被数据库截断，idStr中最后2页则设置为null
                } else {
                    $idArrLimit = array_splice($idArr, $page_start, $page_limit);//分页效果
                    $idStr = implode(',', $idArrLimit);
                }

                $results = $this->model_account_customer_order->queryOrders($param, $idStr);
            }
            $numStart = $page_start;
            $data['orders'] = $this->model_account_customer_order_import->getHomePickListInfos(
                $results,
                [
                    'numStart' => $numStart,
                    'tracking_privilege' => $this->tracking_privilege,
                    'is_auto_buyer' => $this->is_auto_buyer,
                ]
            );

            //分页
            $data['filter_flag'] = true;
            $data['dropship_file_upload'] = url()->to(['account/customer_order/dropshipOrderFileUpload']);
            $data['text_no_results'] = $this->language->get('text_no_results');

        } else {
            $data['filter_flag'] = $filter_flag;
        }
        $data['label_view_show'] = ($this->getLabelViewData($customer_id))['error'];
        $data['trackingSearchShow'] =  (customer()->getCountryId() == AMERICAN_COUNTRY_ID && customer()->isCollectionFromDomicile());
        $data['filter_orderStatus'] = $filter_orderStatus;
        $data['filter_orderId'] = $filter_orderId;
        $data['filter_order_date_range'] = Request('filter_order_date_range', 0);
        $data['filter_orderDate_from'] = $filter_orderDate_from;
        $data['filter_orderDate_to'] = $filter_orderDate_to;
        $data['filter_item_code'] = $filter_item_code;
        $data['filter_tracking_number'] = $filter_tracking_number;
        $data['filter_delivery_status'] = $filterDeliveryStatus;
        $data['filter_import_mode'] = $filter_import_mode;
        $data['filter_cancel_not_applied_rma'] = $filter_cancel_not_applied_rma;
        $data['app_version'] = APP_VERSION;
        $data['country_id'] = $country_id;
        $data['is_usa'] = customer()->isUSA();
        $data['delivery_status_list'] = TrackStatus::getHomePickViewItems();
        $data['now'] = date('Y-m-d H:i:s');
        $data['listCustomerSalesOrderStatus'] = CustomerSalesOrderStatus::listStatusForBuyerPickup($countryId);
        return $this->render('account/corder_info', $data);
    }

    /**
     * #24701 该页面不再需要
     * public function orderNeedToBuy()
     */

    /**
     * @deprecated
     * @param int $product_id
     * @param float $price
     * @return false|float|mixed
     * @throws Exception
     */
    public function calculatePrice($product_id, $price)
    {
        load()->model('catalog/product');
        //add by xxli 购物车折扣
        $customer_id = $this->customer->getId();
        $customerCountry = null;
        $customerCountry = $this->customer->getCountryId();
        $seller_id = $this->db->query("Select * from oc_customerpartner_to_product where product_id = " . $product_id)->row['customer_id'];


        $discountResult = $this->model_catalog_product->getDiscount($customer_id, $seller_id);
        $price = $this->model_catalog_product->getDiscountPrice($price, $discountResult);

        //        if ($customerCountry) {
        //            $price = $this->country->getDisplayPrice($customerCountry, $price);
        //        }

        // Product Specials
        $product_special_query = $this->db->query("SELECT price FROM " . DB_PREFIX . "product_special
        WHERE product_id = '" . (int)$product_id . "' AND customer_group_id = '" . (int)configDB('config_customer_group_id') . "'
        AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) ORDER BY priority ASC, price ASC LIMIT 1");

        if ($product_special_query->num_rows) {
            $price = $product_special_query->row['price'];
        }

        if ($customerCountry == JAPAN_COUNTRY_ID) {
            $price = round($price);
        }
        return $price;
    }

    /**
     * @throws Throwable
     */
    public function orderDetail()
    {
        load()->language('account/customer_order');
        load()->language('account/customer_order_import');
        load()->model("account/customer_order_import");
        load()->model("account/customer_order");
        load()->model('catalog/product');
        load()->model('tool/image');


        $order_header_id = $this->request->get('id', 0);

        $results = $this->model_account_customer_order_import->getCustomerSalesOrderLineByHeaderId($order_header_id);
        foreach ($results as $key => $v) {
            $tag_array = $this->model_catalog_product->getProductSpecificTagByOrderLineId($v['id']);
            $tags = array();
            if (isset($tag_array)) {
                foreach ($tag_array as $tag) {
                    if (isset($tag['origin_icon']) && !empty($tag['origin_icon'])) {
                        //采用唯一图片  LTL.jpg  part.jpg  combo-img.png
                        $img_url = $this->model_tool_image->getOriginImageProductTags($tag['origin_icon']);
                        $tags[] = '<img data-toggle="tooltip"  class="' . $tag['class_style'] . '" title="' . $tag['description'] . '" style="padding-left: 1px" src="' . $img_url . '">';
                    }
                }
            }
            $v['tag'] = $tags;
            $v['item_status_name'] = CustomerSalesOrderLineItemStatus::getDescription($v['item_status']);
            $results[$key] = $v;

        }

        $sum_info = $this->model_account_customer_order->queryOrderByOrderId($order_header_id);
        foreach ($sum_info as $key => $value) {
            $sum_info[$key]['discount_amount'] = sprintf('%.2f', $value['discount_amount']);
            $sum_info[$key]['tax_amount'] = sprintf('%.2f', $value['tax_amount']);
            $sum_info[$key]['order_total'] = sprintf('%.2f', $value['order_total']);
        }
        $data = array();
        $data['details'] = $results;
        $data['all_details'] = $sum_info;
        $this->response->setOutput(load()->view('account/corder_info_detail', $data));
    }

    /**
     * @throws Throwable
     */
    public function customerOrderSalesOrderDetails()
    {
        load()->language('account/customer_order_import');
        load()->language('account/customer_order');
        load()->language('common/cwf');
        load()->model("account/customer_order_import");
        /**
         * @var ModelAccountCustomerOrderImport $customerOrderImport
         */
        $customerOrderImport = $this->model_account_customer_order_import;
        load()->model("account/customer_order");
        $country_id = $this->customer->getCountryId();

        $this->document->setTitle($this->language->get('text_heading_title_details'));

        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => url()->to(['common/home'])
            ],
            [
                'text' => $this->language->get('text_customer_order'),
                'href' => url()->to(['account/customer_order'])
            ],
        ];

        if (!$this->customer->isCollectionFromDomicile() && $this->customer->isUSA()) {
            $data['breadcrumbs'][] = [
                'text' => $this->language->get('heading_title_drop_shipping'),
                'href' => url()->to(['account/customer_order'])
            ];
        }
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_heading_title_details'),
            'href' => 'javascription:void(0)'
        ];

        $order_header_id = $this->request->get('id', 0);
        $res = $customerOrderImport->getCustomerOrderAllInformation($order_header_id, $this->tracking_privilege);
        $order_status_label = $this->model_account_customer_order->getSalesOrderStatusLabel($order_header_id);

        //不能越权查看别人的订单信息
        if ($res['base_info']['buyer_id'] != $this->customer->getId()) {
            $this->redirect(['account/customer_order'])->send();
        }

        //是否为欧洲
        $isEurope = false;
        if ($this->country->isEuropeCountry($this->customer->getCountryId())) {
            $isEurope = true;
        }
        $data['futures_detail_url'] = url()->to(['account/product_quotes/futures/detail']);
        $data['service_type'] = SERVICE_TYPE;
        $data['isCollectionFromDomicile'] = $this->customer->isCollectionFromDomicile();
        $data['isEurope'] = $isEurope;
        $data['base_info'] = $res['base_info'];
        $data['item_list'] = $res['item_list'];
        $data['shipping_information'] = $res['shipping_information'];
        $data['signature_list'] = $res['signature_list'];
        $data['sub_total'] = $res['sub_total'];
        $data['fee_total'] = $res['fee_total'];
        $data['all_total'] = $res['all_total'];
        $data['item_total_price'] = $res['item_total_price'];
        $data['shipping_address'] = implode(',', array_filter([$res['base_info']['ship_address1'], $res['base_info']['ship_city'], $res['base_info']['ship_state'], $res['base_info']['ship_zip_code'], $res['base_info']['ship_country']]));
        $data['safeguard_bills'] = app(SafeguardBillRepository::class)->getSafeguardBillsBySaleId($order_header_id);
        $data['now'] = date('Y-m-d H:i:s');
        $data['country_id'] = $country_id;
        $data['column_left'] = load()->controller('common/column_left');
        $data['column_right'] = load()->controller('common/column_right');
        $data['content_top'] = load()->controller('common/content_top');
        $data['content_bottom'] = load()->controller('common/content_bottom');
        $data['footer'] = load()->controller('common/footer');
        $data['header'] = load()->controller('common/header');
        $data['href_go_back'] = url()->to(['account/customer_order']);
        $data['order_status_label'] = $order_status_label;
        $data['item_final_total_price'] = $res['item_final_total_price'];
        $data['trackingSearchShow'] = (customer()->getCountryId() == AMERICAN_COUNTRY_ID);

        //100783 虚拟支付 采用虚拟支付的订单，取消时不可保留库存
        $data['hasVirtualPayment'] = $this->model_account_customer_order->hasVirtualPayment($order_header_id);
        //自提货
        if (isset($data['base_info']['import_mode']) && $data['base_info']['import_mode'] == HomePickImportMode::IMPORT_MODE_BUYER_PICK_UP) {
            return $this->render('account/customer_order_sales_orderDetails_pick_up', $this->pickUpDetail($order_header_id, $data), 'buyer');
        }
        $this->response->setOutput(load()->view('account/customer_order_sales_orderDetails', $data));
    }

    //自提货--订单详情
    public function pickUpDetail($order_header_id, &$data)
    {
        //销售渠道
        $data['base_info']['orders_from'] = HomePickImportMode::getDescription(HomePickImportMode::IMPORT_MODE_BUYER_PICK_UP);
        //申请信息
        $pickUpInfo = CustomerSalesOrderPickUp::query()->where('sales_order_id', $order_header_id)->first();
        $data['pickUpInfo'] = $pickUpInfo;
        //取货待确认
        $data['pickUpInfoTbc'] = ($pickUpInfo->pick_up_status == CustomerSalesOrderPickUpStatus::PICK_UP_INFO_TBC && $data['base_info']['order_status'] == CustomerSalesOrderStatus::BEING_PROCESSED) ? true : false;
        //备货中
        $data['pickUpInPrep'] = ($pickUpInfo->pick_up_status == CustomerSalesOrderPickUpStatus::IN_PREP && $data['base_info']['order_status'] == CustomerSalesOrderStatus::BEING_PROCESSED) ? true : false;
        //超时未取货
        $data['pickUpTimeOut'] = ($pickUpInfo->pick_up_status == CustomerSalesOrderPickUpStatus::PICK_UP_TIMEOUT && $data['base_info']['order_status'] == CustomerSalesOrderStatus::ON_HOLD) ? true : false;
        //申请仓库信息
        $data['warehouseInfo'] = $pickUpInfo->warehouse;
        //仓库发货信息变更记录信息
        $pickUpLineChanges = $pickUpInfo->pickUpLineChanges;
        //信息对比--取货待确认、取货待确认的取消
        if ($pickUpLineChanges->isNotEmpty() && $pickUpInfo->pick_up_status == CustomerSalesOrderPickUpStatus::PICK_UP_INFO_TBC
            && ($data['base_info']['order_status'] == CustomerSalesOrderStatus::CANCELED || $data['base_info']['order_status'] == CustomerSalesOrderStatus::BEING_PROCESSED)
        ) {
            //变更记录信息最新一条
            $pickUpLineChange = $pickUpLineChanges->get($pickUpLineChanges->count() - 1);
            //最新取货信息
            $data['originPickUpInfo'] = app(CustomerSalesOrderPickUpLineRepository::class)->dealPickUpJson($pickUpLineChange->origin_pick_up_json);
            //最新仓库给的取货信息
            $data['storePickUpInfo'] = app(CustomerSalesOrderPickUpLineRepository::class)->dealPickUpJson($pickUpLineChange->store_pick_up_json);
            //倒计时
            $timeCountDown = (new Carbon())->diffInSeconds(Carbon::parse($pickUpLineChange->create_time)->addHours(48), false);
            if ($timeCountDown > 0 && $timeCountDown <= (48 * 60 * 60)) {
                $data['seconds_remaining'] = intval($timeCountDown);
            }
        }
        //历史申请信息
        if ($pickUpLineChanges->isNotEmpty()) {
            //变更记录信息最新一条
            $pickUpLineChange = $pickUpLineChanges->get(0);
            //最新取货信息
            $data['oldPickUpInfo'] = $pickUpLineChange->is_buyer_accept ? app(CustomerSalesOrderPickUpLineRepository::class)->dealPickUpJson($pickUpLineChange->origin_pick_up_json) : [];
        }

        //BOL文件：发给仓库的文件 (待取货与仓库备货中显示)
        if ($pickUpInfo->bol_file_id && ($data['base_info']['order_status'] == CustomerSalesOrderStatus::WAITING_FOR_PICK_UP || $data['pickUpInPrep'])) {
            $data['pickUpBolFiles'] = $pickUpInfo->bol_files;
        }
        //取货凭证：取货完成后仓库给 (complete完成后显示)
        if ($pickUpInfo->pick_up_file_id && $data['base_info']['order_status'] == CustomerSalesOrderStatus::COMPLETED) {
            $data['pickUpFiles'] = $pickUpInfo->pick_files;
            foreach ($data['pickUpFiles'] as &$val) {
                $val->backImg = $val->filePath;
                if (strtolower(pathinfo($val['filePath'], PATHINFO_EXTENSION)) === 'pdf') {
                    $val->backImg = '/image/product/downLoad-bol.jpg';
                }
            }
        }
        //自提货状态信息
        if ($pickUpInfo->pick_up_status != CustomerSalesOrderPickUpStatus::DEFAULT
            && in_array($data['base_info']['order_status'], [CustomerSalesOrderStatus::BEING_PROCESSED, CustomerSalesOrderStatus::ON_HOLD])
        ) {
            $data['pickUpStatus'] = [
                'desc' => CustomerSalesOrderPickUpStatus::getDescription($pickUpInfo->pick_up_status),//状态描述
                'color' => CustomerSalesOrderPickUpStatus::getColorDescription($pickUpInfo->pick_up_status),//状态颜色值
            ];
        }
        //取消原因
        if ($data['base_info']['order_status'] == CustomerSalesOrderStatus::CANCELED) {
            $data['cancelMsg'] = CustomerOrderModifyLog::query()
                ->where('header_id', $order_header_id)
                ->where('order_id', $data['base_info']['order_id'])
                ->where('process_code', CommonOrderProcessCode::CANCEL_ORDER)
                ->where('status', CommonOrderActionStatus::SUCCESS)
                ->orderByDesc('id')
                ->value('cancel_reason');
        }

        //销售单明细--未删除
        $lines = $pickUpInfo->salesOrder->linesNoDelete;
        $data['itemTags'] = [];
        foreach ($lines as $v) {
            if (!isset($item_tag_list[$v->item_code])) {
                // 获取产品tag
                $tags = app(CustomerSalesOrderRepository::class)->getCustomerSalesOrderTags($v->id, $v->item_code);
                $data['itemTags'][$v->item_code] = $tags;
            }
        }
        $pkey = 0;
        foreach ($lines as $line) {
            $temp = [];
            $temp['itemCode'] = $line->item_code;//父sku
            $temp['qty'] = $line->qty;//父sku数量
            // 非 combo
            if (!$line->combo_info) {
                $temp['childItemCode'] = '';
                $temp['childQty'] = '';
                $temp['crossRow'] = 1;
                $data['productInfo'][$pkey] = $temp;
                $pkey++;
                continue;
            }
            // combo
            $combos = Json::decode($line->combo_info);
            $line = $pkey;
            $crossRow = 0;//合并的列数
            $exitsItemCodeTemp = [];
            foreach ($combos as $combo) {
                $qty = 0;
                foreach ($combo as $itemCode => $subQty) {
                    if (strtoupper($itemCode) == $temp['itemCode']) {
                        $qty = $subQty;
                        unset($combo[$itemCode]);//去除子产品
                        break;
                    }
                }
                foreach ($combo as $itemCode => $subQty) {
                    if (isset($exitsItemCodeTemp[$itemCode])) {
                        $data['productInfo'][$exitsItemCodeTemp[$itemCode]]['childQty'] += $subQty * $qty;//累加qty
                    } else {
                        $exitsItemCodeTemp[$itemCode] = $pkey;
                        $temp['childItemCode'] = $itemCode;//子sku
                        $temp['childQty'] = $subQty * $qty;//子sku数量
                        $data['productInfo'][$pkey] = $temp;
                        $pkey++;
                        $crossRow++;
                    }
                }
            }
            $data['productInfo'][$line]['crossRow'] = $crossRow;
        }
        $data['productInfoLineCount'] = $data['productInfo'] ? count($data['productInfo']) : 0;
        return $data;
    }

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws Exception
     */
    public function upload()
    {
        load()->language('account/customer_order_import');
        load()->model('account/customer_order_import');
        $json = [];
        $uploadType = $this->request->input->get('upload_type') + self::NUM;
        /** @var Symfony\Component\HttpFoundation\File\UploadedFile $fileInfo */
        $fileInfo = $this->request->file('file');
        $customer_id = $this->customer->getId();
        $country_id = $this->customer->getCountryId();

        // 检查文件名以及文件类型
        if (isset($fileInfo)) {
            $fileName = $fileInfo->getClientOriginalName();
            $fileType = $fileInfo->getClientOriginalExtension();
            if (in_array($uploadType, [HomePickImportMode::IMPORT_MODE_WAYFAIR, HomePickImportMode::IMPORT_MODE_AMAZON])
                && !in_array($fileType, self::CSV_SUFFIX)) {
                if ($uploadType == HomePickImportMode::IMPORT_MODE_WAYFAIR
                    && in_array($country_id, EUROPE_COUNTRY_ID)) {
                    $json['error'] = $this->language->get('error_wayfair_file_content');
                } else {
                    $json['error'] = $this->language->get('error_filetype');
                }
            } elseif ((HomePickImportMode::IMPORT_MODE_WALMART == $uploadType
                && !in_array($fileType, self::EXCEL_SUFFIX))) {
                $json['error'] = $this->language->get('error_walmart_filetype');
            } elseif (HomePickImportMode::US_OTHER == $uploadType
                && !in_array($fileType, self::EXCEL_SUFFIX)
                && $country_id == AMERICAN_COUNTRY_ID) {
                $json['error'] = 'The order file must be in .xls or .xlsx format only.';
            } elseif (HomePickImportMode::US_OTHER == $uploadType
                && !in_array($fileType, self::CSV_SUFFIX)
                && $country_id != AMERICAN_COUNTRY_ID
            ) {
                $json['error'] = $this->language->get('error_filetype');
            } elseif (HomePickImportMode::IMPORT_MODE_BUYER_PICK_UP == $uploadType && !in_array($fileType, self::EXCEL_SUFFIX)) {//自提货
                $json['error'] = 'The order file must be in .xls or .xlsx format only.';
            }
            if ($fileInfo->getError() != UPLOAD_ERR_OK) {
                $json['error'] = $this->language->get('error_upload_' . $fileInfo->getError());
            }

        } else {

            $json['error'] = $this->language->get('error_upload');

        }
        $import_mode = $uploadType;

        // 4 dropship
        // 5 warfair
        // 6 common
        //  7 walmart
        //  8 Buyer Pick-up 自提货
        // 上传订单文件，以用户ID进行分类
        if (!isset($json['error'])) {
            // 复制上传的文件到orderCSV路径下
            $run_id = msectime();
            $dateTime = date("Y-m-d_His");
            $realFileName = str_replace('.' . $fileType, '_', $fileName) . $dateTime . '.' . $fileType;
            StorageCloud::orderCsv()->writeFile($fileInfo, $customer_id, $realFileName);
            // 记录上传文件数据
            $fileData = [
                "file_name" => $fileInfo->getClientOriginalName(),
                "size" => $fileInfo->getSize(),
                "file_path" => $customer_id . "/" . $realFileName,
                "customer_id" => $customer_id,
                "import_mode" => $import_mode,
                "run_id" => $run_id,
                "create_user_name" => $customer_id,
                "create_time" => Carbon::now(),
                "handle_status" => 0,
            ];
            $this->model_account_customer_order_import->saveOrderFile($fileData);
            //预读csv文件内容
            $json['text'] = $this->language->get('text_upload');
            $json['runId'] = $run_id;
            $json['next'] = url()->to(['account/customer_order/saveOrder', 'runId' => $run_id, 'importMode' => $import_mode]);
        }

        return $this->response->json($json);
    }

    /**
     * [getCountryState description]
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws Exception
     */
    public function getCountryState()
    {
        load()->model('localisation/zone');
        $country_id = $this->request->post('country_id', 0);
        $arr = [
            'US' => AMERICAN_COUNTRY_ID,
            'GB' => HomePickUploadType::BRITAIN_COUNTRY_ID,
            'DE' => HomePickUploadType::GERMANY_COUNTRY_ID,
            'JP' => JAPAN_COUNTRY_ID,
        ];
        if (isset($arr[$country_id])) {
            if ($arr[$country_id] == JAPAN_COUNTRY_ID) {
                $json = $this->orm->table('tb_sys_country_state')->where(['country_id' => JAPAN_COUNTRY_ID])->selectRaw('county as name,county as code')->get()->toArray();
            } else {
                $json = $this->model_localisation_zone->getZonesByCountryId($arr[$country_id]);
            }
        } else {
            $json = $this->model_localisation_zone->getZonesByCountryId(null);
        }

        return $this->response->json($json);
    }

    /**
     * Buyer下载顾客订单，生成CSV文件
     */
    public function downloadOrderFulfillment()
    {
        //13377 combo 下载
        set_time_limit(0);
        load()->language('account/customer_order_import');
        load()->language('account/customer_order');
        // 用户ID
        $customer_id = Customer()->getId();
        $country_id = Customer()->getCountryId();
        $order = Request('order', 'ASC');
        $sort = Request('sort', 'order_id');
        $filter_orderStatus = Request('filter_orderStatus', null);
        $filter_orderId = Request('filter_orderId', null);
        $filter_orderDate_from = Request('filter_orderDate_from', null);
        $filter_orderDate_to = Request('filter_orderDate_to', null);
        $filter_item_code = Request('filter_item_code', null);
        $filter_tracking_number = Request('filter_tracking_number', 2);
        $filterDeliveryStatus = $this->request->get('filter_delivery_status',-1);
        $filter_import_mode = Request('filter_import_mode', -1);
        $filter_cancel_not_applied_rma = Request('filter_cancel_not_applied_rma', -1);
        //过滤参数
        $param = array(
            'filter_orderStatus' => $filter_orderStatus,
            'filter_orderId' => trim($filter_orderId),
            'filter_orderDate_from' => $filter_orderDate_from,
            'filter_orderDate_to' => $filter_orderDate_to,
            'filter_tracking_number' => $filter_tracking_number,
            'filter_delivery_status' => $filterDeliveryStatus,
            'filter_item_code' => trim($filter_item_code),
            'filter_import_mode' => $filter_import_mode,
            'filter_cancel_not_applied_rma' => $filter_cancel_not_applied_rma,
            'customer_id' => $customer_id,
            'sort' => $sort,
            'order' => $order,
            'tracking_privilege' => $this->tracking_privilege,
            'delivery_type' => Customer()->isCollectionFromDomicile() ? 1 : 0,
        );
        $paramNew = $param;
        $results = [];
        $tmp = $this->model_account_customer_order->queryOrderNum($param, true);
        if ($tmp['idStr']) {
            $param = explode(',', trim($tmp['idStr'], ','));
            if (strlen($tmp['idStr']) == 102400) {
                if (substr($tmp['idStr'], -1) != ',') {
                    array_pop($param);//如果MySQL返回的字符串长度为102400,则最后一个订单ID可能被截断
                }
            }
            $results = $this->model_account_customer_order_import->getTrackingNumberInfoByOrderParam($param);
        }


        //12591 B2B记录各国别用户的操作时间
        $country = session('country', 'USA');
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        $time = dateFormat($fromZone, $toZone, date("YmdHis", time()), 'YmdHis');
        //12591 end
        $fileName = "SalesOrderManagement" . $time . ".csv";

        if ($country_id == AMERICAN_COUNTRY_ID) {
            $head = [
                'Sales Order ID',
                'Upload Time',
                'Item Code',
                'Quantity',
                'Sub-item Code',
                'Sub-item Quantity',
                'Shipping Recipient',
                'Shipping Address',
                'Tracking Number',
                'Delivery Status', //物流状态
                'Carrier Name',
                'Warehouse Code',
                'Warehouse Address',
                'Ship Date',
                //'ShipToService',
                'Checkout Time',
                'Order Status',
                'Platform',
            ];
        } else {
            $head = [
                'Sales Order ID',
                'Upload Time',
                'Item Code',
                'Quantity',
                'Sub-item Code',
                'Sub-item Quantity',
                'Shipping Recipient',
                'Shipping Address',
                'Tracking Number',
                'Carrier Name',
                'Ship Date',
                'Checkout Time',
                'Order Status',
                'Platform',
            ];
        }

        $results = $this->model_account_customer_order_import->getHomePickDownLoadInfos($results,$paramNew);

        //13377 B2B上comboSKU需要显示每一个子SKU对应的运单号
        if (isset($results) && !empty($results)) {
            if ($country_id != AMERICAN_COUNTRY_ID) {
                foreach ($results as $result) {

                    $content[] = [
                        "\t" . $result['order_id'],
                        $result['create_time'],
                        $result['sku'],
                        $result['line_qty'],
                        $result['child_sku'],
                        $result['all_qty'],
                        $result['shipping_recipient'],
                        $result['shipping_address'],
                        $result['tracking_number_deal'],
                        $result['carrier_name_deal'],
                        //$result['warehouse_code'],
                        //$result['warehouse_address'],
                        "\t" . $result['shipDeliveryDate_deal'],
                        $result['checkout_time'],
                        $result['status_name'],
                        $result['platform'],
                    ];
                }
            } else {
                foreach ($results as $result) {
                    if (isset($this->request->get['filter_tracking_number']) && $this->request->get['filter_tracking_number'] != 0) {
                        if (
                            ($this->tracking_privilege && $result['order_status'] == CustomerSalesOrderStatus::COMPLETED) ||
                            !$this->tracking_privilege
                        ) {


                        } else {
                            $result['tracking_number_deal'] = null;
                            $result['carrier_name_deal'] = null;
                            $result['shipDeliveryDate_deal'] = null;
                        }
                    } else {
                        $result['tracking_number_deal'] = null;
                        $result['carrier_name_deal'] = null;
                        $result['shipDeliveryDate_deal'] = null;
                    }

                    if ($filterDeliveryStatus > 0) {
                        if (empty($result['tracking_number_deal']) || $result['tracking_status_deal'] == 'N/A') { // 可能有运单号：order_tracking和facts表存的数据不一样，这样就下载不到数据
                            continue;
                        }
                    }

                    $content[] = [
                        "\t" . $result['order_id'],
                        $result['create_time'],
                        $result['sku'],
                        $result['line_qty'],
                        $result['child_sku'],
                        $result['all_qty'],
                        $result['shipping_recipient'],
                        $result['shipping_address'],
                        $result['tracking_number_deal'],
                        $result['tracking_status_deal'], //物流状态
                        $result['carrier_name_deal'],
                        $result['warehouse_code'],
                        $result['warehouse_address'],
                        "\t" . $result['shipDeliveryDate_deal'],
                        //strcasecmp(trim($result['ship_method']), 'ASR') == 0 ? 'ASR' : '',
                        $result['checkout_time'],
                        $result['status_name'],
                        $result['platform'],
                    ];

                }
            }
            //12591 B2B记录各国别用户的操作时间
            outputCsv($fileName, $head, $content, $this->session);
            //12591 end
        } else {
            //12591 B2B记录各国别用户的操作时间
            outputCsv($fileName, $head, null, $this->session);
            //12591 end
        }
    }

    /**
     * 格式化 walmart导入的数据格式
     * @param array $data
     * @return array
     * */
    private function formatWalmartData($data)
    {
        $retData = [];
        foreach ($data as $k => $v) {
            if (0 == $k) continue;
            if (count($data[0]) != count($v)) break;

            $temp = [
                'order_id' => trim($v[0]),
                'order' => trim($v[1]),
                'order_date' => trim($v[2]),
                'ship_by' => trim($v[3]),
                'ship_to_name' => trim($v[4]),
                'ship_to_address' => trim($v[5]),
                'ship_to_phone' => trim($v[6]),
                'store_id' => trim($v[7]),
                'ship_to_address1' => trim($v[8]),
                'ship_to_address2' => trim($v[9]),
                'city' => trim($v[10]),
                'state' => trim($v[11]),
                'zip' => trim($v[12]),
                'flids' => trim($v[13]),
                'ship_node' => trim($v[14]),
                'line' => trim($v[15]),
                'upc' => trim($v[16]),
                'platform_sku' => trim($v[17]),
                'status' => trim($v[18]),
                'item_description' => trim($v[19]),
                'qty' => intval($v[20]),
                'ship_to' => ucfirst(strtolower(trim($v[21]))),
                'shipping_method' => trim($v[22]),
                'requested_carrier_method' => trim($v[23]),
                'update_status' => trim($v[24]),
                'update_qty' => trim($v[25]),
                'carrier' => trim($v[26]),
                'tracking_number' => trim($v[27]),
                'package_asn' => trim($v[28]),
            ];

            foreach ($temp as $key => &$value) {
                $value = str_replace(chr(0xC2) . chr(0xA0), ' ', $value);
            }
            $retData[] = $temp;
        }

        return $retData;
    }

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws Exception
     */
    public function saveEuropeWayfairOrder()
    {
        load()->language('account/customer_order_import');
        load()->model('account/customer_order_import');
        load()->model('tool/csv');
        $customer_id = customer()->getId();
        $country_id = customer()->getCountryId();
        $importMode = request('importMode');
        $runId = request('runId');
        // 获取上传的文件
        $customer_order_file = $this->model_account_customer_order_import->getCustomerOrderFileByRunId($runId, $customer_id);
        //使用时需要使用临时文件夹中的地址，使用完成之后删除文件
        $file_path = StorageCloud::orderCsv()->getLocalTempPath($customer_order_file['file_path']);
        $csv_data = $this->model_tool_csv->readCsvLines($file_path);
        // 校验header数据
        $header_flag = $this->model_account_customer_order_import->verifyWayfairHeader($csv_data, $country_id);
        if(!$header_flag){
            // 上传的CSV内容格式与模板格式不符合！
            $json['error'] = $this->language->get('error_wayfair_file_content');
        }else{
            $res = $this->model_account_customer_order_import->verifyEuropeWayfairCsvByMapping($csv_data['values'], $customer_id, $country_id);
            if ($res['err']) {
                $json['error'] = $res['err'];
                //wayfair 中 warehouse 映射 和 item code 映射不对时，提供一个链接跳转
                if (isset($res['err_href'])) {
                    $json['err_href'] = $res['err_href'];
                }
            } else {
                //这里应该是wayfair全部正确的line
                $res = $this->model_account_customer_order_import->verifyEuropeWayFairCsvUpload($res['data'], $runId, $importMode, $country_id, $customer_id);
                if ($res !== true) {
                    $json['error'] = $res;
                } else {
                    $json['text'] = $this->language->get('text_order_upload_manifest');
                    $json['file_deal_wayfair_europe'] = url()->link('account/customer_order/wayfairUploadManifest', ['runId' => $runId ,'importMode' => $importMode]);
                }
            }
        }

        if (isset($json['file_deal'])) {
            //14091 Sales Order Management - Upload History表中加上传结果列
            $update_info = [
                'handle_status' => 1,
                'handle_message' => 'uploaded successfully.',
            ];

        } elseif (!isset($json['error'])) {
            //14091 Sales Order Management - Upload History表中加上传结果列
            $update_info = [
                'handle_status' => 1,
                'handle_message' => 'uploaded successfully.',
            ];
            $json['text'] = $this->language->get('text_order_processing');
            $json['next'] = url()->to(['account/customer_order/orderPurchase', 'runId' => $runId, 'importMode' => $importMode]);
        } else {
            //14091 Sales Order Management - Upload History表中加上传结果列
            $update_info = [
                'handle_status' => 0,
                'handle_message' => 'upload failed, ' . $json['error'],
            ];

        }
        $this->model_account_customer_order_import->updateUploadInfoStatus($runId, $customer_id, $update_info);
        StorageCloud::orderCsv()->deleteLocalTempFile($file_path);
        return $this->response->json($json);
    }

    /**
     * @throws Throwable
     */
    public function wayfairUploadManifest()
    {
        load()->model('account/customer_order_import');
        trim_strings($this->request->get);
        $get = $this->request->get;
        $importMode = $get['importMode'];
        $runId = $get['runId'];
        $customer_id = $this->customer->getId();
        //根据当前的order 和 manifest做出相对应的处理
        $data['list'] = $this->model_account_customer_order_import->getManifestListByRunId($runId, $customer_id, $importMode);
        $data['dropship_file_unlink'] = url()->to(['account/customer_order/dropshipFileUnlink']);
        $data['europe_wayfair_manifest_preserved'] = url()->to(['account/customer_order/europeWayfairManifestPreserved', 'runId' => $runId, 'importMode' => $importMode]);
        $data['show_url'] = url()->to(['account/customer_order/dropshipUploadFileBoxShow', 'runId' => $runId, 'importMode' => $importMode]);
        $data['app_version'] = APP_VERSION;
        $this->response->setOutput(load()->view('account/wayfair_upload_manifest', $data));

    }

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws Exception
     */
    public function europeWayfairManifestPreserved()
    {
        trim_strings($this->request->post);
        $posts = $this->request->post;
        trim_strings($this->request->get);
        $get = $this->request->get;
        $importMode = $get['importMode'];
        $runId = $get['runId'];
        $customer_id = $this->customer->getId();
        load()->model('account/customer_order_import');
        $ret = $this->model_account_customer_order_import->updateManifestFile($posts['manifest_common_label'], $customer_id);
        $json['error'] = 0;
        $json['msg'] = $ret['msg'];
        $json['show_url'] = url()->to(['account/customer_order/dropshipUploadFileBoxShow', 'runId' => $runId, 'importMode' => $importMode]);
        return $this->response->json($json);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function wayfairUploadManifestDeal()
    {
        load()->language('account/customer_order_import');
        $container_id = $this->request->post('container_id');
        //检测文件合法信息
        $json = [];
        if (isset($this->request->files['files']['name'])) {
            if (substr($this->request->files['files']['name'], -4) != '.pdf') {
                $json['error'] = $this->language->get('error_filetype');
            }

            if ($this->request->files['files']['error'] != UPLOAD_ERR_OK) {
                $json['error'] = $this->language->get('error_upload_' . $this->request->files['files']['error']);
            }
        } else {
            $json['error'] = $this->language->get('error_upload');
        }

        //创建文件夹
        $current_time = time();
        $dir_upload =  'dropshipPdf/manifest/' . date('Y-m-d', $current_time) . '/';
        if (!isset($json['error'])) {
            //放置文件
            $file_name = date('YmdHis', $current_time) . '_' . token(20) . '.pdf';
            $file_path = $dir_upload . $file_name;
            StorageCloud::storage()->writeFile(request()->filesBag->get('files'), $dir_upload, $file_name);
            //数据库保存数据 根据order_id 和 buyer_id run_id
            $fileData = [
                //"file_name" => $this->request->files['files']['name'],
                "file_name" => str_replace(' ', '', $this->request->files['files']['name']),
                "size" => $this->request->files['files']['size'],
                "file_path" => $file_path,
                'deal_file_path' => StorageCloud::storage()->getUrl($file_path, ['check-exist' => false]),
                'container_id' => $container_id,
                'create_user_name' => $this->customer->getId(),
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $this->orm->table('tb_sys_customer_sales_dropship_upload_file')->insertGetId($fileData);
            $fileData['real_bol_path'] = $fileData['deal_file_path'];
            $json['error'] = 0;
            $json['data'] = $fileData;

        }

        return $this->response->json($json);

    }

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws Exception
     */
    public function saveOrder()
    {
        load()->language('account/customer_order_import');
        load()->model('account/customer_order_import');
        load()->model('tool/excel');
        load()->model('tool/csv');
        $customer = $this->customer;
        $customer_id = $customer->getId();
        $country_id = $customer->getCountryId();
        $importMode = $this->request->query->get('importMode');
        $runId = $this->request->query->get('runId');
        $order_column_info = []; //验证是否有乱码
        $json = [];
        if ($this->country->isEuropeCountry($country_id)) {
            $isEurope = true;
        } else {
            $isEurope = false;
        }
        // 根据runId 和cust获取导入文件的信息
        $customer_order_file = $this->model_account_customer_order_import->getCustomerOrderFileByRunId($runId);
        //使用时需要使用临时文件夹中的地址，使用完成之后删除文件
        $filePath = StorageCloud::orderCsv()->getLocalTempPath($customer_order_file['file_path']);
        //4 dropship
        // 5 warfair
        // 6 common
        //  7 walmart
        //  8 Buyer Pick-up 自提货
        switch ($importMode) {
            case HomePickImportMode::IMPORT_MODE_WALMART:
            {
                //国别 针对于美国
                $excelData = $this->model_tool_excel->getExcelData($filePath);
                $excelHeader = [
                    'PO#',
                    'Order#',
                    'Order Date',
                    'Ship By',
                    'Customer Name',
                    'Customer Shipping Address',
                    'Customer Phone Number',
                    'Store Id',
                    'Ship to Address 1',
                    'Ship to Address 2',
                    'City',
                    'State',
                    'Zip',
                    'FLIDS',
                    'Ship Node',
                    'Line#',
                    'UPC',
                    'SKU',
                    'Status',
                    'Item Description',
                    'Qty',
                    'Ship To',
                    'Shipping Method',
                    'Requested Carrier Method',
                    'Update Status',
                    'Update Qty',
                    'Carrier',
                    'Tracking Number',
                    'Package ASN',
                ];
                if (isset($excelData[0]) && $excelData[0] == $excelHeader) {
                    $formatData = $this->formatWalmartData($excelData);
                    if (count($formatData) < 1) {
                        $json['error'] = $this->language->get('error_file_empty');
                        goto end;
                    }
                    $res = $this->model_account_customer_order_import->verifyWalmartData($formatData, $country_id);
                    if (!$res['flag']) {
                        $json['error'] = $res['err'];
                        goto end;
                    }
                    $initPretreatmentData = $this->model_account_customer_order_import->initPretreatmentWalmart($res['data']);
                    $this->cache->set($customer_id . '_' . $runId . '_dropship_undo', $initPretreatmentData['order_undo']);
                    $this->cache->set($customer_id . '_' . $runId . '_dropship_do', $initPretreatmentData['order_do']);
                    $validData = $initPretreatmentData['data'];
                    if ($initPretreatmentData['order_do']['amount'] == 0) {
                        $json['error'] = $this->language->get('error_all_order_sku');
                        goto end;
                    }
                    $ret = $this->model_account_customer_order_import->verifyWalmartUpload($validData, $runId);
                    if (!$ret['flag']) {
                        $json['error'] = $ret['err'];
                    } else {
                        $json['text'] = $this->language->get('text_order_processing');
                        $json['file_deal'] = url()->to(['account/customer_order/initPretreatmentDropshipTable', 'runId' => $runId, 'importMode' => $importMode]);
                    }
                    goto end;

                } else {

                    $json['error'] = $this->language->get('error_file_content');
                    goto end;
                }
                break;
            }
            case HomePickImportMode::US_OTHER:
            {
                if ($country_id == AMERICAN_COUNTRY_ID) {
                    //获取xls的文件数据
                    $excelData = $this->model_tool_excel->getExcelData($filePath);
                    //处理xls的内容
                    $data_ret = $this->model_account_customer_order_import->dealWithFileData($excelData, $runId, $importMode, $customer_id, $country_id);
                    if ($data_ret) {
                        $json['error'] = $data_ret;
                    }
                } else {
                    //国别 针对于非美国
                    //common order
                    $csvDatas = $this->model_tool_csv->readCsvLines($filePath, 1);
                    // 检查CSV的读取是否正确
                    // 不验证ltl
                    $csvHeader = [
                        'SalesPlatform',                    // 销售平台
                        'OrderId',                          // 订单号
                        'LineItemNumber',                   // 订单明细号
                        'OrderDate',                        // 订单时间
                        'BuyerBrand',                       // Buyer的品牌
                        'BuyerPlatformSku',                 // Buyer平台Sku
                        'B2BItemCode',                      // B2B平台商品Code
                        'BuyerSkuDescription',              // Buyer商品描述
                        'BuyerSkuCommercialValue',          // Buyer商品的商业价值/件
                        'BuyerSkuLink',                     // Buyer商品的购买链接
                        'ShipToQty',                        // 发货数量
                        'ShipToService',                    // 发货物流服务
                        'ShipToServiceLevel',               // 发货物流服务等级
                        'ShippedDate',                      // 希望发货日期
                        'ShipToAttachmentUrl',              // 发货附件链接地址
                        'ShipToName',                       // 收货人
                        'ShipToEmail',                      // 收货人邮箱
                        'ShipToPhone',                      // 收货人电话
                        'ShipToPostalCode',                 // 收货邮编
                        'ShipToAddressDetail',              // 收货详细地址
                        'ShipToCity',                       // 收货城市
                        'ShipToState',                      // 收货州/地区
                        'ShipToCountry',                    // 收货国家
                        'OrderComments'                     // 订单备注
                    ];

                    if (isset($csvDatas['keys']) && $csvDatas['keys'] == $csvHeader) {
                        // CSV读取到的订单数据
                        $csvDataValues = $csvDatas['values'];
                        if (count($csvDataValues) < 1) { // 无数据
                            $json['error'] = $this->language->get('error_file_empty');
                            goto end;
                        }
                        $verify = $this->model_account_customer_order_import->verifyCommonOrderCsvByMapping($csvDataValues);
                        if ($verify) {
                            $json['error'] = $verify;
                            goto end;
                        }
                        $res = $this->model_account_customer_order_import->verifyCommonOrderCsvUpload($csvDataValues, $runId, $country_id);
                        if ($res !== true) {
                            $json['error'] = $res;
                        }
                    } else {
                        // 上传的CSV内容格式与模板格式不符合！
                        $json['error'] = $this->language->get('error_file_content');
                        goto end;
                    }

                }
                break;
            }
            case HomePickImportMode::IMPORT_MODE_WAYFAIR:
            {
                if ($isEurope) {
                    return $this->saveEuropeWayfairOrder();
                }
                //国别 针对于美国
                //wayFair  有需要bol的大件货 和 普通订单的导入
                $csvDatas = $this->model_tool_csv->readCsvLines($filePath);
                $csvHeader = [
                    'Warehouse Name',            // 发货仓库名称
                    'Store Name',                // 销售平台 SalesPlatform原
                    'PO Number',                 // 订单号
                    'PO Date',                   //
                    'Must Ship By',              //
                    'Backorder Date',            //
                    'Order Status',              // 订单状态
                    'Item Number',               // sku product中 sku B2BItemCode
                    'Item Name',                 // product name
                    'Quantity',                  // 数量  ShipToQty
                    'Wholesale Price',           // BuyerSkuCommercialValue
                    'Ship Method',               // 装运方法
                    'Carrier Name',              // 物流名称
                    'Shipping Account Number',   // 只保存在数据库，不在任何地方展示
                    'Ship To Name',              // 收货方名称
                    'Ship To Address',           // 地址1
                    'Ship To Address 2',         // 地址2
                    'Ship To City',              // 收货方城市
                    'Ship To State',             // 收货州 （翻译）
                    'Ship To Zip',               // 收货方邮政编码
                    'Ship To Phone',             // 手机号码
                    'Inventory at PO Time',
                    'Inventory Send Date',
                    'Ship Speed',
                    'PO Date & Time',
                    'Registered Timestamp',
                    'Customization Text',
                    'Event Name',
                    'Event ID',
                    'Event Start Date',
                    'Event End Date',
                    'Event Type',
                    'Backorder Reason',
                    'Original Product ID',
                    'Original Product Name',
                    'Event Inventory Source',
                    'Packing Slip URL',
                    'Tracking Number',                // Tracking Number不必填
                    'Ready for Pickup Date',
                    'SKU',
                    'Destination Country',       // ShipToCountry 收货国
                    'Depot ID',
                    'Depot Name',
                    'Wholesale Event Source',
                    'Wholesale Event Store Source',
                    'B2BOrder',
                    'Composite Wood Product',
                    'Sales Channel'
                ];
                if (isset($csvDatas['keys']) && $csvDatas['keys'] == $csvHeader) {
                    $csvDataValues = $csvDatas['values'];
                    if (count($csvDataValues) < 1) {
                        $json['error'] = $this->language->get('error_upload_no_data');
                        goto end;
                    }
                    // 检查CSV的读取是否正确
                    $res = $this->model_account_customer_order_import->verifyWayfairCsvByMapping($csvDataValues, $country_id);
                    if ($res['err']) {
                        $json['error'] = $res['err'];
                        //wayfair 中 warehouse 映射 和 item code 映射不对时，提供一个链接跳转
                        if (isset($res['err_href'])) {
                            $json['err_href'] = $res['err_href'];
                        }
                        goto end;
                    }

                    $initPretreatmentData = $this->model_account_customer_order_import->initPretreatmentWayFairCsv($res['data'], $country_id);
                    $this->cache->set($customer_id . '_' . $runId . '_dropship_undo', $initPretreatmentData['order_undo']);
                    $this->cache->set($customer_id . '_' . $runId . '_dropship_do', $initPretreatmentData['order_do']);
                    $validData = $initPretreatmentData['data'];

                    $res = $this->model_account_customer_order_import->verifyWayFairCsvUpload($validData, $runId, $importMode);
                    if ($res !== true) {
                        $json['error'] = $res;

                    } else {
                        $json['text'] = $this->language->get('text_order_processing');
                        $json['file_deal'] = url()->to(['account/customer_order/initPretreatmentDropshipTable', 'runId' => $runId, 'importMode' => $importMode]);
                    }

                    goto end;

                } else {
                    $json['error'] = $this->language->get('error_file_content');
                    goto end;
                }
                break;
            }
            case HomePickImportMode::IMPORT_MODE_AMAZON:
            {
                //获取csv数据
                //这里以后会根据国别拆分到底是使用ups 还是arrow 13846
                $csvDatas = $this->model_tool_csv->readCsvLines($filePath);
                //$csvHeader[0] = 'Order ID';
                if (in_array($country_id, [HomePickUploadType::BRITAIN_COUNTRY_ID, AMERICAN_COUNTRY_ID])) { //美国 英国  (英国和美国的模板使用一样的了)
                    //美国用户直接分成三个部分
                    //第一个 dropship
                    $csvHeader = [
                        ltrim('﻿Order ID', chr(0xEF) . chr(0xBB) . chr(0xBF)),                    // 订单号
                        'Order Status',                // 订单状态
                        'Warehouse Code',              // 发货仓库编码
                        'Order Place Date',            // 订单地日期
                        'Required Ship Date',          // 要求发货日期
                        'Ship Method',                 // 装运方法
                        'Ship Method Code',            // 装运方法编号
                        'Ship To Name',                // 收货方名称
                        'Ship To Address Line 1',      // 地址1
                        'Ship To Address Line 2',      // 地址2
                        'Ship To Address Line 3',      // 地址3
                        'Ship To City',                // 收货方城市
                        'Ship To State',               // 收货州 （翻译）
                        'Ship To ZIP Code',            // 收货方邮政编码
                        'Ship To Country',             // 收货国
                        'Phone Number',                // 手机号码
                        'Is it Gift?',                 // 是否是礼物
                        'Item Cost',                   // 产品价格
                        'SKU',                         // sku product中 sku
                        'ASIN',                        //
                        'Item Title',                  // 产品标题
                        'Item Quantity',               // 产品数量
                        'Gift Message',                // 礼品留言
                        'Tracking ID',                 // 运单号
                        'Shipped Date'                 // 发货日期
                    ];

                }

                if (isset($csvDatas['keys']) && $csvDatas['keys'] == $csvHeader) {
                    //header头部信息通过 验证其他校验
                    // CSV读取到的订单数据
                    $csvDataValues = $csvDatas['values'];
                    if (count($csvDataValues) < 1) {
                        $json['error'] = $this->language->get('error_upload_no_data');
                        goto end;
                    }
                    //处理获取的数据，主要判断导入的订单的sku是否符合我们的需求
                    $res = $this->model_account_customer_order_import->verifyDropshipCsvByMapping($csvDataValues, $country_id, customer()->isCollectionFromDomicile());
                    if ($res['err']) {
                        $json['error'] = $res['err'];
                        //dropship 中 warehouse 映射 提供一个链接跳转
                        if (isset($res['err_href'])) {
                            $json['err_href'] = $res['err_href'];
                        }
                        goto end;
                    }

                    $initPretreatmentData = $this->model_account_customer_order_import->initPretreatmentDropshipCsv($csvDataValues, $country_id);
                    $this->cache->set($customer_id . '_' . $runId . '_dropship_undo', $initPretreatmentData['order_undo']);
                    $this->cache->set($customer_id . '_' . $runId . '_dropship_do', $initPretreatmentData['order_do']);
                    $validData = $initPretreatmentData['data'];

                    $res = $this->model_account_customer_order_import->verifyDropshipCsvUpload($validData, $runId, $importMode);
                    if ($res !== true) {

                        $json['error'] = $res;

                    } else {
                        $json['text'] = $this->language->get('text_order_processing');
                        $json['file_deal'] = url()->to(['account/customer_order/initPretreatmentDropshipTable', 'runId' => $runId, 'importMode' => $importMode]);
                    }

                    goto end;
                } else {
                    $json['error'] = $this->language->get('error_file_content');
                    goto end;
                }

                break;
            }
            case HomePickImportMode::IMPORT_MODE_BUYER_PICK_UP: //8 Buyer Pick-up 自提货
            {
                //获取xls的文件数据
                $excelData = $this->model_tool_excel->getExcelData($filePath);
                //处理xls的内容
                $data_ret = $this->dealBuyerPickUpFileData($excelData, $runId, $importMode, $customer_id, $country_id);
                if ($data_ret) {
                    $json['error'] = $data_ret;
                }
                break;
            }
        }
        StorageCloud::orderCsv()->deleteLocalTempFile($filePath);
        end:
        if (isset($json['file_deal'])) {
            //14091 Sales Order Management - Upload History表中加上传结果列
            $update_info = [
                'handle_status' => 1,
                'handle_message' => 'uploaded successfully.',
            ];

        } elseif (!isset($json['error'])) {
            //14091 Sales Order Management - Upload History表中加上传结果列
            $update_info = [
                'handle_status' => 1,
                'handle_message' => 'uploaded successfully.',
            ];
            $json['text'] = $this->language->get('text_order_processing');
            if ($country_id == AMERICAN_COUNTRY_ID && $importMode != HomePickImportMode::IMPORT_MODE_BUYER_PICK_UP) {
                $json['other_next'] = url()->to(['account/customer_order/dropshipUploadFileBoxShow', 'runId' => $runId, 'importMode' => $importMode]);
            } else {
                $salesOrderList = app(CustomerSalesOrderRepository::class)->getSalesOrderListByRunId($customer_id, $runId)->toArray();
                $salesOrderIds = implode(',', array_column($salesOrderList, 'id'));
                $json['dropship_order_match'] = $this->url->link('sales_order/match', ['runId' => $runId, 'importMode' => $importMode]);
                $json['orderIds'] = $salesOrderIds;
                if ($importMode == HomePickImportMode::IMPORT_MODE_BUYER_PICK_UP) {//自提货
                    $json['confirm_next'] = $this->url->link('account/customer_order/confirmUploadFileBoxShow', ['runId' => $runId, 'importMode' => $importMode]);
                } else {
                    $json['next'] = url()->to(['account/customer_order/orderPurchase', 'runId' => $runId, 'importMode' => $importMode]);
                }
            }



        } else {
            //14091 Sales Order Management - Upload History表中加上传结果列
            $update_info = [
                'handle_status' => 0,
                'handle_message' => 'upload failed, ' . $json['error'],
            ];

        }
        $this->model_account_customer_order_import->updateUploadInfoStatus($runId, $customer_id, $update_info);
        $this->response->headers->set('Content-Type', 'application/json');
        return $this->response->json($json);

    }

    /**
     * 处理与保存自提货校验上传数据
     * @param $excelData
     * @param $runId
     * @param int $importMode
     * @param int $customerId
     * @param int $countryId
     * @return array|string
     * @throws Exception
     */
    public function dealBuyerPickUpFileData($excelData, $runId, $importMode, $customerId, $countryId)
    {
        $this->load->model('account/customer_order_import');
        //校验上传数据
        $verifyResult = $this->verifyBuyerPickUpFileData($excelData, $countryId, $customerId);
        if (!is_array($verifyResult)) {
           return  $verifyResult;
        }
        //写入数据
        $column_ret = app(SalesOrderService::class)->saveBuyerPickUpOrder($verifyResult, $importMode, $runId, $customerId);
        if ($column_ret !== true) {
            return 'something wrong happened. Please try it again.';
        }
        return '';
    }

    /**
     * 自提货校验上传数据
     * @param array $data
     * @param int $countryId
     * @param int $customerId
     * @return array|string
     * @throws Exception
     */
    public function verifyBuyerPickUpFileData(array $data, int $countryId, int $customerId)
    {
        $this->load->model('account/mapping_warehouse');
        $this->load->model('account/customer_order_import');
        $excel_header = [
            '*Sales Order ID',//订单号
            '*B2B Item Code',//sku
            '*Quantity',//数量
            '*B2B warehouse code',//仓库
            '*Requested Pick-up Date',//申请取货日期
            '*Pick-up Person\'s Name',//取货人姓名
            '*Pick-up Person\'s Contact Number',//取货人联系电话
            '*Require Palletized Service?',//是否需要打托服务
            '*Accept Warehouse Location Adjustment?',//是否接受取货仓库调剂
        ];
        // 首行
        if (!isset($data[0]) || $data[0] != $excel_header) {
            return $this->language->get('error_file_content');
        }
        // 行数
        if (count($data) < 2) {
            return $this->language->get('error_file_empty');
        }
        $order_sku_key = [];
        $order_warehouse_code = [];
        $order_warehouse_id = [];
        $order_apply_date = [];
        $order_need_tray = [];
        $order_can_adjust = [];
        $lineTransferSku = [];
        $data = $this->model_account_customer_order_import->formatFileData($data);
        foreach ($data as $key => &$value) {
            //Sales Order ID：数字、字母、下划线、连字符
            $value['order_id'] = trim($value['Sales Order ID']);
            if (strlen($value['order_id']) > 20 || !preg_match('/^[_0-9-a-zA-Z]{1,20}$/i', $value['order_id']) || $value['order_id'] == '') {
                return "Line" . ($key + 2) . ", [Sales Order ID] must be between 1 and 20 characters long and must only contain letters, numbers, - or _.";
            }
            //B2B Item Code为空
            $value['item_code'] = strtoupper(trim($value['B2B Item Code']));
            if (trim($value['item_code']) == '') {
                return "Line" . ($key + 2) . ", [B2B Item Code] can not be left blank.";
            }
            //一个订单中不能有相同的B2B Item Code
            if (isset($order_sku_key[$value['order_id']][$value['item_code']])) {
                return 'Line' . ($key + 2) . ", Duplicate B2B Item Code '" . $value['item_code'] . "' within Sales Order ID '" . $value['order_id'] . "'.";
            }
            if (!isset($order_sku_key[$value['order_id']][$value['item_code']])) {
                //B2B Item Code当前国别下不存在
                if (!$this->model_account_customer_order_import->judgeSkuIsExist($value['item_code'], $countryId)) {
                    return 'Line' . ($key + 2) . ", [B2B Item Code] Item '" . $value['B2B Item Code'] . "' does not exist.";
                }
                //排序giga与joy商品
                if (app(ProductRepository::class)->isGigaOrJoyProduct($value['item_code'])) {
                    return 'Line' . ($key + 2) . ", [B2B Item Code] The itemcode '" . $value['B2B Item Code'] . "' is not available for buyer pick-up currently. If you have any questions or concerns, please contact the online customer service.";
                }
                $lineTransferSku[$key + 2] = $value['item_code'];
                $order_sku_key[$value['order_id']][$value['item_code']] = $key + 2;
            }
            //数量
            $value['quantity'] = trim($value['Quantity']);
            if (!preg_match('/^[1-9][0-9]*$/', $value['quantity']) || $value['quantity'] == '') {
                return 'Line' . ($key + 2) . ", [Quantity] is in an incorrect format.";
            }
            //warehouse_code
            $value['warehouse_code'] = trim($value['B2B warehouse code']);
            if ($value['warehouse_code'] == '') {
                return "Line" . ($key + 2) . ", [B2B warehouse code] can not be left blank.";
            }
            //一个订单中只能对应一个B2B warehouse code
            if (isset($order_warehouse_code[$value['order_id']]) && $order_warehouse_code[$value['order_id']] != strtoupper($value['warehouse_code'])) {
                return 'Line' . ($key + 2) . ", Sales Order ID: '" . $value['order_id'] . "' one order is not allowed to have different B2B Warehouse Code.";
            }
            //校验warehouse_code
            if (!isset($order_warehouse_code[$value['order_id']])) {
                $value['warehouse_id'] = $this->model_account_mapping_warehouse->getWarehouseIsExist($value['warehouse_code'], $countryId);
                $order_warehouse_id[$value['order_id']] = $value['warehouse_id'];
                if (!$value['warehouse_id']) {
                    return 'Line ' . ($key + 2) . ", [B2B warehouse code] '" . $value['B2B warehouse code'] . "' does not exist.";
                }
                //排除区域仓库
                if (app(WarehouseRepository::class)->checkIsVirtualWarehouse($value['warehouse_id'])) {
                    return 'Line' . ($key + 2) . ", [B2B warehouse code] The warehouse '" . $value['warehouse_code'] . "' is not available for buyer pick-up currently. If you have any questions or concerns, please contact the online customer service.";
                }
                //排除joy
                if (app(WarehouseRepository::class)->checkWarehouseSellerIsJoy($value['warehouse_id'])) {
                    return 'Line' . ($key + 2) . ", [B2B warehouse code] The warehouse '" . $value['warehouse_code'] . "' is not available for buyer pick-up currently. If you have any questions or concerns, please contact the online customer service.";
                }
                //排除GIGA Onsite
                if (app(WarehouseRepository::class)->checkWarehouseSellerType($value['warehouse_id'], SellerType::GIGA_ON_SITE)) {
                    return 'Line' . ($key + 2) . ", [B2B warehouse code] The warehouse '" . $value['warehouse_code'] . "' is not available for buyer pick-up currently. If you have any questions or concerns, please contact the online customer service.";
                }
                $order_warehouse_code[$value['order_id']] = strtoupper($value['warehouse_code']);
            }
            //申请取货日期
            $value['apply_date'] = trim($value['Requested Pick-up Date']);
            //同一个订单，要同一个取货日期
            if (isset($order_apply_date[$value['order_id']]) && $order_apply_date[$value['order_id']] != $value['apply_date']) {
                return "The Sales Order ID: '" . $value['order_id'] . "' is related with multiple [Requested Pick-up Date].";
            }
            //校验取货日期
            if (!isset($order_apply_date[$value['order_id']])) {
                if (!$this->model_account_customer_order_import->isDateValid($value['apply_date'])) {
                    return 'Line' . ($key + 2) . ", [Requested Pick-up Date] The Requested Pick-up Date is in an incorrect format. It should be in MM/DD/YYYY format only.";
                }
                //工作日
                if (app(ReceiptRepository::class)->isHoliday(Carbon::parse($value['apply_date'])->toDateString())) {
                    return 'Line' . ($key + 2) . ", [Requested Pick-up Date] The pick-up is not available on '" . $value['apply_date'] . "' since it is not a business day.";
                }
                //5个工作日后
                $next_work_date = app(ReceiptRepository::class)->getWorkDate(Carbon::now()->toDateString(), 5);
                if (strtotime($value['apply_date']) < strtotime($next_work_date)) {
                    return 'Line' . ($key + 2) . ", [Requested Pick-up Date] The Requested Pick-up Date filled in the uploaded file does not meet the requirement. At least 5 business days are required for the warehouse to prepare the order.";
                }
                //一个月后
                if (Carbon::now()->addMonthNoOverflow(1)->lte(Carbon::parse($value['apply_date']))) {
                    return 'Line' . ($key + 2) . ", [Requested Pick-up Date] '" . $value['apply_date'] . "' is not eligible since the date cannot be one month later than the current day.";
                }
                $order_apply_date[$value['order_id']] = $value['apply_date'];
            }
            //取货人姓名
            $value['user_name'] = $value['Pick-up Person\'s Name'];
            if ($value['user_name'] == '' || strlen($value['user_name']) > 90) {
                return 'Line' . ($key + 2) . ", [Pick-up Person's Name] must be between 1 and 90 characters.";
            }
            //取货人联系电话
            $value['user_phone'] = $value['Pick-up Person\'s Contact Number'];
            if ($value['user_phone'] == '' || strlen($value['user_phone']) > 45) {
                return 'Line' . ($key + 2) . ", [Pick-up Person's Contact Number] must be between 1 and 45 characters.";
            }
            //打托服务
            $value['need_tray'] = trim(strtoupper($value['Require Palletized Service?']));
            if (!in_array($value['need_tray'], ['YES', 'NO'])) {
                return 'Line' . ($key + 2) . ", [Require Palletized Service] The filled content for '" . $value['need_tray'] . "' is incorrect. It should be Yes or No.";
            }
            //同一笔订单是否接受取货打托服务不一致的
            if (isset($order_need_tray[$value['order_id']]) && $order_need_tray[$value['order_id']] != $value['need_tray']) {
                return "The Sales Order ID: '" . $value['order_id'] . "' is related with multiple [Require Palletized Service].";
            }
            if (!isset($order_need_tray[$value['order_id']])) {
                $order_need_tray[$value['order_id']] = $value['need_tray'];
            }
            //仓库调剂
            $value['can_adjust'] = trim(strtoupper($value['Accept Warehouse Location Adjustment?']));
            if (!in_array($value['can_adjust'], ['YES', 'NO'])) {
                return 'Line' . ($key + 2) . ", [Accept Warehouse Location Adjustment] The filled content for '" . $value['can_adjust'] . "' is incorrect. It should be Yes or No.";
            }
            //同一笔订单是否接受取货仓库调剂不一致的
            if (isset($order_can_adjust[$value['order_id']]) && $order_can_adjust[$value['order_id']] != $value['can_adjust']) {
                return "The Sales Order ID: '" . $value['order_id'] . "' is related with multiple [Accept Warehouse Location Adjustment].";
            }
            if (!isset($order_can_adjust[$value['order_id']])) {
                $order_can_adjust[$value['order_id']] = $value['can_adjust'];
            }
            //Sales Order ID是否已经存在
            $salesOrders = CustomerSalesOrder::query()->where('order_id', $value['order_id'])->get(['order_id'])->pluck('order_id')->all();
            if (in_array($value['order_id'], $salesOrders)) {
                return "Line" . ($key + 2) . ", [Sales Order ID] is already exist ,please check the uploaded file.";
            }
            $value['apply_date'] = date('Y-m-d', strtotime($value['apply_date']));
            $value['need_tray'] = $value['need_tray'] == 'YES' ? 1 : 0;
            $value['can_adjust'] = $value['can_adjust'] == 'YES' ? 1 : 0;
            $value['warehouse_id'] = $order_warehouse_id[$value['order_id']];
        }
        $verifyRet = app(salesOrderSkuValidate::class)->withSkus(array_values($lineTransferSku))->validateSkus();
        if (!$verifyRet['code']) {
            return 'Line' . array_search($verifyRet['errorSku'], $lineTransferSku) . ", [B2B Item Code] '" . $verifyRet['errorSku'] . "' is a service that cannot be shipped as a regular item. Please check it.";
        }
        return $data;
    }

    /**
     * 自提货导单上传弹窗信息确认
     * @return JsonResponse
     * @throws Exception
     */
    public function confirmUploadFileBoxShow()
    {
        $this->load->model('catalog/product');
        $runId = $this->request->get('runId');
        $importMode = $this->request->get('importMode');
        $customerId = $this->customer->getId();
        $json = app(CustomerSalesOrderRepository::class)->getPickUpUploadInfo($runId, $customerId, $importMode);
        if (!$json) {
            return $this->jsonFailed();
        }
        foreach ($json as &$val) {
            $tag_array = $this->model_catalog_product->getProductSpecificTagByOrderLineId($val->id);
            $tags = [];
            foreach ($tag_array as $tag) {
                if (isset($tag['origin_icon']) && !empty($tag['origin_icon'])) {
                    //采用唯一图片  LTL.jpg  part.jpg  combo-img.png
                    $img_url = $this->model_tool_image->getOriginImageProductTags($tag['origin_icon']);
                    $tags[] = '<img data-toggle="tooltip" class="' . $tag['class_style'] . '" title="' . $tag['description'] . '" style="padding-left: 1px" src="' . $img_url . '">';
                }
            }
            $val->tags = $tags;
        }
        return $this->jsonSuccess($json);
    }

    /**
     * 根据销售订单id获取对应信息
     * @return JsonResponse
     * @throws Exception
     */
    public function getBuyerPickUpOrderInfoByOrderId()
    {
        $this->load->model('catalog/product');
        $id = $this->request->get('id');
        $json = app(CustomerSalesOrderRepository::class)->getPickUpOrderInfoByOrderId($id);
        if (!$json) {
            return $this->jsonFailed();
        }
        foreach ($json->lines as &$val) {
            $tag_array = $this->model_catalog_product->getProductSpecificTagByOrderLineId($val->id);
            $tags = [];
            foreach ($tag_array as $tag) {
                if (isset($tag['origin_icon']) && !empty($tag['origin_icon'])) {
                    //采用唯一图片  LTL.jpg  part.jpg  combo-img.png
                    $img_url = $this->model_tool_image->getOriginImageProductTags($tag['origin_icon']);
                    $tags[] = '<img data-toggle="tooltip" class="' . $tag['class_style'] . '" title="' . $tag['description'] . '" style="padding-left: 1px" src="' . $img_url . '">';
                }
            }
            $val->tags = $tags;
        }
        //5个工作日后
        $nextWorkDate = app(ReceiptRepository::class)->getWorkDate(Carbon::now()->toDateString(), 5);
        //1个月
        $endDate = Carbon::now()->addMonth(1)->toDateString();
        $period = CarbonPeriod::create(Carbon::now()->toDateString(), $endDate);
        $unavailableDates = [];
        foreach ($period as $date) {
            //周末与节假日、5个工作日内
            if (app(ReceiptRepository::class)->isHoliday($date->format('Y-m-d')) || strtotime($date) < strtotime($nextWorkDate)) {
                array_push($unavailableDates, $date->format('Y-m-d'));
            }
        }
        $json['date_control'] = [
            'beginDate' => $nextWorkDate,
            'endDate' => $endDate,
            'unavailableDates' => $unavailableDates,
        ];
        return $this->jsonSuccess($json);
    }

    /**
     * 修改自提货取货信息
     * @return JsonResponse
     * @throws Exception
     */
    public function editPickingInfoHandle()
    {
        $this->load->model("account/customer_order");
        $this->load->model('account/mapping_warehouse');
        $this->load->model('account/customer_order_import');
        $post = $this->request->post();
        if (empty(trim($post['sales_order_id'])) || !isset($post['sales_order_id'])) {
            return $this->jsonFailed('The modification has failed. You may try again later.');
        }
        //warehouse_code
        if (empty(trim($post['warehouse_code'])) || !isset($post['warehouse_code'])) {
            return $this->jsonFailed("[Requested Pick-up Warehouse] can not be left blank.");
        }
        $post['warehouse_id'] = $this->model_account_mapping_warehouse->getWarehouseIsExist(trim($post['warehouse_code']), Customer()->getCountryId());
        if (!$post['warehouse_id']) {
            return $this->jsonFailed("[Requested Pick-up Warehouse] '" . $post['warehouse_code'] . "' does not exist.");
        }
        //排除GIGA Onsite、区域仓库、joy
        if (app(WarehouseRepository::class)->checkIsVirtualWarehouse($post['warehouse_id'])
            || app(WarehouseRepository::class)->checkWarehouseSellerIsJoy($post['warehouse_id'])
            || app(WarehouseRepository::class)->checkWarehouseSellerType($post['warehouse_id'], SellerType::GIGA_ON_SITE)) {
            return $this->jsonFailed("[Requested Pick-up Warehouse] The warehouse '" . $post['warehouse_code'] . "' is not available for buyer pick-up currently. If you have any questions or concerns, please contact the online customer service.");
        }
        //申请取货日期
        if (!$this->model_account_customer_order_import->isDateValid(trim($post['apply_date']), ['Y-m-d'])) {
            return $this->jsonFailed("[Requested Pick-up Date] The Requested Pick-up Date is in an incorrect format. It should be in YYYY-MM-DD format only.");
        }
        //工作日
        if (app(ReceiptRepository::class)->isHoliday(trim($post['apply_date']))) {
            return $this->jsonFailed("[Requested Pick-up Date] The pick-up is not available on '" . $post['apply_date'] . "' since it is not a business day.");
        }
        //5个工作日后
        $next_work_date = app(ReceiptRepository::class)->getWorkDate(Carbon::now()->toDateString(), 5);
        if (strtotime(trim($post['apply_date'])) < strtotime($next_work_date)) {
            return $this->jsonFailed('[Requested Pick-up Date] The Requested Pick-up Date filled in the uploaded file does not meet the requirement. At least 5 business days are required for the warehouse to prepare the order.');
        }
        //一个月后
        if (Carbon::now()->addMonthNoOverflow(1)->lte(Carbon::parse(trim($post['apply_date'])))) {
            return $this->jsonFailed("[Requested Pick-up Date] '" . $post['apply_date'] . "' is not eligible since the date cannot be one month later than the current day.");
        }
        //取货人姓名
        $post['user_name'] = $post['user_name'] ? htmlspecialchars_decode(trim($post['user_name'])) : '';
        if (empty($post['user_name']) || !isset($post['user_name']) || strlen($post['user_name']) > 90) {
            return $this->jsonFailed("[Pick-up Person's Name] must be between 1 and 90 characters.");
        }
        //取货人联系电话
        $post['user_phone'] = $post['user_phone'] ? htmlspecialchars_decode(trim($post['user_phone'])) : '';
        if (empty($post['user_phone']) || !isset($post['user_phone']) || strlen($post['user_phone']) > 45) {
            return $this->jsonFailed("[Pick-up Person's Contact Number] must be between 1 and 45 characters.");
        }
        //打托服务
        if (!in_array(trim($post['need_tray']), ['1', '0'])) {
            return $this->jsonFailed("[Require Palletized Service] The filled content for '" . $post['need_tray'] . "' is incorrect. It should be Yes or No.");
        }
        //仓库调剂
        if (!in_array(trim($post['can_adjust']), ['1', '0'])) {
            return $this->jsonFailed("[Accept Warehouse Location Adjustment] The filled content for '" . $post['can_adjust'] . "' is incorrect. It should be Yes or No.");
        }
        //new order 、pending charges 、BP 状态可修改
        $order_status = CustomerSalesOrder::query()->where('id', $post['sales_order_id'])->value('order_status');
        if (!in_array($order_status, [CustomerSalesOrderStatus::TO_BE_PAID, CustomerSalesOrderStatus::BEING_PROCESSED, CustomerSalesOrderStatus::PENDING_CHARGES])) {
            return $this->jsonFailed('The modification has failed. You may try again later.');
        }
        //正在同步不能修改
        $is_syncing = $this->model_account_customer_order->checkOrderIsSyncing($post['sales_order_id']);
        if ($is_syncing) {
            return $this->jsonFailed('This order is performing an order synchronization task. Please try this request again later.');
        }
        //已同步不能修改
        $isExportInfo = app(CustomerSalesOrderRepository::class)->calculateSalesOrderIsExportedNumber($post['sales_order_id']);
        if ($isExportInfo['is_export_number'] > 0) {
            return $this->jsonFailed('The requested pick-up information is unable to be modified since it has been synchronized to the warehouse.');
        }
        //修改
        CustomerSalesOrderPickUp::query()->where('sales_order_id', trim($post['sales_order_id']))->update([
            'warehouse_id' => trim($post['warehouse_id']),
            'apply_date' => date('Y-m-d', strtotime(trim($post['apply_date']))),
            'user_phone' => $post['user_phone'],
            'user_name' => $post['user_name'],
            'need_tray' => trim($post['need_tray']) ?? 0,
            'can_adjust' => trim($post['can_adjust']) ?? 0,
            'update_time' => Carbon::now(),
        ]);
        return $this->jsonSuccess([], 'The modification is successful.');
    }

    /**
     * 自提货--确认取货信息
     * @return JsonResponse
     * @throws Throwable
     */
    public function confirmPickingInfoHandle()
    {
        $salesOrderId = $this->request->post('sales_order_id');
        if (empty($salesOrderId) || !isset($salesOrderId)) {
            return $this->jsonFailed('Failed to confirm the pick-up information. You may try again later.');
        }
        //判断是否可确认
        $pickUpInfoChange = CustomerSalesOrderPickUpLineChange::query()->where('sales_order_id', $salesOrderId)->where('is_buyer_accept', YesNoEnum::NO)->orderByDesc('id')->first();
        if (!$pickUpInfoChange) {
            return $this->jsonFailed('Failed to confirm the pick-up information. You may try again later.');
        }
        $pickup = $pickUpInfoChange->pickUp;
        $salesOrder = $pickUpInfoChange->salesOrder;
        if ($pickup->pick_up_status != CustomerSalesOrderPickUpStatus::PICK_UP_INFO_TBC || $salesOrder->order_status != CustomerSalesOrderStatus::BEING_PROCESSED) {
            return $this->jsonFailed('Failed to confirm the pick-up information. You may try again later.');
        }
        try {
            dbTransaction(function () use ($pickup, $salesOrderId, $pickUpInfoChange) {
                $storePickUp = json_decode($pickUpInfoChange->store_pick_up_json, true);
                $originPickUp = json_decode($pickUpInfoChange->origin_pick_up_json, true);
                // 取货信息变更
                if ($storePickUp['applyDate'] != $originPickUp['applyDate']) {
                    $pickup->apply_date = $storePickUp['applyDate'];
                }
                if ($storePickUp['warehouseId'] != $originPickUp['warehouseId']) {
                    $pickup->warehouse_id = $storePickUp['warehouseId'];
                }
                $pickup->save();
                //部分分单调整
                if ($storePickUp['lines'] != $originPickUp['lines']) {
                    app(SalesOrderService::class)->adjustLines($salesOrderId, $storePickUp['lines']);
                }
                //生成 bol 文件
                $is = app(SalesOrderPickUpService::class)->generateBOL($salesOrderId);
                if (!$is) {
                    throw new Exception('bol generate error');
                }
            });
        } catch (Exception $e) {
            Logger::error('自提货--确认取货信息-修改数据失败');
            Logger::error($e);
            return $this->jsonFailed('Failed to confirm the pick-up information. You may try again later.');
        }
        try {
            //通过 YZCM 将 BOL 文件发给 WOS
            $result = app(SalesOrderApi::class)->sendPickUpBolToOmd($salesOrderId);
            if ($result) {
                //标记buyer 接受后成功通知仓库
                $pickUpInfoChange->is_notify_store = YesNoEnum::YES;
            }
        } catch (Exception $e) {
            Logger::error('自提货--确认取货信息-发送BOL失败');
            Logger::error($e);
        }
        //标记buyer接受
        $pickUpInfoChange->is_buyer_accept = YesNoEnum::YES;
        $pickUpInfoChange->update_time = date('Y-m-d H:i:s', time());
        $pickUpInfoChange->save();
        return $this->jsonSuccess([], 'Confirm successfully');
    }

    /**
     * [initPretreatmentDropshipTable description] 12406 增加流程
     * @throws Throwable
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function initPretreatmentDropshipTable()
    {
        load()->language('account/customer_order_import');
        $customer_id = $this->customer->getId();
        $runId = $this->request->get('runId', '');
        $importMode = $this->request->get('importMode');
        $data['order_undo'] = $this->cache->get($customer_id . '_' . $runId . '_dropship_undo');
        $data['order_do'] = $this->cache->get($customer_id . '_' . $runId . '_dropship_do');
        // dropship 为 4 wayfair 为5 因为tracking_id 不存在 所以做出一些调整
        $data['importMode'] = $importMode;
        if ($data['order_do']['amount'] == 0) {
            $data['show_flag'] = 0;
        } else {
            $data['show_flag'] = 1;
        }
        $data['show_url'] = url()->to(['account/customer_order/dropshipUploadFileBoxShow', 'runId' => $runId, 'importMode' => $importMode]);
        $this->cache->delete($customer_id . '_' . $runId . '_dropship_undo');
        $this->cache->delete($customer_id . '_' . $runId . '_dropship_do');
        $this->response->setOutput(load()->view('account/corder_init_pretreatment', $data));

    }

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws Exception
     */
    public function orderPurchase()
    {
        set_time_limit(0);
        load()->language('account/customer_order_import');
        load()->model('account/customer_order_import');
        load()->model('catalog/product');
        load()->model('tool/image');

        // 运行RunId
        $runId = $this->request->query->get('runId', '');
        // ImportMode
        $importMode = $this->request->query->get('importMode', 0);
        $orderIdStr = trim($this->request->query->get('order_id'), ',');
        $country_id = $this->customer->getCountryId();
        $address_id = null;
        $json = [];
        $customerId = $this->customer->getId();
        if ($importMode == HomePickImportMode::IMPORT_MODE_NORMAL) {
            // 普通模式订单导入
            // 通过runId查询tb_sys_customer_sales_order_line
            $customerSalesOrderLineArr = $this->model_account_customer_order_import->getCustomerSalesOrderLineByRunId($runId);
            // 产品未找到的产品数组
            $hasNoProductArr = array();
            //找不到sku 或者被禁用
            $hasNoExistProductArr = [];
            // 没库存需要购买的产品数组
            $hasNoCostArr = array();
            // 需要采购的产品
            $productArr = array();
            // 平台没有的产品
            $noProductArr = array();
            //超大件展示
            $overSizeArr = array();
            // 获取库存MAP
            $productCostMap = $this->customer->getProductCostMap($customerId);
            $productSellerMap = $this->customer->getProductSellerMap($customerId);

            load()->model('account/product_quotes/margin_contract');

            $productCostMapTemp = $productCostMap;
            // 获取根据头表进行分组
            $headerCustomerSalesOrderMap = array();
            $costQtyArr = array();
            if ($customerSalesOrderLineArr && count($customerSalesOrderLineArr)) {
                $oversize_array = array();

                foreach ($customerSalesOrderLineArr as $customerSalesOrderLine) {
                    //设置超大件标志
                    $tag_array = $this->model_catalog_product->getProductSpecificTagByOrderLineId($customerSalesOrderLine['id']);
                    $tags = array();
                    if (isset($tag_array)) {
                        foreach ($tag_array as $tag) {
                            if (isset($tag['origin_icon']) && !empty($tag['origin_icon'])) {
                                //采用唯一图片  LTL.jpg  part.jpg  combo-img.png
                                $img_url = $this->model_tool_image->getOriginImageProductTags($tag['origin_icon']);
                                $tags[] = '<img data-toggle="tooltip"  class="' . $tag['class_style'] . '"    title="' . $tag['description'] . '" style="padding-left: 1px" src="' . $img_url . '">';
                            }
                        }
                    }
                    $customerSalesOrderLine['tag'] = $tags;
                    // 查询oc_product 更新product_id;
                    $itemCode = strtoupper($customerSalesOrderLine['item_code']);
                    // $sellerId = $customerSalesOrderLine['seller_id'];
                    $sellerId = null;
                    // $margin_product = $this->model_account_product_quotes_margin_contract->getMarginProductForBuyer($customerId, $itemCode);
                    // 查询产品更新productId
                    $oc_products = $this->model_account_customer_order_import->findCanBuyProductByItemCodeAndSellerId($itemCode, $sellerId, $productCostMap)->rows;
                    $orderHeader = $this->model_account_customer_order_import->getCustomerSalesOrderById($customerSalesOrderLine['header_id']);
                    $customerSalesOrderLine['ship_address'] = app('db-aes')->decrypt($orderHeader['ship_address1']) . app('db-aes')->decrypt($orderHeader['ship_address2']). ',' . app('db-aes')->decrypt($orderHeader['ship_city']) . ',' . $orderHeader['ship_state'] . ',' . $orderHeader['ship_country'];
                    $customerSalesOrderLine['ship_name'] = app('db-aes')->decrypt($orderHeader['ship_name']);
                    $customerSalesOrderLine['order_id'] = $orderHeader['order_id'];
                    if ($oc_products) {
                        $cost_qty = 0;
                        $sellerArr = array();
                        $sellerNameStr = '';
                        $is_oversize = false;
                        foreach ($oc_products as $oc_product) {
                            $productId = $oc_product['product_id'];
                            //add by xxli 判断product_id的库存
                            if (isset($productCostMap[$productId])) {
                                // 有库存
                                $cost_qty = $cost_qty + $productCostMap[$productId];
                                $sellerNameStr = $sellerNameStr . $productSellerMap[$productId] . ',';
                            }
                            $sellerArr[] = array(
                                'product_id' => $productId,
                                'name' => $oc_product['screenname']
                            );
                            if (!$is_oversize && (!isset($oversize_array[$customerSalesOrderLine['header_id']]) || !$oversize_array[$customerSalesOrderLine['header_id']])) {
                                $is_oversize = $this->model_catalog_product->checkIsOversizeItem($productId);
                                $oversize_array[$customerSalesOrderLine['header_id']] = $is_oversize;
                            }
                            /*$sellerNameStr = $sellerNameStr . $oc_product['screenname'] . ',';*/
                        }
                        //buyer库存itemcode对应的数量
                        $costQtyArr[$itemCode] = $cost_qty;
                        $customerSalesOrderLine['is_oversize'] = $is_oversize;
                        $customerSalesOrderLine['sellerArr'] = $sellerArr;
                        $customerSalesOrderLine['stockQty'] = $cost_qty;
                        if ($sellerNameStr != '') {
                            $customerSalesOrderLine['sellerStr'] = substr($sellerNameStr, 0, strlen($sellerNameStr) - 1);
                        } else {
                            $customerSalesOrderLine['sellerStr'] = '';
                        }

                        //如果oc_products为1更新product_id
                        $canBuyProduct = $this->model_account_customer_order_import->findCanBuyProductByItemCodeAndSellerId($itemCode, $sellerId)->rows;
                        if (count($canBuyProduct) == 1) {
                            $this->model_account_customer_order_import->updateCustomerSalesOrderLineProductId($canBuyProduct[0]['product_id'], $customerSalesOrderLine['id']);
                            $customerSalesOrderLine['product_id'] = $canBuyProduct[0]['product_id'];
                        }

                    } else {
                        //{#12406 order fullfillment 2期#}
                        if (isset($noProductArr[$itemCode])) {
                            $customerSalesOrderLineEntity = $noProductArr[$itemCode];
                            $customerSalesOrderLineEntity['qty'] = intval($customerSalesOrderLineEntity['qty']) + intval($customerSalesOrderLine['qty']);
                            $customerSalesOrderLineEntity['qty_list'][] = $customerSalesOrderLine['qty'];
                            $customerSalesOrderLineEntity['order_id_list'][] = $customerSalesOrderLine['order_id'];
                            $noProductArr[$itemCode] = $customerSalesOrderLineEntity;
                        } else {
                            $noProductArr[$itemCode] = $customerSalesOrderLine;
                            $noProductArr[$itemCode]['qty_list'][] = $customerSalesOrderLine['qty'];
                            $noProductArr[$itemCode]['order_id_list'][] = $customerSalesOrderLine['order_id'];
                        }
                    }
                    //更新制造商icon ,品牌判断需要修改2019-01-10
                    //$imageId = $this->getManufactureImageId($customerSalesOrderLine['image_id'],$customerSalesOrderLine['product_id']);
                    // add by lilei 暂时都是1
                    $imageId = 1;
                    $this->model_account_customer_order_import->updateCustomerSalesOrderLineImageId($imageId, $customerSalesOrderLine['id']);
                    if (isset($headerCustomerSalesOrderMap[$customerSalesOrderLine['header_id']])) {
                        $headerCustomerSalesOrderMap[$customerSalesOrderLine['header_id']][] = $customerSalesOrderLine;
                    } else {
                        $headerCustomerSalesOrderMap[$customerSalesOrderLine['header_id']] = array();
                        $headerCustomerSalesOrderMap[$customerSalesOrderLine['header_id']][] = $customerSalesOrderLine;
                    }
                }
                $keys = array_keys($headerCustomerSalesOrderMap);
                foreach ($keys as $orderNo => $key) {
                    $lineDatas = $headerCustomerSalesOrderMap[$key];
                    $count = count($lineDatas);
                    $index = 0;
                    foreach ($lineDatas as $lineNo => $lineData) {
                        $lineItemCode = strtoupper($lineData['item_code']);
                        if (isset($oversize_array[$lineData['header_id']])) {
                            $lineData['is_oversize'] = $oversize_array[$lineData['header_id']];
                        }
                        if (isset($costQtyArr[$lineItemCode])) {
                            $cost_qty = $costQtyArr[$lineItemCode];
                            if ($cost_qty > 0) {
                                if ($cost_qty >= intval($lineData['qty'])) {
                                    // 减去已售未发
                                    $cost_qty = $cost_qty - intval($lineData['qty']);
                                    $costQtyArr[$lineItemCode] = $cost_qty;
                                    $sucessArr = $headerCustomerSalesOrderMap[$key][$lineNo];
                                    $sucessArr['qty'] = intval($lineData['qty']);
                                    $sucessArr['order_status'] = 1;
                                    if ($lineData['is_oversize']) {
                                        //超大件订单模态框订单状态直接显示LTL CHECK
                                        $sucessArr['order_status'] = CustomerSalesOrderStatus::LTL_CHECK;
                                    } else {
                                        $sucessArr['order_status'] = CustomerSalesOrderStatus::TO_BE_PAID;
                                    }
                                    $sucessArr['is_oversize'] = $lineData['is_oversize'];
                                    $productArr[$orderNo][] = $sucessArr;
                                    $index++;
                                } else {
                                    $needToBuy = intval($lineData['qty']) - $cost_qty;
                                    $costQtyArr[$lineItemCode] = $cost_qty - intval($lineData['qty']);
                                    $sucessArr = $headerCustomerSalesOrderMap[$key][$lineNo];
                                    $sucessArr['qty'] = $cost_qty;
                                    $sucessArr['is_oversize'] = $lineData['is_oversize'];
                                    if ($lineData['is_oversize']) {
                                        //超大件订单模态框订单状态直接显示LTL CHECK
                                        $sucessArr['order_status'] = CustomerSalesOrderStatus::LTL_CHECK;
                                    } else {
                                        $sucessArr['order_status'] = CustomerSalesOrderStatus::TO_BE_PAID;
                                    }
                                    $productArr[$orderNo][] = $sucessArr;
                                    $cost_qty = $needToBuy;
                                    $lineData['qty'] = $needToBuy;
                                    $hasNoCostArr[] = $lineData;
                                }
                            } else {
                                $hasNoCostArr[] = $lineData;
                            }
                        } else {
                            if (isset($lineItemCode)) {
                                // 没有库存
                                if (!isset($noProductArr[$lineItemCode])) {
                                    $hasNoCostArr[] = $lineData;
                                }
                            }
                        }
                    }
                    if ($count == $index) {
                        //增加非超大件订单的过滤
                        if (isset($oversize_array[$key]) && $oversize_array[$key]) {
                            foreach ($lineDatas as $LTLData) {
                                $LTLData['order_status'] = CustomerSalesOrderStatus::LTL_CHECK;
                                foreach ($productArr as $index1 => $products) {
                                    foreach ($products as $index2 => $product) {
                                        if ($product['id'] == $LTLData['id']) {
                                            $productArr[$index1][$index2]['order_status'] = CustomerSalesOrderStatus::LTL_CHECK;
                                        } else {

                                            #12406 order fullfillment 二期#
                                            //$productArr[$index1][$index2] = $LTLData;
                                        }
                                    }
                                }
                            }
                        } else {
                            $productCostMapTemp = $productCostMap;
                            // #24701 上门取货去除导单强绑逻辑
                        }
                    } else {
                        $productCostMap = $productCostMapTemp;
                    }
                }
                foreach ($noProductArr as $customerSalesOrderLine) {
                    //$hasNoProductArr[] = $customerSalesOrderLine;
                    //13827【需求】Reorder页面和购买弹窗中的不存在在平台上的SKU表格拆分成未建立关联的SKU和平台上不存在的SKU两个表格
                    //放入的只有两种情况 一种平台无sku 一种为未建立联系
                    $exist_flag = $this->model_account_customer_order_import->judgeSkuIsExist($customerSalesOrderLine['item_code']);
                    if ($exist_flag) {
                        $hasNoProductArr[] = $customerSalesOrderLine;
                    } else {
                        $hasNoExistProductArr[] = $customerSalesOrderLine;
                    }
                }

                //by chenyang 更新超大件订单为TLT Check状态
                if (isset($oversize_array) && !empty($oversize_array)) {
                    $TLT_order_ids = array();
                    foreach ($oversize_array as $saleOrderId => $isOversize) {
                        if ($isOversize) {
                            $TLT_order_ids[] = $saleOrderId;
                        }
                    }
                    //64为新增超大件确认等待 LTL Check状态
                    $this->model_account_customer_order_import->batchUpdateCustomerSalesOrderStatus($TLT_order_ids, CustomerSalesOrderStatus::LTL_CHECK);
                }
            }
            //超大件拆分
            foreach ($hasNoCostArr as $key => $noCost) {
                if ($noCost['is_oversize']) {
                    $overSizeArr[] = $noCost;
                    unset($hasNoCostArr[$key]);
                }
            }
            foreach ($productArr as $key => $hasProduct) {
                foreach ($hasProduct as $ks => $vs) {
                    if ($vs['is_oversize']) {
                        $overSizeArr[] = $vs;
                        unset($productArr[$key][$ks]);
                    }
                }
            }
            // 设置缓存
            $this->cache->set($runId . "hasNoProductArr", $hasNoProductArr);
            $this->cache->set($runId . "hasNoCostArr", $hasNoCostArr);
            $this->cache->set($runId . "productArr", $productArr);
            $this->cache->set($runId . "overSizeArr", $overSizeArr);
            $this->cache->set($runId . "hasNoExistProductArr", $hasNoExistProductArr);
            $json['modalBox'] = url()->to(['account/customer_order/modalBoxShow', 'runId' => $runId, 'importMode' => $importMode]);
            $json['success'] = $this->language->get('text_process_success');

        } elseif (in_array($importMode,
            [
                HomePickImportMode::IMPORT_MODE_AMAZON,
                HomePickImportMode::IMPORT_MODE_WAYFAIR,
                HomePickImportMode::US_OTHER,
                HomePickImportMode::IMPORT_MODE_WALMART,
                HomePickImportMode::IMPORT_MODE_BUYER_PICK_UP,
            ])
        ) {
            //importMode 6  ordermode 3
            //处理商品详情line表的数据
            // 普通模式订单导入
            // 通过runId查询tb_sys_customer_sales_order_line
            $manifest_flag = YesNoEnum::NO;
            if ($importMode == HomePickImportMode::IMPORT_MODE_WAYFAIR && in_array($country_id, EUROPE_COUNTRY_ID)) {
                $manifest_flag = YesNoEnum::YES;
            }
            $lineList = $this->model_account_customer_order_import->getCustomerSalesOrderLineByRunIdAndCustomerId($runId, $customerId, $manifest_flag);
            if ($lineList) {
                $res = $this->model_account_customer_order_import->getDropshipProductsInfoByCalc($lineList);
                // 设置缓存
                $this->cache->set($customerId . '_' . $runId . '_hasNoProductArr', $res['hasNoProductArr']);
                $this->cache->set($customerId . '_' . $runId . '_hasNoCostArr', $res['hasNoCostArr']);
                $this->cache->set($customerId . '_' . $runId . '_productArr', $res['productArr']);
                $this->cache->set($customerId . '_' . $runId . '_hasNoExistProductArr', $res['hasNoExistProductArr']);
                $json['modalBox'] = url()->to(['account/customer_order/dropshipModalBoxShow', 'runId' => $runId, 'importMode' => $importMode]);
            }
            $json['success'] = $this->language->get('text_dropship_process_success');

        }

        return $this->response->json($json);
    }

    /**
     * @param int $brandId
     * @param int $productId
     * @return mixed|null
     */
    private function getManufactureImageId($brandId, $productId)
    {
        $result = null;
        if (!empty($brandId)) {
            $userSet = $this->model_account_customer_order_import->findManufactureInfoByManufactureId($brandId);
            if (!empty($productId)) {
                $sysSet = $this->model_account_customer_order_import->findManufactureInfoByProductId($productId);
                if (!empty($userSet)) {
                    if (!empty($sysSet)) {
                        if (($this->customer->getId() == $userSet['customer_id']) && ($sysSet['can_brand'] == true)) {
                            $result = $userSet['image_id'];
                        } else {
                            $result = $sysSet['image_id'];
                        }
                    }
                } else {
                    if (!empty($sysSet)) {
                        $result = $sysSet['image_id'];
                    }
                }
            }
        } else {
            if (!empty($productId)) {
                $info = $this->model_account_customer_order_import->findManufactureInfoByProductId($productId);
                if (!empty($info)) {
                    $result = $info['image_id'];
                }
            }
        }
        return $result;
    }

    /**
     * @return \Framework\Http\Response
     * @throws Throwable
     */
    public function dropshipUploadFileBoxShow()
    {
        load()->language('account/customer_order_import');
        load()->model('account/customer_order_import');
        load()->model('tool/image');
        load()->model('account/customer_order');
        /**
         * @var ModelAccountCustomerOrderImport $customerOrderImport
         * @var ModelAccountCustomerOrder $model_customer_order
         */
        $customerOrderImport = $this->model_account_customer_order_import;
        $model_customer_order = $this->model_account_customer_order;
        //13846
        $country_id = customer()->getCountryId();
        $data = [];
        $data['app_version'] = APP_VERSION;
        $data['comboTag'] = Tag::query()->where('tag_id', 3)->first();
        $data['country_id'] = $country_id;
        // 运行RunId
        $runId = request('runId');
        $customer_id = customer()->getId();
        // ImportMode
        $importMode = request('importMode');
        //dropship 业务 importMode 4 bol 是生成的 wayFair  importMode 5 bol 是导入的
        $data['dropship_file_deal_url'] = $this->url->link('account/customer_order/dropshipFileDeal');
        $data['dropship_file_check_url'] = $this->url->link('account/customer_order/dropshipFileCheck');
        $data['dropship_file_unlink'] = $this->url->link('account/customer_order/dropshipFileUnlink');
        $data['dropship_file_submit'] = $this->url->link('account/customer_order/dropshipFilePreserved', ['runId' => $runId, 'importMode' => $importMode]);
        $data['dropship_form_check'] = $this->url->link('account/customer_order/checkIsRepeatForm', ['runId' => $runId, 'importMode' => $importMode]);
        $data['dropship_order_purchase'] = $this->url->link('account/customer_order/orderPurchase', ['runId' => $runId, 'importMode' => $importMode]);
        $data['dropship_order_match'] = $this->url->link('sales_order/match', ['runId' => $runId, 'importMode' => $importMode]);
        switch ($importMode){
            case HomePickImportMode::IMPORT_MODE_AMAZON:
                $data['upload_file_info'] =  $customerOrderImport->getDropshipUploadFileInfo($runId, $customer_id);
                $tmp['container_id_list'] = $this->session->get('container_id_list');
                $data['js_auxiliary_information'] = json_encode($tmp);
                return $this->render('account/corder_import_file_upload', $data);
                break;
            case HomePickImportMode::IMPORT_MODE_WAYFAIR:
                if (in_array($country_id, EUROPE_COUNTRY_ID)) {
                    //欧洲wayfair 没有bol 有manifest
                    $data['wayfair_bol_deal_url'] = $this->url->link('account/customer_order/wayfairBolDeal');
                    $upload_file_info = $customerOrderImport->getEuropeWayfairUploadFileInfo($runId, $customer_id);
                    $data['upload_file_info'] = $upload_file_info;
                    $tmp['container_id_list'] = session('europe_wayfair_container_id_list');
                    $data['js_auxiliary_information'] = json_encode($tmp);
                    //替换图标
                    $data['image_icon_url'] = '';
                    $tag_detail = $model_customer_order->getTagInfoByTagId(3);
                    if ($tag_detail) {
                        $image_url = $this->model_tool_image->getOriginImageProductTags($tag_detail->icon);
                        $image_icon_url = '<img data-toggle="tooltip" class="' . $tag_detail->class_style . '" style="padding-left: 1px" src="' . $image_url . '">';
                        $data['image_icon_url'] = $image_icon_url;
                    }
                    return $this->render('account/corder_import_europe_wayfair', $data);
                } else {
                    $data['wayfair_bol_deal_url'] = $this->url->link('account/customer_order/wayfairBolDeal');
                    $upload_file_info = $customerOrderImport->getWayfairUploadFileInfo($runId, $customer_id);
                    $data['upload_file_info'] = $upload_file_info;
                    $tmp['container_id_list'] = session('wayfair_container_id_list');
                    $tmp['wayfair_bol'] =session('wayfair_bol');
                    $data['js_auxiliary_information'] = json_encode($tmp);
                    return $this->render('account/corder_import_wayfair_file_upload', $data);
                }
                break;
            case HomePickImportMode::IMPORT_MODE_WALMART:
                $upload_file_info = $customerOrderImport->getWalmartUploadFileInfo($runId, $customer_id);
                $data['upload_file_info'] = $upload_file_info;
                $data['edit_first'] = 1; //首次提交
                $tmp['container_id_list'] = session('walmart_container_id_list');
                $tmp['walmart_bol'] = session('walmart_bol');
                $tmp['validation_rule'] = session('validation_rule');
                $data['js_auxiliary_information'] = json_encode($tmp);
                $data['walmart_bol_deal_url'] = $this->url->link('account/customer_order/walmartBolDeal');
                $data['walmart_verify_tracking'] = $this->url->link('account/customer_order/verifyWalmartTrackingNumber', ['order_id' => 0]);
                //替换图标
                $data['image_icon_url'] = '';
                $tag_detail = $model_customer_order->getTagInfoByTagId(3);
                if ($tag_detail) {
                    $image_url = $this->model_tool_image->getOriginImageProductTags($tag_detail->icon);
                    $image_icon_url = '<img data-toggle="tooltip" class="' . $tag_detail->class_style . '" style="padding-left: 1px" src="' . $image_url . '">';
                    $data['image_icon_url'] = $image_icon_url;
                }

                return $this->render('account/corder_import_walmart_file_upload', $data);
                break;
            case HomePickImportMode::US_OTHER:
                if($country_id == AMERICAN_COUNTRY_ID){
                    $map = [
                        ['run_id', '=', $runId],
                        ['buyer_id', '=', $customer_id],
                    ];
                    //美国上门取件上传文件信息
                    $data['upload_file_info'] = $customerOrderImport->getUsPickUpOtherUploadFileInfo($map, 0);
                    $data['edit_first'] = 1; //首次提交
                    $tmp['container_id_list'] = $this->session->get('container_id_list');
                    $tmp['bol'] = session('bol');
                    $data['js_auxiliary_information'] = json_encode($tmp);
                    //美国上门取货other导单入口的 LTL packing slip 裁剪
                    $data['ltl_packing_slip_cut'] = HomePickCarrierType::US_PICK_UP_OTHER_LTL_PACKING_SLIP;
                    //美国上门取货other导单入口的 LTL 正常label 裁剪
                    $data['ltl_label_cut'] = HomePickCarrierType::US_PICK_UP_OTHER_LTL_LABEL;
                    $data['packing_slip_label_url'] = $this->url->link('account/customer_order/downloadTemplateLabel', ['label_type' => HomePickOtherLabelType::PACKING_SLIP]);
                    $data['label_url'] = $this->url->link('account/customer_order/downloadTemplateLabel', ['label_type' => ' ']);
                    $data['ltl_label_url'] = $this->url->link('account/customer_order/downloadTemplateLabel', ['label_type' => HomePickOtherLabelType::LABEL]);
                    //label裁剪
                    $data['us_other_file_deal_url'] = $this->url->link('account/customer_order/usOtherFileDeal', []);
                    //tracking number检测是否重复
                    $data['walmart_verify_tracking'] = $this->url->link('account/customer_order/verifyWalmartTrackingNumber', ['order_id' => 0]);
                    //bol上传
                    $data['walmart_bol_deal_url'] = $this->url->link('account/customer_order/walmartBolDeal', []);
                    return $this->render('account/corder_import_other_file_upload', $data);
                    break;
                }
        }

    }

    /**
     * [dropshipOrderFileUpload description]
     * @throws Exception
     */
    public function dropshipOrderFileUpload()
    {
        $id = $this->request->get('id', 0);
        load()->language('account/customer_order_import');
        load()->model('account/customer_order_import');
        load()->model('account/customer_order');
        load()->model('tool/image');
        /**
         * @var ModelAccountCustomerOrderImport $customerOrderImport
         * @var ModelAccountCustomerOrder $model_customer_order
         */
        $model_customer_order = $this->model_account_customer_order;
        $customerOrderImport = $this->model_account_customer_order_import;
        $country_id = customer()->getCountryId();
        $data['app_version'] = APP_VERSION;
        $data['country_id'] = $country_id;
        //获取 combo tag
        $data['comboTag'] = Tag::query()->where('tag_id', 3)->first();
        $res = $customerOrderImport->judgeDropshipIsOverTime($id);
        $file_info = $customerOrderImport->getFileInfoByOrderId($id, $country_id);
        $import_mode = $file_info['import_mode'];
        // 针对于德国发往英国的货进行商业发票的填写
        $ship_country = $file_info['ship_country'];
        $upload_file_info = $file_info['upload_file_info'];
        $data['upload_file_info'] = $upload_file_info;
        //获取上传订单的 库存数量和 sku combo 的结构
        //验证是初次编辑还是多次编辑
        $flag = HomePickLabelDetails::query()
            ->where([
                ['order_id', '=', $id],
                ['status', '=', 1],
            ])
            ->groupBy('order_id')
            ->exists();
        $data['dropship_file_deal_url'] = url()->link('account/customer_order/dropshipFileDeal');
        $data['dropship_file_check_url'] = url()->link('account/customer_order/dropshipFileCheck');
        $data['dropship_file_unlink'] = url()->link('account/customer_order/dropshipFileUnlink');
        $data['dropship_file_submit'] = url()->link('account/customer_order/dropshipSingleFilePreserved', ['id' => $id]);
        $data['home_pick_commercial_invoice'] = YesNoEnum::NO;
        switch ($import_mode) {
            case HomePickImportMode::IMPORT_MODE_WAYFAIR:
                //欧洲wayfair 没有bol 只有manifest
                if (in_array($country_id, EUROPE_COUNTRY_ID)) {
                    // 验证是否需要商业发票
                    if(
                        $country_id == HomePickUploadType::GERMANY_COUNTRY_ID
                        && in_array(strtoupper($ship_country),$this->config->get('home_pick_commercial_invoice'))
                    ){
                        $data['home_pick_commercial_invoice'] = YesNoEnum::YES;
                    }
                    //替换图标
                    $data['image_icon_url'] = '';
                    $tag_detail = $model_customer_order->getTagInfoByTagId(3);
                    if ($tag_detail) {
                        $image_url = $this->model_tool_image->getOriginImageProductTags($tag_detail->icon);
                        $image_icon_url = '<img data-toggle="tooltip" class="' . $tag_detail->class_style . '" style="padding-left: 1px" src="' . $image_url . '">';
                        $data['image_icon_url'] = $image_icon_url;
                    }
                    if ($res) {
                        //获取所有上传的 非edit_first的
                        $data['verify_file_name'] = $flag ? json_encode(session('europe_wayfair_label_details')) : json_encode(null);
                        $data['edit_first'] = $flag ? 0 : 1;
                        $tmp['container_id_list'] = session('europe_wayfair_container_id_list');
                        $data['js_auxiliary_information'] = json_encode($tmp);
                        $data['wayfair_bol_deal_url'] = url()->link('account/customer_order/wayfairBolDeal');
                        return $this->render('account/corder_import_single_europe_wayfair', $data);
                    } else {
                        return $this->render('account/corder_import_single_europe_wayfair_show', $data);
                    }

                } else {
                    if ($res) {
                        $data['edit_first'] = $flag ? 0 : 1;
                        $data['bol_path'] = $flag ? $upload_file_info[0]['bol_file_name'] : '';
                        if ($flag) {
                            //获取所有上传的 非edit_first的
                            $data['vertify_file_name'] = HomePickLabelDetails::query()
                                ->where([
                                    ['order_id', '=', $id],
                                    ['status', '=', 1],
                                ])
                                ->selectRaw('group_concat(file_name) as fileStr')
                                ->value('fileStr');
                        }
                        $tmp['container_id_list'] = $this->session->get('wayfair_container_id_list');
                        $tmp['wayfair_bol'] = session('wayfair_bol') ?? null;
                        $data['js_auxiliary_information'] = json_encode($tmp);
                        $data['wayfair_tracking_info'] = session('wayfair_tracking_info');
                        $data['wayfair_bol_deal_url'] = $this->url->link('account/customer_order/wayfairBolDeal');
                        return $this->render('account/corder_import_single_wayfair_file_upload', $data);
                    } else {
                        return $this->render('account/corder_import_single_wayfair_file_upload_show', $data);
                    }
                }
                break;
            case HomePickImportMode::IMPORT_MODE_AMAZON:
                if ($res) {
                    //验证是初次编辑还是多次编辑
                    $data['edit_first'] = $flag ? 0 : 1;
                    if ($flag) {
                        //获取所有上传的 非edit_first的
                        $data['vertify_file_name'] = HomePickLabelDetails::query()
                            ->where([
                                ['order_id', '=', $id],
                                ['status', '=', 1],
                            ])
                            ->selectRaw('group_concat(file_name) as fileStr')
                            ->value('fileStr');

                    }
                    $tmp['container_id_list'] = $this->session->get('container_id_list');
                    $data['js_auxiliary_information'] = json_encode($tmp);
                    return $this->render('account/corder_import_single_file_upload', $data);
                } else {
                    return $this->render('account/corder_import_single_file_upload_show', $data);
                }
                break;
            case HomePickImportMode::US_OTHER:
                if ($country_id == AMERICAN_COUNTRY_ID) { //美国上门取货common(也称other)导单上传文件详情
                    $res = $this->judgeUsOtherCanEditUploadLabel($id);//是否展示可编辑弹窗
                    //获取所有上传的 非edit_first的
                    $data['verify_file_name'] = session('label_details') ? json_encode(session('label_details')) : json_encode(null);
                    $data['bol_path'] = $upload_file_info[0]['bol_file_name'] ?? '';
                    if ($res) {//可编辑
                        $data['edit_first'] = $flag ? 0 : 1;
                        $tmp['container_id_list'] = session('container_id_list');
                        $tmp['bol'] = session('bol');

                        $data['js_auxiliary_information'] = json_encode($tmp);
                        //label裁剪
                        $data['us_other_file_deal_url'] = $this->url->link('account/customer_order/usOtherFileDeal', []);
                        //bol上传
                        $data['walmart_bol_deal_url'] = $this->url->link('account/customer_order/walmartBolDeal', []);
                        //上传文件删除
                        //提交
                        //tracking number检测是否重复
                        $data['walmart_verify_tracking'] = $this->url->link('account/customer_order/verifyWalmartTrackingNumber', ['order_id' => $id]);
                        //美国上门取货other导单入口的 LTL packing slip 裁剪
                        $data['ltl_packing_slip_cut'] = HomePickCarrierType::US_PICK_UP_OTHER_LTL_PACKING_SLIP;
                        //美国上门取货other导单入口的 LTL 正常label 裁剪
                        $data['ltl_label_cut'] = HomePickCarrierType::US_PICK_UP_OTHER_LTL_LABEL;
                        $data['packing_slip_label_url'] = $this->url->link('account/customer_order/downloadTemplateLabel', ['label_type' => HomePickOtherLabelType::PACKING_SLIP]);
                        $data['label_url'] = $this->url->link('account/customer_order/downloadTemplateLabel', ['label_type' => ' ']);
                        $data['ltl_label_url'] = $this->url->link('account/customer_order/downloadTemplateLabel', ['label_type' => HomePickOtherLabelType::LABEL]);
                        //label裁剪
                        $data['us_other_file_deal_url'] = $this->url->link('account/customer_order/usOtherFileDeal', []);
                        //us上门取货订单审核信息
                        $data['us_other_review'] = $this->url->link('account/customer_order/usOtherReviewStatus', []);
                        return $this->render('account/corder_import_single_other_file_upload', $data);
                    } else {//查看
                        return $this->render('account/corder_import_single_other_file_upload_show', $data);
                    }
                }
                break;
            case HomePickImportMode::IMPORT_MODE_WALMART:
                //WALMART 获取walmart的上传文件详情
                //替换图标
                $data['image_icon_url'] = '';
                $tag_detail = $model_customer_order->getTagInfoByTagId(3);
                if ($tag_detail) {
                    $image_url = $this->model_tool_image->getOriginImageProductTags($tag_detail->icon);
                    $image_icon_url = '<img data-toggle="tooltip" class="' . $tag_detail->class_style . '" style="padding-left: 1px" src="' . $image_url . '">';
                    $data['image_icon_url'] = $image_icon_url;
                }
                if ($res) {
                    $data['verify_file_name'] = session('walmart_label_details') ? json_encode(session('walmart_label_details')) : json_encode(null);
                    $data['bol_path'] = $upload_file_info[0]['bol_file_name'] ?? '';
                    $data['edit_first'] = $flag ? 0 : 1;
                    $tmp['container_id_list'] = session('walmart_container_id_list');
                    $tmp['walmart_store_label'] = session('walmart_store_label');
                    $tmp['walmart_bol'] = session('walmart_bol');
                    $data['js_auxiliary_information'] = json_encode($tmp);
                    //文件ajax 上传
                    $data['walmart_bol_deal_url'] = $this->url->link('account/customer_order/walmartBolDeal');
                    $data['walmart_verify_tracking'] = $this->url->link('account/customer_order/verifyWalmartTrackingNumber', ['order_id' => $id]);

                    return $this->render('account/corder_import_single_walmart_file_upload', $data);
                } else {
                    return $this->render('account/corder_import_single_walmart_file_upload_show', $data);

                }
                break;
        }
    }

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function verifyEuropeWayfairTrackingNumber()
    {
        $order_id = request()->post('order_id', 0);
        $tracking_number = request()->post('tracking_number', []);
        $tracking_number_list = array_values($tracking_number);
        // 检测是否为空
        if (count($tracking_number_list) != count(array_filter($tracking_number_list))) {
            load()->language('account/customer_order_import');
            $json['error'] = 1;
            $json['result'] = $this->language->get('error_europe_tracking_number_fill');
        } else {
            $map = [
                ['f.status', '=', 1],
                ['f.create_user_name', '=', $this->customer->getId()],
                ['o.order_status', '!=', CustomerSalesOrderStatus::CANCELED],
            ];
            $ret = HomePickLabelDetails::query()->alias('f')
                ->leftJoin('tb_sys_customer_sales_order as o', 'f.order_id', '=', 'o.id')
                ->where($map)
                ->whereIn('f.tracking_number', $tracking_number_list)
                ->whereNotIn('f.order_id', [$order_id])
                ->orderByDesc('o.create_time')
                ->select(['o.order_id', 'f.tracking_number'])
                ->get()
                ->toArray();
            if (count($ret)) {
                $json['error'] = 1;
                // 返回报错
                // 和其他订单的TrackingNumber重复（包括历史订单）：Tracking Number: 第一个重复的Tracking Number 与订单（Sales Order ID: 第一个重复的订单号）中的Tracking Number重复，请重新选择label。​
                $string = 'The tracking number [' . $ret[0]['tracking_number'] . '] is a duplicate of the sales order [' . $ret[0]['order_id'] . ']. Do you wish to confirm continuing the upload of this label?';
                $json['result'] = $string;
            } else {
                $json['error'] = 0;
                $json['result'] = $ret;
            }

        }
        return $this->response->json($json);
    }

    /**
     * [verifyWalmartTrackingNumber description] 校验是否有重复的tracking_number
     */
    public function verifyWalmartTrackingNumber()
    {
        $order_id = $this->request->get('order_id', 0);
        $tracking_number_list = $this->request->post('tracking_number_list', []);
        $map = [
            ['f.status', '=', 1],
            ['f.create_user_name', '=', $this->customer->getId()],
            ['o.order_status', '!=', CustomerSalesOrderStatus::CANCELED],
        ];
        $ret = $this->orm->table('tb_sys_customer_sales_dropship_file_details as f')
            ->leftJoin('tb_sys_customer_sales_order as o', 'f.order_id', '=', 'o.id')
            ->whereNotIn('f.order_id', [$order_id])
            ->where($map)
            ->whereIn('f.tracking_number', $tracking_number_list)
            ->groupBy('f.tracking_number')
            ->pluck('f.tracking_number');
        if (count($ret)) {
            $json['error'] = 1;
            $json['result'] = $ret;
        } else {
            $json['error'] = 0;
            $json['result'] = $ret;
        }
        return $this->response->json($json);
    }

    /**
     * [vertifyDropShipTrackingNumber description] 验证是否有重复的tracking_number
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function vertifyDropShipTrackingNumber()
    {
        if (request()->isMethod('GET')) {
            $tracking_number = Request('tracking_number');
            $id = Request('file_id');
            $map = [
                ['f.status', '=', 1],
                ['f.create_user_name', '=', Customer()->getId()],
                ['f.tracking_number', 'like', "{$tracking_number}"],
                ['o.order_status', '!=', CustomerSalesOrderStatus::CANCELED],
            ];
            $order_id = $this->orm->table('tb_sys_customer_sales_dropship_file_details as f')
                ->whereNotIn('f.id', [$id])
                ->where($map)
                ->leftJoin('tb_sys_customer_sales_order as o', 'f.order_id', '=', 'o.id')
                ->value('o.order_id');
            if ($order_id)
                goto end;

            //找到temp表中对应的
            //temp表里是不对的 分割查到了证明是真的有
            $temp_id = $this->orm->table('tb_sys_customer_sales_dropship_file_details')->where('id', $id)->value('temp_id');
            if ($temp_id) {
                $mapRes = [
                    ['t.create_id', '=', Customer()->getId()],
                    ['o.create_user_name', '=', Customer()->getId()],
                    ['o.order_status', '!=', CustomerSalesOrderStatus::CANCELED],
                    ['o.id', '!=', $temp_id],
                ];
            } else {
                $mapRes = [
                    ['t.create_id', '=', Customer()->getId()],
                    ['o.create_user_name', '=', Customer()->getId()],
                    ['o.order_status', '!=', CustomerSalesOrderStatus::CANCELED],
                ];

            }
            $order_id = $this->orm->table('tb_sys_customer_sales_dropship_temp as t')
                ->leftJoin('tb_sys_customer_sales_order as o', 'o.order_id', 't.order_id')
                ->where($mapRes)
                ->where(function ($query) use ($tracking_number) {
                    $common_v = $tracking_number . '&';
                    $query->where('tracking_id', 'like', "%{$common_v}")->orWhere('tracking_id', 'like', "%{$tracking_number}");
                })
                ->limit(1)
                ->value('o.order_id');
            end:
            $json['order_id'] = $order_id;
            return $this->response->json($json);
        }
    }

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws Exception
     */
    public function dropshipSingleFilePreserved()
    {
        $id = request('id', 0);
        $post = $this->request->post;
        $country_id = customer()->getCountryId();
        $json['msg'] = '';
        load()->language('account/customer_order_import');
        load()->model('account/customer_order_import');
        $salesOrderInfo = CustomerSalesOrder::find($id);
        $import_mode = $salesOrderInfo->import_mode;
        $run_id = $salesOrderInfo->run_id;
        $func = [
            HomePickImportMode::IMPORT_MODE_AMAZON => ['getDropshipSingleUploadFileInfo'],
            HomePickImportMode::IMPORT_MODE_WAYFAIR => ['getWayfairSingleUploadFileInfo', 'getEuropeWayfairSingleUploadFileInfo'],
            HomePickImportMode::IMPORT_MODE_WALMART => ['getWalmartSingleUploadFileInfo'],
            HomePickImportMode::US_OTHER => ['getUsPickUpOtherUploadFileInfo']
        ];

        if (in_array($import_mode, [HomePickImportMode::IMPORT_MODE_AMAZON, HomePickImportMode::IMPORT_MODE_WALMART])) {
            $upload_file_info = call_user_func_array([$this->model_account_customer_order_import, $func[$import_mode][0]], [$id]);
        } else {
            if (in_array($country_id, EUROPE_COUNTRY_ID)) {
                $upload_file_info = call_user_func_array([$this->model_account_customer_order_import, $func[$import_mode][1]], [$id]);
            } else if ($import_mode == HomePickImportMode::US_OTHER && $country_id == AMERICAN_COUNTRY_ID) {
                //美国上门取货
                $map = [
                    ['id', '=', $id],
                ];
                $upload_file_info = call_user_func_array([$this->model_account_customer_order_import, $func[$import_mode][0]], [$map, 1]);
            } else {
                $upload_file_info = call_user_func_array([$this->model_account_customer_order_import, $func[$import_mode][0]], [$id]);
            }
        }

        $flag = HomePickLabelDetails::query()
            ->where([
                'order_id' => $id,
                'status' => YesNoEnum::NO,
            ])
            ->select('id')
            ->value('id');

        if (
            (!$flag && $post['edit_first'])
            || !$post['edit_first']
        ) {
            $lock = null;
            if ($post['edit_first']) {
                $lock = Locker::dropshipUploadLabelFirst($id);
                if ($lock->acquire(true)) {
                    // 首次提交的校验销售单状态
                    if ($salesOrderInfo->order_status != CustomerSalesOrderStatus::CHECK_LABEL) {
                        $json['msg'] = "Shipping labels for the sales orders (ID: {$salesOrderInfo->order_id}) have been submitted. If you want to modify, please access Sales Order list and click on 'Manage Labels'.";
                        return $this->response->json($json);
                    }
                }
            }
            $labels = $this->model_account_customer_order_import->wayfairSingleFileUploadDetails($post, $upload_file_info, $post['edit_first'], $import_mode, $country_id,$run_id);
            if ($lock && $lock->isAcquired()) {
                $lock->release();
            }
            $order_status = $this->model_account_customer_order_import->getSalesOrderColumn(['id' => $id], 'order_status');
            //美国上门取货的other导单(非首次编辑的时候)
            if ($import_mode == HomePickImportMode::US_OTHER
                && $country_id == AMERICAN_COUNTRY_ID
                && $post['edit_first'] == YesNoEnum::NO
            ) {
                //的订单状态不为New order 与Check Label
                if ($order_status != CustomerSalesOrderStatus::TO_BE_PAID
                    && $order_status != CustomerSalesOrderStatus::CHECK_LABEL
                ) {
                    $json['msg'] = 'You have successfully submitted.';
                    return $this->response->json($json);
                }
            }
            // 校验是否是bp订单，bp订单不需要再次执行getDropshipProductsInfoByCalc
            if ($order_status != CustomerSalesOrderStatus::BEING_PROCESSED) {
                //处理商品详情line表的数据
                $lineList = $this->model_account_customer_order_import->getSalesOrderLineDetails(['header_id' => $id]);
                //数量足够直接变成bp订单
                $lineList = obj2array($lineList);
                if ($import_mode == HomePickImportMode::IMPORT_MODE_WAYFAIR
                    && in_array($country_id, EUROPE_COUNTRY_ID)
                ) {
                    $verify_flag = $this->model_account_customer_order_import->verifyHasManifestFile($id);
                    if ($verify_flag) {
                        $this->model_account_customer_order_import->getDropshipProductsInfoByCalc($lineList);
                    }
                } else {
                    $this->model_account_customer_order_import->getDropshipProductsInfoByCalc($lineList);
                }
                // 更改order中存在的combo的信息
                $this->model_account_customer_order_import->updatePurchaseOrderLineComboInfo($id, $this->customer->getId());
            }

            if ($post['edit_first']) {
                $json['msg'] = 'You have successfully uploaded ' . (int)$labels . ' label(s).';
            } else {
                $json['msg'] = 'You have successfully submitted.';
            }

        }

        return $this->response->json($json);

    }


    /**
     * [dropshipOrderState description] 此处日常会发生状态变更bp被覆盖成check_label状态
     * @return JsonResponse
     * @throws Exception
     */
    public function dropshipOrderState()
    {
        $this->load->language('account/customer_order_import');
        $json['success'] = $this->language->get('text_dropship_process_success');
        return $this->response->json($json);

    }

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws Exception
     */
    public function dropshipFilePreserved()
    {
        set_time_limit(0);
        $runId = request('runId');
        $customer_id = customer()->getId();
        $import_mode = request('importMode');
        $country_id = customer()->getCountryId();
        $post = $this->request->post;
        load()->language('account/customer_order_import');
        load()->model('account/customer_order_import');
        $mapVerify = [
            ['create_user_name', '=', $customer_id],
            ['run_id', '=', $runId],
        ];
        $func = [
            HomePickImportMode::IMPORT_MODE_AMAZON => ['getDropshipUploadFileInfo'],
            HomePickImportMode::IMPORT_MODE_WAYFAIR => ['getWayfairUploadFileInfo', 'getEuropeWayfairUploadFileInfo'],
            HomePickImportMode::IMPORT_MODE_WALMART => ['getWalmartUploadFileInfo'],
            HomePickImportMode::US_OTHER => ['getUsPickUpOtherUploadFileInfo']
        ];
        $flag = HomePickLabelDetails::query()
            ->where($mapVerify)
            ->select('id')
            ->value('id');
        $upload_file_info = [];
        if (!$flag) {
            if (in_array($import_mode, [HomePickImportMode::IMPORT_MODE_AMAZON, HomePickImportMode::IMPORT_MODE_WALMART])) {
                $upload_file_info = call_user_func_array([$this->model_account_customer_order_import, $func[$import_mode][0]], [$runId, $customer_id]);
            } else {
                if (in_array($country_id, EUROPE_COUNTRY_ID)) {
                    $upload_file_info = call_user_func_array([$this->model_account_customer_order_import, $func[$import_mode][1]], [$runId, $customer_id]);
                } else if ($import_mode == HomePickImportMode::US_OTHER && $country_id == AMERICAN_COUNTRY_ID) {
                    //美国上门取货
                    $map = [
                        ['run_id', '=', $runId],
                        ['buyer_id', '=', $customer_id],
                    ];
                    $upload_file_info = call_user_func_array([$this->model_account_customer_order_import, $func[$import_mode][0]], [$map, 0]);
                } else {
                    $upload_file_info = call_user_func_array([$this->model_account_customer_order_import, $func[$import_mode][0]], [$runId, $customer_id]);
                }
            }
        }

        $labels = $this->model_account_customer_order_import->dropshipFileUploadDetails($post, $upload_file_info, $import_mode, $country_id);
        // 更改order中存在的combo的信息
        $this->model_account_customer_order_import->updatePurchaseOrderLineComboInfo(0, $customer_id, $runId);
        $json['msg'] = 'You have successfully uploaded ' . (int)$labels . ' label(s).';
        return $this->response->json($json);
    }


    /**
     * @deprecated
     * [checkIsRepeatForm description] 验证是否重复提交
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function checkIsRepeatForm()
    {

        $id = $this->request->get('id', 0);
        $runId = $this->request->get('runId', '');
        $customer_id = $this->customer->getId();

        if ($id > 0) {
            $mapVerify = [
                ['order_id', '=', $id],
                ['create_user_name', '=', $customer_id],
            ];
        } elseif ($runId > 0) {
            $mapVerify = [
                ['create_user_name', '=', $customer_id],
                ['run_id', '=', $runId],
            ];
        } else {
            $json['error'] = 1;
            goto end;

        }

        $flag = $this->orm->table('tb_sys_customer_sales_dropship_file_details')->where($mapVerify)->limit(1)->value('id');
        if ($flag) {
            $json['error'] = 1;
        } else {
            $json['error'] = 0;
        }
        end:
        return $this->response->json($json);

    }

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws Exception
     */
    public function walmartBolDeal()
    {
        load()->language('account/customer_order_import');
        load()->model('account/customer_order');
        /** @var Symfony\Component\HttpFoundation\File\UploadedFile $fileInfo */
        $fileInfo = request()->file('files');
        $container_id = request('container_id');
        //检测文件合法信息
        if( $fileInfo->isValid()){
            $fileType = $fileInfo->getClientOriginalExtension();
            if(!in_array(strtolower($fileType),self::PDF_SUFFIX)){
                $json['error'] = $this->language->get('error_filetype');
            }

            if ($fileInfo->getError() != UPLOAD_ERR_OK) {
                $json['error'] = $this->language->get('error_upload_' . $fileInfo->getError());
            }
        }else{
            $json['error'] = $this->language->get('error_upload');
        }
        //创建文件夹
        $dir_upload = 'dropshipPdf/' . date('Y-m-d', time()) . '/';
        if (!isset($json['error'])) {
            //放置文件
            $file_name = date('YmdHis', time()) . '_' . token(20) . '.pdf';
            $file_path = $dir_upload . $file_name;
            StorageCloud::storage()->writeFile(request()->filesBag->get('files'), $dir_upload, $file_name);
            //获取sku
            $order_info = $this->model_account_customer_order->getSkuAndQtyBySalesOrderId(
                current(explode('_', $container_id)),
                customer()->getId()
            );
            $sku = $order_info['sku'];
            $date_now = date('Y-m-d', time());
            $deal_file_path = $file_path;
            $ship_method_type = LTL_BOL_CUT_TYPES;
            $page = [];
            $page[0] = $page[1] = 1;

            $res = $this->getDealPdf($deal_file_path, $order_info['order_id'], $sku, $ship_method_type, $page);
            //$res = true;
            if ($res && $res !== true) {
                $json['error'] = 1;
            } else {
                //数据库保存数据 根据order_id 和 buyer_id run_id
                $customerSalesOrderInfo = CustomerSalesOrder::query()->where(
                        [
                            'id' => current(explode('_', $container_id)),
                            'buyer_id' => customer()->getId()
                        ]
                    )
                    ->select(['order_id', 'run_id'])
                    ->first();
                $fileData = [
                    "file_name" => str_replace(' ', '', $fileInfo->getClientOriginalName()),
                    "size" => $fileInfo->getSize(),
                    "file_path" => 'dropshipPdf/' . $date_now . '/' . $file_name,
                    'deal_file_path' => StorageCloud::storage()->getUrl($dir_upload . 'splitPdf/' . $file_name, ['check-exist' => false]),
                    'container_id' => $container_id,
                    'order_id' => $customerSalesOrderInfo->order_id,
                    'create_user_name' => customer()->getId(),
                    'create_time' => date("Y-m-d H:i:s"),
                    'run_id' => $customerSalesOrderInfo->run_id,
                ];
                $this->orm->table('tb_sys_customer_sales_dropship_upload_file')->insertGetId($fileData);
                $fileData['real_bol_path'] = StorageCloud::storage()->getUrl($file_path, ['check-exist' => false]);
                $json['error'] = 0;
                $json['data'] = $fileData;
            }

        }

        return $this->response->json($json);

    }

    /**
     * [wayfairBolDeal description] wayFair 中的 bol 上传
     * @return array
     * @throws Exception
     */
    public function wayfairBolDeal()
    {
        load()->language('account/customer_order_import');
        load()->model('account/customer_order');
        /** @var Symfony\Component\HttpFoundation\File\UploadedFile $fileInfo */
        $fileInfo = request()->file('files');
        $container_id = request('container_id');
        //检测文件合法信息
        if( $fileInfo->isValid()){
            $fileType = $fileInfo->getClientOriginalExtension();
            if(!in_array(strtolower($fileType),self::PDF_SUFFIX)){
                $json['error'] = $this->language->get('error_filetype');
            }

            if ($fileInfo->getError() != UPLOAD_ERR_OK) {
                $json['error'] = $this->language->get('error_upload_' . $fileInfo->getError());
            }
        }else{
            $json['error'] = $this->language->get('error_upload');
        }

        //创建文件夹
        $dir_upload = 'dropshipPdf/' . date('Y-m-d', time()) . '/';
        if (!isset($json['error'])) {
            //放置文件
            $file_name = date('YmdHis', time()) . '_' . token(20) . '.pdf';
            $file_path = $dir_upload . $file_name;
            StorageCloud::storage()->writeFile(request()->filesBag->get('files'), $dir_upload, $file_name);

            //获取sku
            $order_info = $this->model_account_customer_order->getSkuAndQtyBySalesOrderId(current(explode('_', $container_id)), $this->customer->getId());
            $sku = $order_info['sku'];
            $date_now = date('Y-m-d', time());
            $deal_file_path = $file_path;
            $ship_method_type = LTL_BOL_CUT_TYPES;
            $page = [];
            $page[0] = $page[1] = 1;

            $res = $this->getDealPdf($deal_file_path, $order_info['order_id'], $sku, $ship_method_type, $page);
            if ($res && $res !== true) {
                $json['error'] = 1;
            } else {
                //数据库保存数据 根据order_id 和 buyer_id run_id
                $customerSalesOrderInfo = CustomerSalesOrder::query()->where(
                        [
                            'id' => current(explode('_', $container_id)),
                            'buyer_id' => customer()->getId()
                        ]
                    )
                    ->select(['order_id', 'run_id'])
                    ->first();

                $fileData = [
                    "file_name" => str_replace(' ', '', $fileInfo->getClientOriginalName()),
                    "size" => $fileInfo->getSize(),
                    "file_path" => $file_path,
                    'deal_file_path' => StorageCloud::storage()->getUrl($dir_upload . 'splitPdf/' . $file_name, ['check-exist' => false]),
                    'container_id' => $container_id,
                    'order_id' => $customerSalesOrderInfo->order_id,
                    'create_user_name' => $this->customer->getId(),
                    'create_time' => date("Y-m-d H:i:s"),
                    'run_id' => $customerSalesOrderInfo->run_id,
                ];
                $this->orm->table('tb_sys_customer_sales_dropship_upload_file')->insertGetId($fileData);
                $fileData['real_bol_path'] = StorageCloud::storage()->getUrl($file_path, ['check-exist' => false]);
                $json['error'] = 0;
                $json['data'] = $fileData;

            }
        }
        $this->response->headers->set('Content-Type', 'application/json');
        $this->response->setOutput(json_encode($json));

    }


    /**
     * [dropshipFileDeal description] dropship pdf 上传
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws Exception
     */
    public function dropshipFileDeal()
    {
        load()->language('account/customer_order_import');
        load()->model('account/customer_order');
        /** @var Symfony\Component\HttpFoundation\File\UploadedFile $fileInfo */
        $fileInfo = request()->file('files');
        $container_id = request('container_id');
        $container_info = explode('_', $container_id);
        $json = [];
        //检测文件合法信息
        $ret = $this->model_account_customer_order_import->checkHomePickUploadFile($fileInfo);
        if (isset($ret['error'])) {
            return $this->response->json($json);
        }
        //创建文件夹
        $dir_upload = 'dropshipPdf/' . date('Y-m-d', time()) . '/';
        //放置文件
        $file_name = date('YmdHis', time()) . '_' . token(20) . '.pdf';
        $file_path = $dir_upload . $file_name;
        StorageCloud::storage()->writeFile(request()->filesBag->get('files'), $dir_upload, $file_name);
        //数据库保存数据 根据order_id 和 buyer_id run_id
        //walmart wayfair amazon 裁剪更改
        $purchaseOrderInfo = CustomerSalesOrder::query()
            ->where(
                [
                    'id' => $container_info[0],
                    'buyer_id' => customer()->getId()
                ]
            )
            ->select(['order_id', 'run_id', 'import_mode'])
            ->first();
        $fileData = [
            "file_name" => str_replace(' ', '', $fileInfo->getClientOriginalName()),
            "size" => $fileInfo->getSize(),
            "file_path" => $file_path,
            'container_id' => $container_id,
            'order_id' => $purchaseOrderInfo->order_id,
            'create_user_name' => customer()->getId(),
            'create_time' => date("Y-m-d H:i:s"),
            'run_id' => $purchaseOrderInfo->run_id,
        ];

        $import_mode = $purchaseOrderInfo->import_mode;
        $temp_info = $this->model_account_customer_order_import->getCarrierInfoFromTempTable($import_mode, $container_info[2]);
        $sku = $temp_info['sku'];
        $ship_method_code = $temp_info['ship_method_code'];
        $ship_method = $temp_info['ship_method'];
        $ship_speed = $temp_info['ship_speed'];
        //判断sku的 子sku  container_id 的第二个
        //判断是否为combo
        $comboExists = $this->model_account_customer_order_import->checkComboAmountBySku($sku);
        [$page, $ship_method_type, $label_type, $sku] = app(CustomerSalesOrderRepository::class)->getHomePickLabelInfo($container_info, $comboExists, $sku);
        $pathPrefix = 'dropshipPdf/' . date('Y-m-d', time()) . '/splitPdf/' . substr($file_name, 0, -4);
        $fileData['order_id_img'] = StorageCloud::storage()->getUrl($pathPrefix . '-orderId.png', ['check-exist' => false]);
        if ($label_type == HomePickLabelContainerType::STORE_LABEL) {
            //Store label 更改
            $fileData['package_asn_img'] = StorageCloud::storage()->getUrl($pathPrefix . '-packageAsn.png', ['check-exist' => false]);
            $fileData['store_id_img'] = StorageCloud::storage()->getUrl($pathPrefix . '-storeId.png', ['check-exist' => false]);
            $fileData['store_deal_file_path'] = StorageCloud::storage()->getUrl($dir_upload . 'splitPdf/' . $file_name, ['check-exist' => false]);
            $fileData['store_deal_file_name'] = str_replace(' ', '', $fileInfo->getClientOriginalName());
        } elseif ($label_type == HomePickLabelContainerType::COMMERCIAL_INVOICE) {

            $fileData['deal_file_path'] = StorageCloud::storage()->getUrl($dir_upload . 'splitPdf/' . $file_name, ['check-exist' => false]);
            $fileData['deal_file_name'] = str_replace(' ', '', $fileInfo->getClientOriginalName());
        } else {
            $fileData['tracking_number_img'] = StorageCloud::storage()->getUrl($pathPrefix . '-trackingNumber.png', ['check-exist' => false]);
            $fileData['weight_img'] = StorageCloud::storage()->getUrl($pathPrefix . '-weight.png', ['check-exist' => false]);
            $fileData['deal_file_path'] = StorageCloud::storage()->getUrl($dir_upload . 'splitPdf/' . $file_name, ['check-exist' => false]);
            $fileData['deal_file_name'] = str_replace(' ', '', $fileInfo->getClientOriginalName());
        }

        $insertId = $this->model_account_customer_order_import->insertDropshipUploadFile($fileData);
        //ship_method_code 进行确认
        //store label
        if ($label_type == HomePickLabelContainerType::COMMON) {
            $ship_method_type = $this->judgeShipMethodCode($ship_method_code, $ship_method, $import_mode, $ship_speed);
        }

        if ($ship_method_type == 0 ) {
            $json['error'] = 1;
            $json['msg'] = $this->language->get('error_file_size');
            $this->model_account_customer_order_import->updateDropshipUploadFile(['id' => $insertId], ['status' => 0]);
        } else {
            $res = $this->getDealPdf($fileData['file_path'], $fileData['order_id'], $sku, $ship_method_type, $page);
            if (!$res) {
                $json['error'] = 1;
                $json['msg'] =  $ship_method_type == HomePickCarrierType::EUROPE_WAYFAIR_COMMERCIAL_INVOICE ? __('不允许上传多页的商业发票。请重新上传',[], 'repositories/home_pick')
                    :$this->language->get('error_file_size');
                $this->model_account_customer_order_import->updateDropshipUploadFile(['id' => $insertId], ['status' => 0]);
            } else if (isset($res['error']) && $res['error'] == 2) {//文件大小问题
                $json['error'] = 2;
                $json['msg'] = $this->language->get('error_file_exceed');
                $this->model_account_customer_order_import->updateDropshipUploadFile(['id' => $insertId], ['status' => 0]);
            } else if (isset($res['error']) && $res['error'] == 1) {//尺寸问题
                $json['error'] = 2;
                $json['msg'] = $this->language->get('error_us_wayfair_file_size');
                $this->model_account_customer_order_import->updateDropshipUploadFile(['id' => $insertId], ['status' => 0]);
            } else {
                [$update, $json, $updateFlag] = app(CustomerSalesOrderRepository::class)->getUpdateInfoByCutResult(
                    $import_mode,
                    $ship_method_type,
                    $res,
                    $fileData,
                    $this->language->get('error_file_tracking_number')
                );
                if ($updateFlag) {
                    $this->model_account_customer_order_import->updateDropshipUploadFile(['id' => $insertId], $update);
                }
            }
        }

        return $this->response->json($json);

    }

    /**
     * @param string $file_path
     * @param string $order_id
     * @param string $sku
     * @param int $ship_method_type
     * @param array $page
     * @param string $carrier
     * @return array|bool
     */
    public function getDealPdf($file_path, $order_id, $sku, $ship_method_type, $page, $carrier = '')
    {
        $pdf_position = StorageCloud::storage()->getUrl($file_path, ['check-exist' => false]);
        $arr[] = [
            'pdfName' => $pdf_position,
            'orderId' => $order_id,
            'sku' => $sku,
            'type' => $ship_method_type,
            'current_page' => $page[0],
            'total_page' => $page[1],
            'carrier' => $carrier,
        ];
        $data['data'] = json_encode($arr, JSON_UNESCAPED_SLASHES);
        $json['postValue'] = json_encode($data, JSON_UNESCAPED_SLASHES);
        switch (ENV_DROPSHIP_YZCM) {
            case 'dev_17':
            case 'dev_35':
                $headers = [
                    'Authorization:Basic YWRtaW46IVFBWnhzdzIyMDIx',
                ];
                $auth = self::YZCM_AUTH_TEST;
                break;
            case 'pro':
                $headers = [
                    'Authorization:Basic eXpjbUFwaTp5emNtQXBpQDIwMTkwNTE1',
                ];
                $auth = null;
                break;
            default:
                $headers = [
                    'Authorization:Basic YWRtaW46IVFBWnhzdzIyMDIx',
                ];
                $auth = self::YZCM_AUTH_TEST;

        }
        // 下个迭代版本改成RemoteApi
        $url = URL_YZCM . '/api/splitDropshipPdf';
        $json = http_build_query($json);
        $res = post_url($url, $json, $headers, null, $auth);
        $res = json_decode($res, true);
        // 记录log日志
        Logger::salesOrder(['裁剪label', 'info',
            Logger::CONTEXT_VAR_DUMPER => ['res' => $res, 'json' => $json],
        ]);
        if (isset($res['success']) && $res['success'] != '') {
            $data = json_decode($res['data'], true);
            if (isset($data[$order_id]) && trim($data[$order_id]) != '') {
                return $data[$order_id];
            } else {
                return true;
            }

        }
        //java返回错误提示 1.尺寸不是4*6  2.文件大小不能超过1M
        if (isset($res['result']) && in_array($res['result'], [1, 2])) {
            return ['error' => $res['result']];
        }
        return false;
    }

    /**
     * [judgeShipMethodCode description] 进行ups 和arrow的确认
     * @param string $shipMethodCode
     * @param string $ship_method
     * @param int $import_mode
     * @param null $ship_speed
     * @return int|mixed|string
     */
    public function judgeShipMethodCode($shipMethodCode, $ship_method, $import_mode = 4, $ship_speed = null)
    {
        $country_id = $this->customer->getCountryId();
        // import mode 5 wayfair else import mode 4 import mode 7 walmart
        if ($import_mode == HomePickImportMode::IMPORT_MODE_WALMART) {
            // walmart 比较粗暴直接比对
            $tmp = WALMART_CUT_TYPES;
            $carrier = $shipMethodCode == '' ? $ship_method : $shipMethodCode;
            foreach (WALMART_VERIFY_TYPES as $ks => $vs) {
                if (strtolower($carrier) == strtolower($vs)) {
                    if (isset($tmp[$vs])) {
                        return $tmp[$vs];
                    }
                }
            }

            return 0;

        } else if ($import_mode == HomePickImportMode::IMPORT_MODE_WAYFAIR) {
            $type = WAYFAIR_VERIFY_TYPES;
            $speed_type = WAYFAIR_FEDEX_TYPES;
            if (in_array($country_id, EUROPE_COUNTRY_ID)) {
                foreach (WAYFAIR_EUROPE_VERIFY_TYPES as $key => $value) {
                    if (strtoupper($shipMethodCode) == strtoupper($value)) {
                        return $key + 29;
                    }
                }

                return 29;

            } else {
                foreach ($type as $key => $value) {
                    if (stripos($shipMethodCode, $value) !== false) {
                        if ($key == 3) {
                            //Fedex 需要单独验证是否是Next Day Air
                            foreach ($speed_type as $ks => $vs) {
                                if (stripos($ship_speed, $vs) !== false) {
                                    return $key + 5;
                                }
                            }

                        }
                        return $key + 7;
                    }
                }
            }

        } else {
            //UPS 中需要验证 UPS Surepost GRD Parcel UPS中的大件
            //14310 线上增加AFB和CEVA提货方式
            $type = LOGISTICS_TYPES;
            unset($type[0]);
            //krsort($type);
            foreach ($type as $key => $value) {
                if (stripos($shipMethodCode, $value) !== false || stripos($ship_method, $value) !== false) {
                    if ($key == 1) {
                        if (stripos($shipMethodCode, $type[5]) !== false || stripos($ship_method, $type[5]) !== false) {
                            return $key + 4;
                        }
                    }
                    return $key;
                }
            }
            return 0;
        }


    }

    /**
     * [dropshipFileUnlink description] 上传的pdf软删除 其实就是 state 置为0
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function dropshipFileUnlink()
    {
        load()->language('account/customer_order_import');
        $container_id = $this->request->post('container_id');
        $map = [
            ['container_id', '=', $container_id], //container_id 唯一的
            ['status', '=', 1],
        ];
        $res = $this->orm->table('tb_sys_customer_sales_dropship_upload_file')->where($map)->orderBy('id', 'desc')->limit(1)->update(['status' => 0]);

        if ($res != 1)
            $res = $this->language->get('error_delete');

        return $this->response->json($res);


    }


    /**
     * [dropshipFileCheck description] 进度条验证 不需要暂时
     */
    public function dropshipFileCheck()
    {

        $container_id = $this->request->get('container_id');
        if (isset($this->session->data['dropship_file_deal'][$container_id])) {
            $this->response->headers->set('Content-Type', 'application/json');
            $data = $this->session->data['dropship_file_deal'][$container_id];
            $this->session->removeDeepByKey('dropship_file_deal', $container_id);
            $this->response->setOutput(json_encode($data));
        } else {
            //伪造一个成功的但是时间长的
            $temp['bytesRead'] = 102516060;
            $temp['contentLength'] = 102516060;
            $temp['items'] = 1;
            $temp['percent'] = 100;
            $temp['startTime'] = time() * 1000;
            $temp['userTime'] = 1000; // 3s
            $this->response->setOutput(json_encode($temp));
        }


    }

    /**
     * [dropShipModalBoxShow description]
     * @throws Throwable
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * date:2020/11/16 14:22
     */
    public function dropShipModalBoxShow()
    {
        load()->language('account/customer_order_import');
        load()->model('account/customer_order_import');
        load()->model('extension/module/price');
        /** @var ModelExtensionModulePrice $priceModel */
        $priceModel = $this->model_extension_module_price;

        $country_id = $this->customer->getCountryId();
        $customer_id = $this->customer->getId();
        $data = array();
        // 运行RunId
        $runId = $this->request->get('runId');
        // ImportMode 4
        //$importMode = $this->request->get('importMode');
        // 取缓存数据
        $hasNoProductArr = $this->cache->get($customer_id . '_' . $runId . '_hasNoProductArr');
        $hasNoCostArr = $this->cache->get($customer_id . '_' . $runId . '_hasNoCostArr', []);
        $productArr = $this->cache->get($customer_id . '_' . $runId . '_productArr', []);
        //        $overSizeArr = $this->cache->get($customer_id.'_'.$runId.'_overSizeArr');
        //13827【需求】Reorder页面和购买弹窗中的不存在在平台上的SKU表格拆分成未建立关联的SKU和平台上不存在的SKU两个表格
        $hasNoExistProductArr = $this->cache->get($customer_id . '_' . $runId . '_hasNoExistProductArr');
        //add by xxli copy by allen.tai
        $costArr = array();
        foreach ($hasNoCostArr as $key => &$hasNoCost) {
            //通过产品获取信息
            $hasNoCost['product_id'] = $hasNoCost['sellerArr'][0]['product_id'];
            $transaction_info = $priceModel->getProductPriceInfo($hasNoCost['product_id'], $customer_id, [], false, true);
            $selection = 0;
            if ($transaction_info['first_get']['type'] == ProductTransactionType::NORMAL) {
                $hasNoCost['price'] = $transaction_info['first_get']['price_show'];
                $hasNoCost['freight'] = $transaction_info['first_get']['freight_show'];
                if ($transaction_info['base_info']['unavailable'] == 1 || $transaction_info['base_info']['buyer_flag'] == 0) {
                    $transaction_info['base_info']['status'] = 0;
                }
                $hasNoCost['p_status'] = $transaction_info['base_info']['status'];
                $hasNoCost['status'] = $transaction_info['base_info']['status'];
                if ($hasNoCost['status'] == 0) {
                    $hasNoCost['quantity'] = 0;
                } else {
                    $hasNoCost['quantity'] = $transaction_info['base_info']['quantity'];
                }
                $selection = 0;
            } elseif ($transaction_info['first_get']['type'] == ProductTransactionType::REBATE) {

                $hasNoCost['price'] = $transaction_info['first_get']['price_show'];
                $hasNoCost['freight'] = $transaction_info['base_info']['freight_show'];
                $hasNoCost['p_status'] = $transaction_info['base_info']['status'];
                $hasNoCost['status'] = $transaction_info['base_info']['status'];
                if ($hasNoCost['status'] == 0) {
                    $hasNoCost['quantity'] = 0;
                } else {
                    $hasNoCost['quantity'] = $transaction_info['base_info']['quantity'];
                }
                $selection = $transaction_info['first_get']['id'];

            } elseif ($transaction_info['first_get']['type'] == ProductTransactionType::MARGIN) {
                $hasNoCost['price'] = $transaction_info['first_get']['price_show'];
                $hasNoCost['freight'] = $transaction_info['base_info']['freight_show'];
                $hasNoCost['p_status'] = $transaction_info['base_info']['status'];
                $hasNoCost['status'] = $transaction_info['base_info']['status'];
                if ($hasNoCost['status'] == 0) {
                    $hasNoCost['quantity'] = 0;
                } else {
                    $hasNoCost['quantity'] = $transaction_info['first_get']['left_qty'];
                }
                $selection = $transaction_info['first_get']['id'];

            } elseif ($transaction_info['first_get']['type'] == ProductTransactionType::FUTURE) {
                $hasNoCost['price'] = $transaction_info['first_get']['price_show'];
                $hasNoCost['freight'] = $transaction_info['base_info']['freight_show'];
                $hasNoCost['p_status'] = $transaction_info['base_info']['status'];
                $hasNoCost['status'] = $transaction_info['base_info']['status'];
                if ($hasNoCost['status'] == 0) {
                    $hasNoCost['quantity'] = 0;
                } else {
                    $hasNoCost['quantity'] = $transaction_info['first_get']['left_qty'];
                }
                $selection = $transaction_info['first_get']['id'];

            } elseif ($transaction_info['first_get']['type'] == ProductTransactionType::SPOT) {
                $hasNoCost['price'] = $transaction_info['first_get']['price_show'];
                $hasNoCost['freight'] = $transaction_info['base_info']['freight_show'];
                $hasNoCost['p_status'] = $transaction_info['base_info']['status'];
                $hasNoCost['status'] = $transaction_info['base_info']['status'];
                if ($hasNoCost['status'] == 0) {
                    $hasNoCost['quantity'] = 0;
                } else {
                    $hasNoCost['quantity'] = $transaction_info['first_get']['left_qty'];
                }
                $selection = $transaction_info['first_get']['id'];

            }
            $selectItem = [];
            //构造一下select框

            $selectItem[] = [
                'key' => 0,
                'value' => 'Normal Transaction',
                'selected' => $selection == 0 ? 1 : 0,
            ];
            foreach ($transaction_info['transaction_type'] as $item) {
                if ($item['type'] == ProductTransactionType::REBATE) {
                    $vItem = 'Rebate:' . $item['agreement_code'];
                } elseif ($item['type'] == ProductTransactionType::MARGIN) {
                    $vItem = 'Margin:' . $item['agreement_code'];
                } elseif ($item['type'] == ProductTransactionType::FUTURE) {
                    continue;
                    $vItem = 'Future Goods:' . $item['agreement_code'];
                } else {
                    $vItem = 'Spot:' . $item['agreement_code'];
                }
                $selectItem[] = [
                    'key' => $item['id'] . '_' . $item['type'],
                    'value' => $vItem,
                    'selected' => $selection == $item['id'] ? 1 : 0,
                ];
            }

            $hasNoCost['transaction_type'] = $selectItem;
            //$result = $this->model_account_customer_order_import->getPriceAndSellerNameByProductId($hasNoCost['product_id']);
            //$hasNoCost['price'] = $this->calculatePrice($hasNoCost['product_id'], $result['price']);
            //if ($country_id) {
            //    if ($this->customer->getGroupId() == 13) {
            //        $hasNoCost['price'] = $this->country->getDisplayPrice($country_id, $hasNoCost['price']);
            //    }
            //}
            //// JAPAN_COUNTRY_ID 日本 price 控制
            //if ($country_id == JAPAN_COUNTRY_ID) {
            //    $hasNoCost['price'] = round($hasNoCost['price']);
            //    $hasNoCost['freight'] = round($result['freight']);
            //} else {
            //    $hasNoCost['price'] = round($hasNoCost['price'], 2);
            //    $hasNoCost['freight'] = round($result['freight'],2);
            //}
            //$hasNoCost['seller_name'] = $result['screenname'];
            //// 14039下架产品在Sales Order Management功能中隐藏价格
            //// Item Code 为下架、废弃的Item Code
            //if($result['buyer_flag'] == 0){
            //    $result['p_status'] = 0;
            //    $result['status'] = 0;
            //}
            //$hasNoCost['p_status'] = $result['p_status'];
            //if ($result['status'] == 0) {
            //    $hasNoCost['quantity'] = 0;
            //} else {
            //    $hasNoCost['quantity'] = $result['quantity'];
            //}
            //$hasNoCost['status'] = $result['status'];

            //查看 有没有相同itemcode的neworder 订单
            $numbers = $this->model_account_customer_order_import->countItemCodeFromOrder($hasNoCost['item_code'], $runId);
            foreach ($numbers as $index => $number) {
                if ($hasNoCost['order_id'] == $number['order_id']) {
                    $hasNoCost['previousItemCodeNum'] = $index;
                }
            }
            array_push($costArr, $hasNoCost);
        }
        if (count($hasNoProductArr)) {
            $data['hasNoProductArr'] = $hasNoProductArr;
        }
        //13827【需求】Reorder页面和购买弹窗中的不存在在平台上的SKU表格拆分成未建立关联的SKU和平台上不存在的SKU两个表格
        if (count($hasNoExistProductArr)) {
            $data['hasNoExistProductArr'] = $hasNoExistProductArr;
        }
        if (count($hasNoCostArr)) {
            //            $data['hasNoCostArr'] = $hasNoCostArr;
            $data['hasNoCostArr'] = $costArr;
        }
        $emptyFlag = false;
        $pendingChargesProductArr = [];
        foreach ($productArr as $arrKey => $product) {
            if (count($product)) {
                foreach ($product as $proKey => $pro) {
                    if ($pro['order_status'] == CustomerSalesOrderStatus::PENDING_CHARGES) {
                        $pro['order_status_name'] = CustomerSalesOrderStatus::getDescription($pro['order_status']);
                        //pending charges需要单独拎出来显示
                        //需要查询仓租费
                        $pro['storage_fee_unpaid'] = app(StorageFeeRepository::class)->getBoundSalesOrderNeedPay($pro['header_id'], $pro['id']);
                        $pendingChargesProductArr[$arrKey][] = $pro;
                        unset($product[$proKey]);
                    }
                }
                if (count($product) > 0) {
                    $emptyFlag = true;
                } else {
                    unset($productArr[$arrKey]);
                }
            }
        }
        //有一个待支付费用单的情况，关闭后就要跳转到销售订单页面
        $data['is_need_pay_fee'] = intval(!empty($pendingChargesProductArr));
        if ($emptyFlag) {
            $data['productArr'] = $productArr;
        }
        $data['pendingChargesProductArr'] = $pendingChargesProductArr;
        $this->cache->delete($customer_id . '_' . $runId . '_hasNoProductArr');
        $this->cache->delete($customer_id . '_' . $runId . '_hasNoCostArr');
        $this->cache->delete($customer_id . '_' . $runId . '_productArr');
        $this->cache->delete($customer_id . '_' . $runId . '_hasNoExistProductArr');
        $data['exception_list'] = [];
        $data['currency'] = $this->session->get('currency');
        //是否有shpping信息（因自提货没有shipping的信息，用于页面判断)
        $data['existsShippingInfo'] = $this->request->get('importMode') != HomePickImportMode::IMPORT_MODE_BUYER_PICK_UP ? 1 : 0;
        $this->response->setOutput(load()->view('account/corder_import_modal', $data));


    }

    /**
     * @throws Throwable
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function modalBoxShow()
    {
        load()->language('account/customer_order_import');
        load()->model('account/customer_order_import');
        load()->model('account/deliverySignature');
        load()->language('account/deliverySignature');
        load()->model('extension/module/price');
        /** @var ModelExtensionModulePrice $priceModel */
        $priceModel = $this->model_extension_module_price;
        $country_id = $this->customer->getCountryId();
        $data = array();
        // 运行RunId
        $runId = $this->request->get('runId');
        // ImportMode
        //$importMode = $this->request->get('importMode');
        // 取缓存数据
        $hasNoProductArr = $this->cache->get($runId . "hasNoProductArr");
        $hasNoCostArr = $this->cache->get($runId . "hasNoCostArr");
        $productArr = $this->cache->get($runId . "productArr");
        $oversizeArr = $this->cache->get($runId . "overSizeArr");
        //13827【需求】Reorder页面和购买弹窗中的不存在在平台上的SKU表格拆分成未建立关联的SKU和平台上不存在的SKU两个表格
        $hasNoExistProductArr = $this->cache->get($runId . 'hasNoExistProductArr');
        //add by xxli
        $costArr = array();
        foreach ($hasNoCostArr as $hasNoCost) {
            $hasNoCost['product_id'] = $hasNoCost['sellerArr'][0]['product_id'];
            $transaction_info = $priceModel->getProductPriceInfo($hasNoCost['product_id'], $this->customer->getId(), [], false, true);
            $selection = 0;
            if ($transaction_info['first_get']['type'] == ProductTransactionType::NORMAL) {
                $hasNoCost['price'] = $transaction_info['first_get']['price_show'];
                $hasNoCost['freight'] = $transaction_info['first_get']['freight_show'];
                if ($transaction_info['base_info']['unavailable'] == 1 || $transaction_info['base_info']['buyer_flag'] == 0) {
                    $transaction_info['base_info']['status'] = 0;
                }
                $hasNoCost['p_status'] = $transaction_info['base_info']['status'];
                $hasNoCost['status'] = $transaction_info['base_info']['status'];
                if ($hasNoCost['status'] == 0) {
                    $hasNoCost['quantity'] = 0;
                } else {
                    $hasNoCost['quantity'] = $transaction_info['base_info']['quantity'];
                }
                $selection = 0;
            } elseif ($transaction_info['first_get']['type'] == ProductTransactionType::REBATE) {

                $hasNoCost['price'] = $transaction_info['first_get']['price_show'];
                $hasNoCost['freight'] = $transaction_info['base_info']['freight_show'];
                $hasNoCost['p_status'] = $transaction_info['base_info']['status'];
                $hasNoCost['status'] = $transaction_info['base_info']['status'];
                if ($hasNoCost['status'] == 0) {
                    $hasNoCost['quantity'] = 0;
                } else {
                    $hasNoCost['quantity'] = $transaction_info['base_info']['quantity'];
                }
                $selection = $transaction_info['first_get']['id'];

            } elseif ($transaction_info['first_get']['type'] == ProductTransactionType::MARGIN) {
                $hasNoCost['price'] = $transaction_info['first_get']['price_show'];
                $hasNoCost['freight'] = $transaction_info['base_info']['freight_show'];
                $hasNoCost['p_status'] = $transaction_info['base_info']['status'];
                $hasNoCost['status'] = $transaction_info['base_info']['status'];
                if ($hasNoCost['status'] == 0) {
                    $hasNoCost['quantity'] = 0;
                } else {
                    $hasNoCost['quantity'] = $transaction_info['first_get']['left_qty'];
                }
                $selection = $transaction_info['first_get']['id'];

            } elseif ($transaction_info['first_get']['type'] == ProductTransactionType::FUTURE) {
                $hasNoCost['price'] = $transaction_info['first_get']['price_show'];
                $hasNoCost['freight'] = $transaction_info['base_info']['freight_show'];
                $hasNoCost['p_status'] = $transaction_info['base_info']['status'];
                $hasNoCost['status'] = $transaction_info['base_info']['status'];
                if ($hasNoCost['status'] == 0) {
                    $hasNoCost['quantity'] = 0;
                } else {
                    $hasNoCost['quantity'] = $transaction_info['first_get']['left_qty'];
                }
                $selection = $transaction_info['first_get']['id'];

            } elseif ($transaction_info['first_get']['type'] == ProductTransactionType::SPOT) {
                $hasNoCost['price'] = $transaction_info['first_get']['price_show'];
                $hasNoCost['freight'] = $transaction_info['base_info']['freight_show'];
                $hasNoCost['p_status'] = $transaction_info['base_info']['status'];
                $hasNoCost['status'] = $transaction_info['base_info']['status'];
                if ($hasNoCost['status'] == 0) {
                    $hasNoCost['quantity'] = 0;
                } else {
                    $hasNoCost['quantity'] = $transaction_info['first_get']['left_qty'];
                }
                $selection = $transaction_info['first_get']['id'];

            }
            $selectItem = [];
            //构造一下select框

            $selectItem[] = [
                'key' => 0,
                'value' => 'Normal Transaction',
                'selected' => $selection == 0 ? 1 : 0,
            ];
            foreach ($transaction_info['transaction_type'] as $item) {
                if ($item['type'] == ProductTransactionType::REBATE) {
                    $vItem = 'Rebate:' . $item['agreement_code'];
                } elseif ($item['type'] == ProductTransactionType::MARGIN) {
                    $vItem = 'Margin:' . $item['agreement_code'];
                } elseif ($item['type'] == ProductTransactionType::FUTURE) {
                    $vItem = 'Future Goods:' . $item['agreement_code'];
                } else {
                    $vItem = 'Spot:' . $item['agreement_code'];
                }
                $selectItem[] = [
                    'key' => $item['id'] . '_' . $item['type'],
                    'value' => $vItem,
                    'selected' => $selection == $item['id'] ? 1 : 0,
                ];
            }

            $hasNoCost['transaction_type'] = $selectItem;
            //$result = $this->model_account_customer_order_import->getPriceAndSellerNameByProductId($hasNoCost['product_id']);
            //$hasNoCost['price'] = $this->calculatePrice($hasNoCost['product_id'], $result['price']);
            //if ($country_id) {
            //    if ($this->customer->getGroupId() == 13) {
            //        $hasNoCost['price'] = $this->country->getDisplayPrice($country_id, $hasNoCost['price']);
            //    }
            //}
            //if ($country_id == JAPAN_COUNTRY_ID) {
            //    $hasNoCost['price'] = round($hasNoCost['price']);
            //    $hasNoCost['freight'] = round($result['freight']);
            //} else {
            //    $hasNoCost['price'] = round($hasNoCost['price'], 2);
            //    $hasNoCost['freight'] = round($result['freight'],2);
            //}
            //$hasNoCost['seller_name'] = $result['screenname'];
            //// 14039下架产品在Sales Order Management功能中隐藏价格
            //if($result['buyer_flag'] == 0){
            //    $result['p_status'] = 0;
            //    $result['status'] = 0;
            //}
            //$hasNoCost['p_status'] = $result['p_status'];
            //if ($result['status'] == 0) {
            //    $hasNoCost['quantity'] = 0;
            //} else {
            //    $hasNoCost['quantity'] = $result['quantity'];
            //}
            //$hasNoCost['status'] = $result['status'];

            //查看 有没有相同itemcode的neworder 订单
            $numbers = $this->model_account_customer_order_import->countItemCodeFromOrder($hasNoCost['item_code'], $runId);
            foreach ($numbers as $index => $number) {
                if ($hasNoCost['order_id'] == $number['order_id']) {
                    $hasNoCost['previousItemCodeNum'] = $index;
                }
            }
            array_push($costArr, $hasNoCost);
        }

        $asr_order_ids = $this->model_account_deliverySignature->getUnPaidAsrOrderByImportRunId($this->customer->getId(), $runId);
        if (isset($asr_order_ids) && !empty($asr_order_ids)) {
            $data['asr_order_ids'] = sprintf($this->language->get('text_success_notice'), implode("],[", $asr_order_ids));
        }
        //end
        if (count($hasNoProductArr)) {
            $data['hasNoProductArr'] = $hasNoProductArr;
        }
        if (count($hasNoCostArr)) {
            //            $data['hasNoCostArr'] = $hasNoCostArr;
            $cost_column = ['ship_address'];
            foreach ($costArr as $key => $value) {
                foreach ($cost_column as $ks => $vs) {
                    $s = $this->model_account_customer_order_import->dealErrorCode($value[$vs]);
                    if ($s != false) {
                        $costArr[$key][$vs] = $s;
                    }
                }

            }
            $data['hasNoCostArr'] = $costArr;
        }
        $emptyFlag = false;
        foreach ($productArr as $product) {
            if (count($product)) {
                $emptyFlag = true;
                break;
            }
        }
        if ($emptyFlag) {

            $product_column = ['ship_address'];
            foreach ($productArr as $key => $value) {
                foreach ($value as $k => $v) {
                    foreach ($product_column as $ks => $vs) {
                        $s = $this->model_account_customer_order_import->dealErrorCode($v[$vs]);
                        if ($s !== false) {
                            $productArr[$key][$k][$vs] = $s;
                        }
                    }
                }
            }
            $data['productArr'] = $productArr;
        }
        if (count($oversizeArr)) {
            //处理一下显示的栏位
            $oversize_column = ['ship_phone', 'ship_address1', 'ship_state', 'ship_city'];
            foreach ($oversizeArr as $key => $value) {
                foreach ($oversize_column as $ks => $vs) {
                    $s = $this->model_account_customer_order_import->dealErrorCode($value[$vs]);
                    if ($s !== false) {
                        $oversizeArr[$key][$vs] = $s;
                    }
                }

            }
            $data['overSizeArr'] = $oversizeArr;
        }
        //13827【需求】Reorder页面和购买弹窗中的不存在在平台上的SKU表格拆分成未建立关联的SKU和平台上不存在的SKU两个表格
        if (count($hasNoExistProductArr)) {
            $data['hasNoExistProductArr'] = $hasNoExistProductArr;
        }

        //N-699 获取乱码的字段
        $order_column = $this->cache->get($this->customer->getId() . '_' . $runId . '_column_exception');
        //标红
        //订单状态获取
        $exception_list = [];
        foreach ($order_column as $key => $value) {
            $status = $this->model_account_customer_order_import->getCommonOrderStatus($key, $runId);
            foreach ($value as $ks => $vs) {
                $tmp['sales_order_id'] = $key;
                $tmp['field'] = $ks;
                $tmp['content'] = $vs['position'];
                $tmp['order_status'] = $status[0]['order_status'];
                $tmp['order_status_value'] = $status[0]['DicValue'];

                $exception_list[] = $tmp;
            }

        }
        $data['exception_list'] = $exception_list;
        $this->cache->delete($runId . "hasNoProductArr");
        $this->cache->delete($runId . "hasNoCostArr");
        $this->cache->delete($runId . "productArr");
        $this->cache->delete($runId . "hasNoExistProductArr");
        $this->cache->delete($runId . "oversizeArr");
        $this->cache->delete($this->customer->getId() . '_' . $runId . '_column_exception');
        $this->response->setOutput(load()->view('account/corder_import_modal', $data));
    }

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws Exception
     */
    public function addProductToCart()
    {
        $productDatas = $this->request->post('productDatas', []);
        $json = array();
        load()->model('catalog/product');
        load()->language('checkout/cart');
        load()->model('checkout/cart');
        /**
         * @var ModelCheckoutCart $cart_model
         */
        $cart_model = $this->model_checkout_cart;
        load()->model("account/customer_order");
        // 简单的验证产品中是否含有两种及其以上的交易类型
        $product_verify = [];
        foreach ($productDatas as $key => $value) {
            if (isset($product_verify[$value['productId']])) {
                if ($product_verify[$value['productId']] != $value['transaction_type']) {
                    $json['warning'] = $this->language->get('error_transaction_add_cart');
                }
            } else {
                $product_verify[$value['productId']] = $value['transaction_type'];
            }
        }
        if (!isset($json['warning'])) {
            foreach ($productDatas as $productData) {
                //判断该销售订单是否已取消
                if (isset($productData['orderLineId'])) {
                    $order_status = $this->model_account_customer_order->getOrderStatusByOrderLineId($productData['orderLineId']);
                    if ($order_status != 1) {
                        $json['warning'] = 'This sales order\'s status is changed,can not add to cart!';
                        break;
                    }
                }
                $product_info = $this->model_catalog_product->getProduct($productData['productId']);
                if ($product_info
                    && $product_info['status'] == 1
                    && $product_info['dm_display'] == 1) {
                    $quantity = (int)$productData['qty'];
                    $option = array();

                    $product_options = $this->model_catalog_product->getProductOptions($productData['productId']);

                    foreach ($product_options as $product_option) {
                        if ($product_option['required'] && empty($option[$product_option['product_option_id']])) {
                            $json['error']['option'][$product_option['product_option_id']] = sprintf($this->language->get('error_required'), $product_option['name']);
                        }
                    }

                    $recurring_id = 0;

                    $recurrings = $this->model_catalog_product->getProfiles($product_info['product_id']);

                    if ($recurrings) {
                        $recurring_ids = array();
                        foreach ($recurrings as $recurring) {
                            $recurring_ids[] = $recurring['recurring_id'];
                        }
                        if (!in_array($recurring_id, $recurring_ids)) {
                            $json['error']['recurring'] = $this->language->get('error_recurring_required');
                        }
                    }


                    $agreement_id = null;
                    $type = 0;
                    if (isset($productData['transaction_type'])) {
                        if ($productData['transaction_type'] != 0) {
                            $info = explode('_', $productData['transaction_type']);
                            $agreement_id = $info[0];
                            //验证协议是否失效
                            $type = $info[1];
                            $transaction_info = $this->cart->getTransactionTypeInfo($type, $agreement_id, $productData['productId']);
                            if (!$transaction_info) {
                                // 获取agreement id
                                $agreement_code = $cart_model->getTransactionTypeInfo($type, $agreement_id, $productData['productId']);
                                $json['warning'] = sprintf($this->language->get('error_expire_time_add_cart'), $agreement_code);
                            }

                        } else {
                            //验证是否是保证金头款
                            $map = [
                                'process_status' => 1,
                                'advance_product_id' => $productData['productId'],
                            ];
                            $agreement_id = $this->orm->table('tb_sys_margin_process')->where($map)->value('margin_id');
                            if ($agreement_id) {
                                $type = 2;
                            }
                        }
                    } else {
                        $map = [
                            'process_status' => 1,
                            'advance_product_id' => $productData['productId'],
                        ];
                        $agreement_id = $this->orm->table('tb_sys_margin_process')->where($map)->value('margin_id');
                        if ($agreement_id) {
                            $type = 2;
                        }

                    }
                    //新增了逻辑相同产品的不同交易方式不允许同时添加购物车
                    $count = $cart_model->verifyProductAdd($productData['productId'], $type, $agreement_id);
                    if ($count) {
                        $json['warning'] = $this->language->get('error_transaction_add_cart');
                    } else {
                        $cart_model->add($productData['productId'], $quantity, $option, $recurring_id, $type, $agreement_id);
                    }
                } else {
                    $json['warning'] = $this->language->get('text_message');
                    break;
                }
            }
        }
        // 跳转购物车
        $json['cart'] = url()->to(['checkout/cart']);
        return $this->response->json($json);
    }

    /**
     * @deprecated
     * @param $customerSalesOrderLine
     * @return array
     */
    public function getProductArrayEntity($customerSalesOrderLine)
    {
        return $productData = array(
            'product_description' =>
                array(
                    1 =>
                        array(
                            'name' => $customerSalesOrderLine['product_name'],
                            'description' => '',
                            'meta_title' => $customerSalesOrderLine['product_name'],
                            'meta_description' => '',
                            'meta_keyword' => '',
                            'tag' => '',
                        ),
                ),
            'model' => 'YZC',
            'sku' => '',
            'upc' => '',
            'ean' => '',
            'jan' => '',
            'isbn' => '',
            'mpn' => $customerSalesOrderLine['item_code'],
            'location' => '',
            'price' => '',
            'tax_class_id' => '0',
            'quantity' => '1',
            'minimum' => '1',
            'subtract' => '1',
            'stock_status_id' => '6',
            'shipping' => '1',
            'date_available' => date('Y-m-d'),
            'length' => '',
            'width' => '',
            'height' => '',
            'length_class_id' => '1',
            'weight' => '',
            'weight_class_id' => '1',
            'status' => '1',
            'sort_order' => '1',
            'manufacturer' => '',
            'manufacturer_id' => '0',
            'category' => '',
            'filter' => '',
            'product_store' =>
                array(
                    0 => '0',
                ),
            'download' => '',
            'related' => '',
            'option' => '',
            'image' => '',
            'points' => '',
            'product_reward' =>
                array(
                    1 =>
                        array(
                            'points' => '',
                        ),
                ),
            'product_seo_url' =>
                array(
                    0 =>
                        array(
                            1 => '',
                        ),
                ),
            'product_layout' =>
                array(
                    0 => '',
                ),
        );
    }

    /**
     * 下载订单模板文件
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function downloadTemplateFile()
    {
        $path = "download/OrderTemplateCSV.csv";
        return $this->response->download('storage/' . $path);
    }


    /**
     * 下载订单模板文件
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function downloadAmazonTemplateFile()
    {
        $path = '';
        $country_id = $this->customer->getCountryId();
        if ($country_id == HomePickUploadType::BRITAIN_COUNTRY_ID) {
            $path = 'download/Amazon-Giga pickup orders-UK.csv';
        } elseif ($country_id == AMERICAN_COUNTRY_ID) {
            $path = "download/Amazon-Giga pickup orders-US.csv";
        }

        return $this->response->download('storage/' . $path);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function downloadOtherTemplateFile()
    {
        $path = 'download/dp/General Pickup Orders Template.xls';
        return $this->response->download('storage/' . $path);
    }

    /**
     * 自提货模板下载
     * @return BinaryFileResponse
     */
    public function downloadBuyerPickUpTemplate()
    {
        $path = DIR_DOWNLOAD . "Buyer Pick-up - Template.xlsx";
        return $this->response->download($path);
    }

    /**
     * 下载订单导入模板 wayfair walmart
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function downloadDPTemplateFile()
    {
        $type = $this->request->get('type');
        $country_id = $this->customer->getCountryId();
        if ($type == 'wayfair') {
            if ($country_id == AMERICAN_COUNTRY_ID) {
                $path = 'download/Wayfair-Giga pickup orders-US.csv';
            } elseif ($country_id == HomePickUploadType::BRITAIN_COUNTRY_ID) {
                $path = 'download/Wayfair-Giga pickup orders-UK.csv';
            } elseif ($country_id == HomePickUploadType::GERMANY_COUNTRY_ID) {
                $path = 'download/Wayfair-Giga pickup orders-DE.csv';
            } else {
                $path = 'download/Wayfair-Giga pickup orders-US.csv';
            }
        } else {
            $path = 'download/Walmart-Giga pickup orders-US.xls';
        }
        return $this->response->download('storage/' . $path);
    }

    /**
     * [otherInstructionHref description]
     * @return string
     */
    public function otherInstructionHref()
    {
        load()->language('account/customer_order_import');
        $data['title'] = $this->language->get('text_heading_title_other_instruction');
        return $this->render('account/corder_other', $data);
    }

    /**
     * 下载订单模板解释文件
     * @throws Throwable
     */
    public function downloadTemplateInterpretationFile()
    {
        $data = array();
        $data['app_version'] = APP_VERSION;
        $this->response->setOutput(load()->view('account/corder_temp', $data));
    }

    /**
     * Walmart平台对应的“How to Complete Template”
     * @throws Throwable
     * */
    public function walmartInstruction()
    {
        $this->response->setOutput(load()->view('account/corder_temp_walmart', []));
    }

    /**
     * @throws Throwable
     */
    public function europeWayfairInstruction()
    {
        $this->response->setOutput(load()->view('account/corder_temp_wayfair', []));
    }

    /**
     * 自提货导入指导页面
     * @return string
     */
    public function buyerPickUpInstruction()
    {
        return $this->render('account/corder_temp_buyer_pick_up');
    }

    /**
     * 获取欧洲wayfair订单的
     * @return string
     * @throws Exception
     */
    public function getManifestList()
    {
        $condition['order_id'] = request('order_id', '');
        $data = $condition;
        $data['app_version'] = APP_VERSION;

        $condition['is_synchroed'] = false;
        [$data['list'],] = app(ManifestRepository::class)->getManifestManagementList(customer()->getId(), $condition, false);
        $data['dropship_file_unlink'] = url('account/customer_order/dropshipFileUnlink');
        $data['europe_wayfair_manifest_management_preserved'] = url('account/customer_order/europeWayfairManifestManagementPreserved');
        $data['show_url'] = url('account/customer_order/getManifestList');

        return $this->render('account/corder_manifest_list', $data);
    }

    /**
     * 取欧洲wayfair订单的历史记录
     * @return string
     * @throws Exception
     */
    public function getManifestHistoryList()
    {
        $condition['page'] = request('page', 1);
        $condition['page_limit'] = request('page_limit', 10);
        $condition['order_id'] = request('order_id', '');
        $data = $condition;
        $data['app_version'] = APP_VERSION;

        $condition['is_synchroed'] = true;
        [$data['list'], $data['total']] = app(ManifestRepository::class)->getManifestManagementList(customer()->getId(), $condition, true);
        $data['total_pages'] = ceil($data['total'] / ($data['page_limit'] ?? 10));

        return $this->render('account/corder_manifest_history_list', $data, 'buyer');
    }

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws Exception
     */
    public function europeWayfairManifestManagementPreserved()
    {
        trim_strings($this->request->post);
        $posts = $this->request->post;
        $customer_id = $this->customer->getId();
        load()->model('account/customer_order_import');

        $ret = $this->model_account_customer_order_import->updateManifestFile($posts['manifest_common_label'], $customer_id);
        $json['error'] = 0;
        $json['msg'] = $ret['msg'];
        $json['show_url'] = url()->to(['account/customer_order/getManifestList']);
        return $this->response->json($json);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getSalesOrderBySearch()
    {
        $order_id = request()->get('order_id', '');
        $page = request()->get('page', 1);
        $customer_id = $this->customer->getId();

        $map = [
            ['o.order_id', 'like', "%{$order_id}%"],
        ];

        $builder = $this->orm->table('tb_sys_customer_sales_order as o')
            ->where(
                [
                    'o.order_mode' => HomePickUploadType::ORDER_MODE_HOMEPICK,
                    'o.buyer_id' => $customer_id,
                    'o.import_mode' => HomePickImportMode::IMPORT_MODE_WAYFAIR,
                ]
            )
            ->where($map)
            ->groupBy('o.order_id')
            ->select('o.id', 'o.order_id');
        $results['total_count'] = $builder->count('*');
        $results['items'] = $builder->forPage($page, 10)
            ->orderBy('o.order_id', 'asc')
            ->get();
        $results['items'] = obj2array($results['items']);
        return $this->response->json($results);


    }


    /**
     * csv_get_lines 读取CSV文件中的某几行数据
     * @deprecated
     * @param $csvFile CSV文件
     * @param int $offset 起始行数
     * @return array|bool
     */
    function csv_get_lines($csvFile, $offset = 0)
    {
        $encodeType = $this->detect_encoding($csvFile);
        if ($encodeType == false) {
            return false;
        }
        if (!$fp = fopen($csvFile, 'r')) {
            return false;
        }
        $i = $j = 0;
        $line = null;
        while (false !== ($line = fgets($fp))) {
            if ($i++ < $offset) {
                continue;
            }
            break;
        }
        $data = array();
        while (!feof($fp)) {
            $data[] = fgetcsv($fp);
        }
        fclose($fp);
        $values = array();
        $line = preg_split("/,/", $line);
        $keys = array();
        $flag = true;
        foreach ($data as $d) {
            $entity = array();
            if (empty($d)) {
                continue;
            }
            for ($i = 0; $i < count($line); $i++) {
                if ($i < count($d)) {
                    $entity[trim($line[$i])] = iconv($encodeType, 'UTF-8', $d[$i]);
                    if ($flag) {
                        $keys[] = trim($line[$i]);
                    }
                }
            }
            if ($flag) {
                $flag = false;
            }
            $values[] = $entity;
        }
        $result = array(
            "keys" => $keys,
            "values" => $values
        );
        return $result;
    }

    //多seller联动查询
    //public function getPriceAndQtyByProductId(){
    //    $product_id = $this->request->request['product_id'];
    //    $id = $this->request->request['sellerId'];
    //    load()->model('account/customer_order_import');
    //    load()->language('account/customer_order_import');
    //    if($product_id) {
    //        $result = $this->model_account_customer_order_import->getPriceAndSellerNameByProductId($product_id);
    //        $customerId = $this->customer->getId();
    //        load()->model('account/product_quotes/margin_contract');
    //        $margin_product = $this->model_account_product_quotes_margin_contract->getMarginProductForBuyer($customerId, null,$product_id);
    //        if(!empty($margin_product)){
    //            $current = current($margin_product);
    //            if(!empty($current) && $current['margin_is_valid'] == false){
    //                if(!isset($current['effect_time']) || !isset($current['expire_time'])){
    //                    $json['margin_expire'] = sprintf($this->language->get('error_margin_approve_expire'), $current['agreement_id']);
    //                }else{
    //                    $json['margin_expire'] = sprintf($this->language->get('error_margin_expire'), $current['agreement_id'], $current['effect_time'], $current['expire_time']);
    //                }
    //            }
    //        }
    //    }
    //    if(isset($result)){
    //        $json['no_select'] = false;
    //        $json['price'] = $this->calculatePrice($product_id,$result['price']);
    //        if ($this->customer->getCountryId() == JAPAN_COUNTRY_ID) {
    //            $json['freight'] = round($result['freight']);
    //        } else {
    //            $json['freight'] = round($result['freight'],2);
    //        }
    //        //14039 下架产品在Sales Order Management功能中隐藏价格
    //        if($result['buyer_flag'] == 0){
    //            $result['p_status'] = 0;
    //            $result['status'] = 0;
    //        }
    //        if($result['product_display'] == 0 && $result['product_display'] != null){
    //            $json['price'] = '-';
    //            $json['freight'] = '-';
    //        }
    //        if($result['status'] == '0' || $result['p_status'] == '0'){
    //            $json['price'] = '-';
    //            $json['freight'] = '-';
    //        }
    //        $json['quantity'] = $result['quantity'];
    //        $json['id'] = $id;
    //        $json['product_id'] = $product_id;
    //        $json['combo_flag'] = $result['combo_flag'];
    //        if($result['status'] == 1){
    //            $json['status'] = 'Yes';
    //        }else{
    //            $json['status'] = 'No';
    //        }
    //    }else{
    //        $json['no_select'] = true;
    //        $json['id'] = $id;
    //        $json['product_id'] = '';
    //        $json['price'] = '';
    //        $json['quantity'] = '';
    //        $json['status'] = '';
    //        $json['freight'] = '';
    //    }
    //    $this->response->headers->set('Content-Type', 'application/json');
    //    $this->response->setOutput(json_encode($json));
    //}

    /**
     * @throws Exception
     */
    public function getTransactionTypeInfoByProductId()
    {
        $product_id = request()->get('product_id');
        $id = request()->get('sellerId');
        $transaction_type = $this->request->request['transaction_type'];
        load()->model('extension/module/price');
        /** @var ModelExtensionModulePrice $priceModel */
        $priceModel = $this->model_extension_module_price;
        load()->language('account/customer_order_import');
        //目的为了查询出当前类型下的价格
        load()->model('extension/module/price');
        /** @var ModelExtensionModulePrice $priceModel */
        $priceModel = $this->model_extension_module_price;
        $transaction_type = explode('_', $transaction_type);
        $json = [];
        $transaction_info = $priceModel->getProductPriceInfo($product_id, $this->customer->getId(), [], false, true);
        $is_expire = false;
        $json['no_select'] = false;
        $json['is_expire'] = $is_expire;
        $unavailable = $transaction_info['base_info']['unavailable'];
        if ($unavailable) {
            $transaction_info['base_info']['status'] = 0;
        }
        if (end($transaction_type) == ProductTransactionType::NORMAL) {
            //普通的
            $json['price_all'] = $transaction_info['base_info']['price_all_show'];
            $json['price'] = $transaction_info['base_info']['price_show'];
            $json['freight'] = $transaction_info['base_info']['freight_show'];
            $json['p_status'] = $transaction_info['base_info']['status'];
            $json['status'] = $transaction_info['base_info']['status'];
            if ($json['status'] == 0) {
                $json['quantity'] = 0;
            } else {
                $json['quantity'] = $transaction_info['base_info']['quantity'];
            }

        } elseif (end($transaction_type) == ProductTransactionType::REBATE) {
            $info = null;
            foreach ($transaction_info['transaction_type'] as $key => $value) {
                if ($value['type'] == 1 && $value['id'] == $transaction_type[0]) {
                    $info = $value;
                    break;
                }
            }
            if (!$info) {
                //过期了
                $is_expire = true;
                $json['expire_error'] = sprintf($this->language->get('error_rebate_approve_expire'), $this->orm->table('oc_rebate_agreement')->where('id', current($transaction_type))->value('agreement_code'));
            } else {
                $json['price_all'] = $info['price_all_show'];
                $json['price'] = $info['price_show'];
                $json['freight'] = $transaction_info['base_info']['freight_show'];
                $json['p_status'] = $transaction_info['base_info']['status'];
                $json['status'] = $transaction_info['base_info']['status'];
                if ($json['status'] == 0) {
                    $json['quantity'] = 0;
                } else {
                    $json['quantity'] = $transaction_info['base_info']['quantity'];
                }
            }


        } elseif (end($transaction_type) == ProductTransactionType::MARGIN) {
            $info = null;
            foreach ($transaction_info['transaction_type'] as $key => $value) {
                if ($value['type'] == 2 && $value['id'] == $transaction_type[0]) {
                    $info = $value;
                    break;
                }
            }
            if (!$info) {
                //过期了
                $is_expire = true;
                $json['expire_error'] = sprintf($this->language->get('error_margin_approve_expire'), $this->orm->table('tb_sys_margin_agreement')->where('id', $transaction_type[0])->value('agreement_id'));

            } else {
                $json['price'] = $info['price_show'];
                $json['freight'] = $transaction_info['base_info']['freight_show'];
                $json['p_status'] = $transaction_info['base_info']['status'];
                $json['status'] = $transaction_info['base_info']['status'];
                if ($json['status'] == 0) {
                    $json['quantity'] = 0;
                } else {
                    $json['quantity'] = $info['left_qty'];
                }
            }

        } elseif (end($transaction_type) == ProductTransactionType::FUTURE) {
            $info = null;
            foreach ($transaction_info['transaction_type'] as $key => $value) {
                if ($value['type'] == 3 && $value['id'] == $transaction_type[0]) {
                    $info = $value;
                    break;
                }
            }
            if (!$info) {
                //过期了
                $is_expire = true;
                $json['expire_error'] = sprintf($this->language->get('error_future_margin_approve_expire'), $this->orm->table('tb_sys_margin_agreement')->where('id', $transaction_type[0])->value('agreement_id'));

            } else {
                $json['price_all'] = $info['price_all_show'];
                $json['price'] = $info['price_show'];
                $json['freight'] = $transaction_info['base_info']['freight_show'];
                $json['p_status'] = $transaction_info['base_info']['status'];
                $json['status'] = $transaction_info['base_info']['status'];
                if ($json['status'] == 0) {
                    $json['quantity'] = 0;
                } else {
                    $json['quantity'] = $info['left_qty'];
                }
            }

        } elseif (end($transaction_type) == ProductTransactionType::SPOT) {
            $info = null;
            foreach ($transaction_info['transaction_type'] as $key => $value) {
                if ($value['type'] == 4 && $value['id'] == $transaction_type[0]) {
                    $info = $value;
                    break;
                }
            }
            if (!$info) {
                //过期了
                $is_expire = true;
                $json['expire_error'] = sprintf($this->language->get('error_spot_approve_expire'), $this->orm->table('tb_sys_margin_agreement')->where('id', $transaction_type[0])->value('agreement_id'));

            } else {
                $json['price_all'] = $info['price_all_show'];
                $json['price'] = $info['price_show'];
                $json['freight'] = $transaction_info['base_info']['freight_show'];
                $json['p_status'] = $transaction_info['base_info']['status'];
                $json['status'] = $transaction_info['base_info']['status'];
                if ($json['status'] == 0) {
                    $json['quantity'] = 0;
                } else {
                    $json['quantity'] = $info['left_qty'];
                }
            }

        }


        if ($transaction_info['base_info']['buyer_flag'] == 0) {
            $json['p_status'] = 0;
            $json['status'] = 0;
        }
        if ($unavailable == 1) {
            $json['price'] = '-';
            $json['freight'] = '-';
        }
        if ($json['status'] == '0' || $json['p_status'] == '0') {
            $json['price'] = '-';
            $json['freight'] = '-';
        }

        $json['id'] = $id;
        $json['product_id'] = $product_id;
        $json['combo_flag'] = $transaction_info['base_info']['combo_flag'];
        if ($json['status'] == 1) {
            $json['status'] = 'Yes';
        } else {
            $json['status'] = 'No';
        }


        if ($unavailable || $is_expire) {
            $json['no_select'] = true;
            $json['id'] = $id;
            $json['is_expire'] = $is_expire;
            $json['product_id'] = '';
            $json['price'] = '';
            $json['quantity'] = '';
            $json['status'] = 'No';
            $json['freight'] = '';
            $json['price_all'] = '';
        }

        $this->response->returnJson($json);

    }

    /**
     * 多seller联动查询
     * @throws Exception
     */
    public function getPriceAndQtyByProductId()
    {
        $product_id = request()->get('product_id', 0);
        $id = request()->get('sellerId', 0);
        load()->model('extension/module/price');
        /** @var ModelExtensionModulePrice $priceModel */
        $priceModel = $this->model_extension_module_price;
        $selectItem = [];
        $unavailable = '';
        $json = [];
        if ($product_id) {
            $transaction_info = $priceModel->getProductPriceInfo($product_id, $this->customer->getId(), [], false, true);
            $buyer_flag = 0;
            if (array_key_exists('base_info', $transaction_info) && array_key_exists('buyer_flag', $transaction_info['base_info'])) {
                $buyer_flag = $transaction_info['base_info']['buyer_flag'];
            }
            $selection = 0;
            if ($transaction_info['first_get']['type'] == ProductTransactionType::NORMAL) {
                $json['price'] = $transaction_info['first_get']['price_show'];
                $json['freight'] = $transaction_info['base_info']['freight_show'];
                if ($transaction_info['base_info']['unavailable'] == 1) {
                    $transaction_info['base_info']['status'] = 0;
                }
                $json['p_status'] = $transaction_info['base_info']['status'];
                $json['status'] = $transaction_info['base_info']['status'];
                if ($json['status'] == 0 || $buyer_flag == 0) {
                    $json['quantity'] = 0;
                } else {
                    $json['quantity'] = $transaction_info['base_info']['quantity'];
                }
                $selection = 0;
            } elseif ($transaction_info['first_get']['type'] == ProductTransactionType::REBATE) {

                $json['price'] = $transaction_info['first_get']['price_show'];
                $json['freight'] = $transaction_info['base_info']['freight_show'];
                $json['p_status'] = $transaction_info['base_info']['status'];
                $json['status'] = $transaction_info['base_info']['status'];
                if ($json['status'] == 0 || $buyer_flag == 0) {
                    $json['quantity'] = 0;
                } else {
                    $json['quantity'] = $transaction_info['base_info']['quantity'];
                }
                $selection = $transaction_info['first_get']['id'];

            } elseif ($transaction_info['first_get']['type'] == ProductTransactionType::MARGIN) {
                $json['price'] = $transaction_info['first_get']['price_show'];
                $json['freight'] = $transaction_info['base_info']['freight_show'];
                $json['p_status'] = $transaction_info['base_info']['status'];
                $json['status'] = $transaction_info['base_info']['status'];
                if ($json['status'] == 0 || $buyer_flag == 0) {
                    $json['quantity'] = 0;
                } else {
                    $json['quantity'] = $transaction_info['first_get']['left_qty'];
                }
                $selection = $transaction_info['first_get']['id'];

            } elseif ($transaction_info['first_get']['type'] == ProductTransactionType::FUTURE) {
                $json['price'] = $transaction_info['first_get']['price_show'];
                $json['freight'] = $transaction_info['base_info']['freight_show'];
                $json['p_status'] = $transaction_info['base_info']['status'];
                $json['status'] = $transaction_info['base_info']['status'];
                if ($json['status'] == 0 || $buyer_flag == 0) {
                    $json['quantity'] = 0;
                } else {
                    $json['quantity'] = $transaction_info['first_get']['left_qty'];
                }
                $selection = $transaction_info['first_get']['id'];

            } elseif ($transaction_info['first_get']['type'] == ProductTransactionType::SPOT) {
                $json['price'] = $transaction_info['first_get']['price_show'];
                $json['freight'] = $transaction_info['base_info']['freight_show'];
                $json['p_status'] = $transaction_info['base_info']['status'];
                $json['status'] = $transaction_info['base_info']['status'];
                if ($json['status'] == 0 || $buyer_flag == 0) {
                    $json['quantity'] = 0;
                } else {
                    $json['quantity'] = $transaction_info['first_get']['left_qty'];
                }
                $selection = $transaction_info['first_get']['id'];

            }

            $unavailable = $transaction_info['base_info']['unavailable'];
            if ($transaction_info['base_info']['buyer_flag'] == 0) {
                $json['p_status'] = 0;
                $json['status'] = 0;
            }
            if ($unavailable == 1) {
                $json['price'] = '-';
                $json['freight'] = '-';
            }
            if ($json['status'] == '0' || $json['p_status'] == '0') {
                $json['price'] = '-';
                $json['freight'] = '-';
            }

            $json['id'] = $id;
            $json['product_id'] = $product_id;
            $json['combo_flag'] = $transaction_info['base_info']['combo_flag'];
            if ($json['status'] == 1) {
                $json['status'] = 'Yes';
            } else {
                $json['status'] = 'No';
            }
            $json['no_select'] = false;

            //构造一下select框

            $selectItem[] = [
                'key' => 0,
                'value' => 'Normal Transaction',
                'selected' => $selection == 0 ? 1 : 0,
            ];
            foreach ($transaction_info['transaction_type'] as $item) {
                if ($item['type'] == ProductTransactionType::REBATE) {
                    $vItem = 'Rebate:' . $item['agreement_code'];
                } elseif ($item['type'] == ProductTransactionType::MARGIN) {
                    $vItem = 'Margin:' . $item['agreement_code'];
                } elseif ($item['type'] == ProductTransactionType::FUTURE) {
                    continue;
                    $vItem = 'Future Goods:' . $item['agreement_code'];
                } else {
                    $vItem = 'Spot:' . $item['agreement_code'];
                }
                $selectItem[] = [
                    'key' => $item['id'] . '_' . $item['type'],
                    'value' => $vItem,
                    'selected' => $selection == $item['id'] ? 1 : 0,
                ];
            }
            $json['selectItem'] = $selectItem;
        }
        if ($unavailable) {
            $selectItem = [];
            $selectItem[] = [
                'key' => 0,
                'value' => 'Normal Transaction',
                'selected' => 0,
            ];
            $json['no_select'] = true;
            $json['id'] = $id;
            $json['product_id'] = $product_id;
            $json['price'] = '';
            $json['quantity'] = '';
            $json['status'] = 'No';
            $json['freight'] = '';
            $json['selectItem'] = $selectItem;
        }

        $this->response->returnJson($json);
    }

    /**
     * 取消订单 原始上门取货buyer取消订单，因下面的方法基本重新，备份不删
     * @deprecated
     * @throws Exception
     */
    public function cancelOrderBak()
    {

        load()->model("account/customer_order");
        load()->language('account/customer_order_import');
        $json = array();
        $data = $this->request->post;
        $id = request()->post('id', 0);
        $order_id = htmlspecialchars_decode($data['orderId']);
        $remove_stock = request()->post('removeStock', 0);
        $reason = request()->post('reason');
        //$id= 15686;
        //$orderDate= "2019-06-21 23:57:50";
        //$order_id= "DfhBj6hP1";
        //$order_status = 129;
        //$remove_stock = 0;
        $country_id = $this->customer->getCountryId();
        $group_id = $this->customer->getGroupId();
        if (empty($group_id) || empty($country_id)) {
            $buyer_info = $this->model_account_customer_order->getSalesOrderBuyerInfo($id);
            if (isset($buyer_info['customer_group_id']) && isset($buyer_info['country_id'])) {
                $group_id = $buyer_info['customer_group_id'];
                $country_id = $buyer_info['country_id'];
            }
        }
        //dropship cancelOrder 1 2 16 64 128 129
        $customer_sales_order = $this->orm->table('tb_sys_customer_sales_order')->where('id', $id)->first();
        $order_mode = $customer_sales_order->order_mode;
        $orderStatus = $customer_sales_order->order_status;
        $is_syncing = $this->model_account_customer_order->checkOrderIsSyncing($id);
        if ($is_syncing) {
            $json['fail'] = $this->language->get('error_is_syncing');
            goto mark;
        }

        //13549 需求要求超大件可取消
        //$has_oversize = $this->model_account_customer_order->checkOrderContainsOversizeProduct($id);
        //if ($order_status != 1 && $has_oversize){
        //    $json['fail'] = $this->language->get('error_cancel_oversize');
        //}
        $is_in_omd = $this->model_account_customer_order->checkOrderShouldInOmd($id);
        $omd_store_id = $this->model_account_customer_order->getOmdStoreId($id);
        if ($order_mode == CustomerSalesOrderMode::PICK_UP) {
            $omd_store_id = $this->model_account_customer_order->getDropshipOmdStoreId($id);
            if (!in_array($orderStatus, CustomerSalesOrderStatus::canCancel())) {
                $json['fail'] = $this->language->get('error_can_cancel');
                goto mark;
            }
        }

        if (!isset($json['fail'])) {
            $param = array();
            $post_data = array();
            $record_data = array();

            $orderStatusDesc = CustomerSalesOrderStatus::getDescription($orderStatus);
            $process_code = CommonOrderProcessCode::CANCEL_ORDER;
            $status = CommonOrderActionStatus::PENDING;
            $run_id = time();
            $header_id = $id;
            //订单类型，暂不考虑重发单
            $order_type = 1;
            $create_time = date("Y-m-d H:i:s");
            $before_record = "Order_Id:" . $order_id . ", status:" . $orderStatusDesc;
            $modified_record = "Order_Id:" . $order_id . ", status:Cancelled";

            $post_data['uuid'] = self::OMD_ORDER_CANCEL_UUID;
            $post_data['runId'] = $run_id;
            $post_data['orderId'] = $order_id;
            $post_data['storeId'] = $omd_store_id;
            $param['apiKey'] = OMD_POST_API_KEY;
            $param['postValue'] = json_encode($post_data);

            $record_data['process_code'] = $process_code;
            $record_data['status'] = $status;
            $record_data['run_id'] = $run_id;
            $record_data['before_record'] = $before_record;
            $record_data['modified_record'] = $modified_record;
            $record_data['header_id'] = $header_id;
            $record_data['order_id'] = $order_id;
            $record_data['order_type'] = $order_type;
            $record_data['remove_bind'] = $remove_stock;
            $record_data['create_time'] = $create_time;
            $record_data['cancel_reason'] = $reason;

            if ($country_id != AMERICAN_COUNTRY_ID) {
                //N-1039 非美国Buyer，则按原有的规则
                if (
                    $order_mode != CustomerSalesOrderMode::PICK_UP &&
                    in_array($orderStatus, [
                        CustomerSalesOrderStatus::TO_BE_PAID,
                    ])
                ) {
                    $cancel_result = $this->model_account_customer_order->cancelOrderFromNewOrder($id);
                    if ($cancel_result) {
                        $record_data['status'] = CommonOrderActionStatus::SUCCESS;
                        //保存修改记录
                        $this->model_account_customer_order->saveSalesOrderModifyRecord($record_data);
                        $json['success'] = $this->language->get('text_cancel_success');
                    } else {
                        $record_data['status'] = CommonOrderActionStatus::FAILED;
                        //保存修改记录
                        $this->model_account_customer_order->saveSalesOrderModifyRecord($record_data);
                        $json['success'] = $this->language->get('text_cancel_failed');
                    }
                } elseif (
                    $order_mode == CustomerSalesOrderMode::PICK_UP &&
                    in_array($orderStatus, [
                        CustomerSalesOrderStatus::TO_BE_PAID,
                        CustomerSalesOrderStatus::PENDING_CHARGES,
                        CustomerSalesOrderStatus::CHECK_LABEL,
                    ])
                ) {
                    if (ob_get_contents()) ob_end_clean();
                    $this->orm->table('tb_sys_customer_sales_dropship_file_details')->where('order_id', $id)->update(['status' => 0]);
                    $cancel_result = $this->model_account_customer_order->cancelOrderFromNewOrder($id);
                    $remove_bind_result = true;
                    if ($remove_stock == 1) {
                        $remove_bind_result = $this->model_account_customer_order->removeSalesOrderBind($id);
                        // 解除仓租绑定
                        app(StorageFeeService::class)->unbindBySalesOrder([$id]);
                    }

                    if ($cancel_result && $remove_bind_result) {
                        $record_data['status'] = CommonOrderActionStatus::SUCCESS;
                        //保存修改记录
                        $this->model_account_customer_order->saveSalesOrderModifyRecord($record_data);
                        $json['success'] = $this->language->get('text_homepick_europe_cancel_success');
                    } else {
                        $record_data['status'] = CommonOrderActionStatus::FAILED;
                        //保存修改记录
                        $this->model_account_customer_order->saveSalesOrderModifyRecord($record_data);
                        $json['success'] = $this->language->get('text_homepick_europe_cancel_failed');
                    }
                } elseif (!$is_in_omd) {
                    //不在OMD里的订单直接执行取消操作
                    $cancel_result = $this->model_account_customer_order->cancelOrderFromNewOrder($id);
                    $remove_bind_result = true;
                    if ($remove_stock == 1) {
                        $remove_bind_result = $this->model_account_customer_order->removeSalesOrderBind($id);
                        // 解除仓租绑定
                        app(StorageFeeService::class)->unbindBySalesOrder([$id]);
                    }
                    if ($cancel_result && $remove_bind_result) {
                        $record_data['status'] = CommonOrderActionStatus::SUCCESS;
                        //保存修改记录
                        $this->model_account_customer_order->saveSalesOrderModifyRecord($record_data);
                        $json['success'] = $this->language->get('text_cancel_success');
                    } else {
                        $record_data['status'] = CommonOrderActionStatus::FAILED;
                        //保存修改记录
                        $this->model_account_customer_order->saveSalesOrderModifyRecord($record_data);
                        $json['success'] = $this->language->get('text_cancel_failed');
                    }
                } elseif (
                    $order_mode == CustomerSalesOrderMode::PICK_UP && (
                        $orderStatus == CustomerSalesOrderStatus::BEING_PROCESSED ||
                        $orderStatus == CustomerSalesOrderStatus::LTL_CHECK
                    )
                ) {
                    ob_end_clean();
                    //保存修改记录
                    $log_id = $this->model_account_customer_order->saveSalesOrderModifyRecord($record_data);
                    //取消状态根据回调来
                    $response = $this->sendCurl(OMD_POST_URL, $param);
                    if (isset($response) && 'OMD PROCESS SUCCESS' == $response) {
                        //取消的订单tracking_number 可以重复 ，美国dropship业务使用。
                        //$this->orm->table('tb_sys_customer_sales_dropship_file_details')->where('order_id',$id)->update(['status'=> 0 ]);
                        $json['success'] = $this->language->get('text_cancel_wait');
                    } else {
                        $new_status = CommonOrderActionStatus::FAILED;
                        $fail_reason = $this->language->get('error_response');
                        $this->model_account_customer_order->updateSalesOrderModifyLog($log_id, $new_status, $fail_reason);
                        $json['fail'] = $this->language->get('error_response');
                    }
                } else {
                    //保存修改记录
                    $log_id = $this->model_account_customer_order->saveSalesOrderModifyRecord($record_data);
                    $response = $this->sendCurl(OMD_POST_URL, $param);
                    //
                    if (isset($response) && 'OMD PROCESS SUCCESS' == $response) {
                        //取消的订单tracking_number 可以重复 ，美国dropship业务使用。
                        //$this->orm->table('tb_sys_customer_sales_dropship_file_details')->where('order_id',$id)->update(['status'=> 0 ]);
                        $json['success'] = $this->language->get('text_cancel_wait');

                    } else {
                        $new_status = CommonOrderActionStatus::FAILED;
                        $fail_reason = $this->language->get('error_response');
                        $this->model_account_customer_order->updateSalesOrderModifyLog($log_id, $new_status, $fail_reason);
                        $json['fail'] = $this->language->get('error_response');
                    }
                }
            } else {
                //N-1039 是美国Buyer
                if (!$is_in_omd) {//未同步至OMD
                    if (in_array($orderStatus, [
                        CustomerSalesOrderStatus::TO_BE_PAID,
                        CustomerSalesOrderStatus::LTL_CHECK,
                        CustomerSalesOrderStatus::ASR_TO_BE_PAID,
                        CustomerSalesOrderStatus::PENDING_CHARGES,
                        CustomerSalesOrderStatus::CHECK_LABEL,
                    ])) {
                        //不在OMD里的订单直接执行取消操作
                        $cancel_result = $this->model_account_customer_order->cancelOrderFromNewOrder($id);
                        $remove_bind_result = true;
                        if ($remove_stock == 1) {
                            $remove_bind_result = $this->model_account_customer_order->removeSalesOrderBind($id);
                            // 解除仓租绑定
                            app(StorageFeeService::class)->unbindBySalesOrder([$id]);
                        }
                        if ($cancel_result && $remove_bind_result) {
                            $record_data['status'] = CommonOrderActionStatus::SUCCESS;
                            //保存修改记录
                            $this->model_account_customer_order->saveSalesOrderModifyRecord($record_data);
                            $json['success'] = $this->language->get('text_cancel_success');
                            goto mark;
                        } else {
                            $record_data['status'] = CommonOrderActionStatus::FAILED;
                            //保存修改记录
                            $this->model_account_customer_order->saveSalesOrderModifyRecord($record_data);
                            $json['success'] = $this->language->get('text_cancel_failed');
                            goto mark;
                        }
                    } elseif ($orderStatus == CustomerSalesOrderStatus::BEING_PROCESSED) {
                        if (!$this->is_auto_buyer) {
                            //不在OMD里的订单直接执行取消操作
                            $cancel_result = $this->model_account_customer_order->cancelOrderFromNewOrder($id);
                            $remove_bind_result = true;
                            if ($remove_stock == 1) {
                                $remove_bind_result = $this->model_account_customer_order->removeSalesOrderBind($id);
                                // 解除仓租绑定
                                app(StorageFeeService::class)->unbindBySalesOrder([$id]);
                            }
                            if ($cancel_result && $remove_bind_result) {
                                $record_data['status'] = CommonOrderActionStatus::SUCCESS;
                                //保存修改记录
                                $this->model_account_customer_order->saveSalesOrderModifyRecord($record_data);
                                $json['success'] = $this->language->get('text_cancel_success');
                                goto mark;
                            } else {
                                $record_data['status'] = CommonOrderActionStatus::FAILED;
                                //保存修改记录
                                $this->model_account_customer_order->saveSalesOrderModifyRecord($record_data);
                                $json['success'] = $this->language->get('text_cancel_failed');
                                goto mark;
                            }
                        } else {
                            //是自动购买账户
                            $json['fail'] = $this->language->get('error_can_cancel');
                            goto mark;
                        }
                    } else {
                        //其他状态(如16 Canceled，32 Completed)
                        $json['fail'] = $this->language->get('error_can_cancel');
                        goto mark;
                    }
                } else {
                    //已存在于OMD系统中
                    //查询OMD的订单状态
                    $log_id = $this->model_account_customer_order->saveSalesOrderModifyRecord($record_data);
                    $response = $this->sendCurl(OMD_POST_URL, $param);
                    $this->log->write($response);
                    $this->log->write($param);
                    if (isset($response) && 'OMD PROCESS SUCCESS' == $response) {
                        //取消的订单tracking_number 可以重复 ，美国dropship业务使用。
                        //$this->orm->table('tb_sys_customer_sales_dropship_file_details')->where('order_id',$id)->update(['status'=> 0 ]);
                        $json['success'] = $this->language->get('text_cancel_wait');

                    } else {
                        $new_status = CommonOrderActionStatus::FAILED;
                        $fail_reason = $this->language->get('error_response');
                        $this->model_account_customer_order->updateSalesOrderModifyLog($log_id, $new_status, $fail_reason);
                        $json['fail'] = $this->language->get('error_response');
                    }
                    goto mark;
                }
            }
        }
        mark:
        $this->response->headers->set('Content-Type', 'application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * 取消订单 新的
     *
     * 修改时注意以下点：
     * 存在定时任务自动取消自提货待确认的销售单，详见 catalog/controller/api/sales_order_pick_up.php
     * @return JsonResponse
     * @throws Exception
     */
    public function cancelOrder()
    {
        load()->model("account/customer_order");
        load()->language('account/customer_order_import');
        $data = $this->request->post();
        $id = $this->request->post('id');
        $order_id = htmlspecialchars_decode($data['orderId']); //销售订单order  eg：113-5125913-0753019
        $orderStatus = $this->request->post('orderStatus');
        $remove_stock = $this->request->post('removeStock', 1);
        $orderDate = $this->request->post('orderDate');
        $country_id = customer()->getCountryId();
        $group_id = customer()->getGroupId();
        $buyer_id = $this->customer->getId();
        if (empty($group_id) || empty($country_id)) {
            $buyer_info = $this->model_account_customer_order->getSalesOrderBuyerInfo($id);
            if (isset($buyer_info['customer_group_id']) && isset($buyer_info['country_id'])) {
                $group_id = $buyer_info['customer_group_id'];
                $country_id = $buyer_info['country_id'];
                $buyer_id = $buyer_info['customer_id'];
            }
        }
        //dropship cancelOrder 1 2 16 64 128 129
        $customer_sales_order = CustomerSalesOrder::query()->where('id', $id)->first();
        $order_mode = $customer_sales_order->order_mode;
        $orderStatus = $customer_sales_order->order_status;
        $order_code = $customer_sales_order->order_id;
        $program_code = $customer_sales_order->program_code;
        $sales_order_id =  $customer_sales_order->id;
        // 订单状态不允许取消
        if (!in_array($orderStatus, CustomerSalesOrderStatus::canCancel())) {
            return $this->json(['fail' => $this->language->get('error_can_cancel')]);
        }

        //自提货
        if ($customer_sales_order->import_mode == HomePickImportMode::IMPORT_MODE_BUYER_PICK_UP) {
            $buyerPickUpInfo = CustomerSalesOrderPickUp::query()->where('sales_order_id', $id)->first();
            if ($orderStatus == CustomerSalesOrderStatus::BEING_PROCESSED && $buyerPickUpInfo->pick_up_status == CustomerSalesOrderPickUpStatus::IN_PREP) {
                return $this->json(['fail' => 'The order cannot be cancelled when in preparation. If you\'d like to intercept it, please contact the online customer service team.']);
            }
            if ($orderStatus == CustomerSalesOrderStatus::ON_HOLD && $buyerPickUpInfo->pick_up_status == CustomerSalesOrderPickUpStatus::PICK_UP_TIMEOUT) {
                return $this->json(['fail' => 'The order has not been picked up for more than 7 business days. If you\'d like to cancel it, please contact the online customer service team.']);
            }
            if ($orderStatus == CustomerSalesOrderStatus::WAITING_FOR_PICK_UP) {
                return $this->json(['fail' => 'The order cannot be cancelled once the preparation is completed. If you\'d like to intercept it, please contact the online customer service team.']);
            }
        }
        // 订单状态同步中
        $is_syncing = $this->model_account_customer_order->checkOrderIsSyncing($id);
        if ($is_syncing) {
            return $this->json(['fail' => $this->language->get('error_is_syncing')]);
        }

        if ($order_mode == CustomerSalesOrderMode::PICK_UP) {
            //$omd_store_id = $this->model_account_customer_order->getDropshipOmdStoreId($id);
            $omd_store_id = app(CustomerSalesOrderRepository::class)->getOmdStoreId($id);
            if ($customer_sales_order->import_mode == HomePickImportMode::IMPORT_MODE_BUYER_PICK_UP) {
                $omd_store_id = 256;//应omd要求storeId 为256
            }
        } else {
            $omd_store_id = $this->model_account_customer_order->getOmdStoreId($id);
        }
        //极端情况 没获取到omd store id
        if (empty($omd_store_id)) {
            return $this->json(['fail' => $this->language->get('error_store_id_error')]);
        }

        $run_id = time();
        $post_data['uuid'] = self::OMD_ORDER_CANCEL_UUID;
        $post_data['runId'] = $run_id;
        $post_data['orderId'] = $order_code;
        $post_data['storeId'] = $omd_store_id;
        $param['apiKey'] = OMD_POST_API_KEY;
        $param['postValue'] = json_encode($post_data);
        // 英文化
        $order_status = CustomerSalesOrderStatus::getDescription($orderStatus);
        $cancel_reason = $this->request->post('reason', '');
        if ($order_mode == CustomerSalesOrderMode::DROP_SHIPPING && $program_code != CustomerSalesOrderSynMode::B2B_SYN) {
            //兼容Java那边order_mode = 1情况
            // 此处存在api导单的情况
            if ($this->is_auto_buyer) {
                //保存修改记录
                $log_id = app(DropshipCancelOrderService::class)->addCancelModifyLog([
                    'order_id' => $sales_order_id,
                    'order_status' => $order_status,
                    'order_code' => $order_code,
                    'remove_bind' => $remove_stock,
                    'run_id' => $run_id,
                    'cancel_reason' => $cancel_reason,
                ]);
                $response = $this->sendCurl(OMD_POST_URL, $param);
                if (isset($response) && 'OMD PROCESS SUCCESS' == $response) {
                    return $this->json(['success' => $this->language->get('text_cancel_wait')]);
                } else {
                    $new_status = CommonOrderActionStatus::FAILED;
                    $failReason = $this->language->get('error_response');
                    app(DropshipCancelOrderService::class)->updateCancelModifyLog($log_id, $new_status, $failReason);
                    return $this->json(['fail' => $this->language->get('error_response')]);
                }
            }
            //异常订单
            return $this->json(['success' => $this->language->get('error_cannot_cancel')]);
        }

        $isExportInfo = app(CustomerSalesOrderRepository::class)->calculateSalesOrderIsExportedNumber($id);
        if ($isExportInfo['is_export_number'] > 0) {
            $sellerList = app(CustomerRepository::class)->calculateSellerListBySalesOrderId($id);
            $haveGigaOnsite = $haveOmd = 0;
            foreach ($sellerList as $seller) {
                if ($seller['accounting_type'] == CustomerAccountingType::GIGA_ONSIDE) {
                    $haveGigaOnsite = 1; //有卖家需要on_site
                } else {
                    $haveOmd = 1; //有卖家需要omd
                }
            }
            if ($haveGigaOnsite == 1 && $haveOmd == 1) {
                return $this->json(['fail' => $this->language->get('error_is_contact_service')]);
            } elseif ($haveGigaOnsite == 1) {
                $isInOnsite = $this->model_account_customer_order->checkOrderShouldInGigaOnsite($id);
                if ($isInOnsite) {
                    ob_end_clean();
                    //保存修改记录
                    $log_id = app(DropshipCancelOrderService::class)->addCancelModifyLog([
                        'order_id' => $sales_order_id,
                        'order_status' => $order_status,
                        'order_code' => $order_code,
                        'remove_bind' => $remove_stock,
                        'run_id' => $run_id,
                        'cancel_reason' => $cancel_reason,
                    ]);
                    //保存修改记录
                    $gigaResult = app(GigaOnsiteHelper::class)->cancelOrder($order_id, $run_id);
                    if ($gigaResult['code'] == 1) {
                        return $this->json(['success' => $this->language->get('text_cancel_wait')]);
                    } else {
                        $new_status = CommonOrderActionStatus::FAILED;
                        $failReason = $this->language->get('text_cancel_failed');
                        app(DropshipCancelOrderService::class)->updateCancelModifyLog($log_id, $new_status, $failReason);
                        return $this->json(['fail' => $this->language->get('error_response')]);
                    }
                }
            } elseif ($haveOmd == 1) {
                $isInOmd = $this->model_account_customer_order->checkOrderShouldInOmd($id);
                if ($isInOmd) {
                    //保存修改记录
                    $log_id = app(DropshipCancelOrderService::class)->addCancelModifyLog([
                        'order_id' => $sales_order_id,
                        'order_status' => $order_status,
                        'order_code' => $order_code,
                        'remove_bind' => $remove_stock,
                        'run_id' => $run_id,
                        'cancel_reason' => $cancel_reason,
                    ]);
                    $response = $this->sendCurl(OMD_POST_URL, $param);
                    if (isset($response) && 'OMD PROCESS SUCCESS' == $response) {
                        return $this->json(['success' => $this->language->get('text_cancel_wait')]);
                    } else {
                        $new_status = CommonOrderActionStatus::FAILED;
                        $failReason = $this->language->get('error_response');
                        app(DropshipCancelOrderService::class)->updateCancelModifyLog($log_id, $new_status, $failReason);
                        return $this->json(['fail' => $this->language->get('error_response')]);
                    }
                }
            } else {
                if ($isExportInfo['is_omd_number'] > 0) {
                    //保存修改记录
                    $log_id = app(DropshipCancelOrderService::class)->addCancelModifyLog([
                        'order_id' => $sales_order_id,
                        'order_status' => $order_status,
                        'order_code' => $order_code,
                        'remove_bind' => $remove_stock,
                        'run_id' => $run_id,
                        'cancel_reason' => $cancel_reason,
                    ]);
                    $response = $this->sendCurl(OMD_POST_URL, $param);
                    if (isset($response) && 'OMD PROCESS SUCCESS' == $response) {
                        return $this->json(['success' => $this->language->get('text_cancel_wait')]);
                    } else {
                        $new_status = CommonOrderActionStatus::FAILED;
                        $failReason = $this->language->get('error_response');
                        app(DropshipCancelOrderService::class)->updateCancelModifyLog($log_id, $new_status, $failReason);
                        return $this->json(['fail' => $this->language->get('error_response')]);
                    }
                }
            }
        }

        $db = db()->getConnection();
        $db->beginTransaction();
        try {
            //可直接修改汇总到这儿
            //非美国
            if ($country_id != AMERICAN_COUNTRY_ID) {
                ob_end_clean();
                if (in_array($orderStatus, [
                    CustomerSalesOrderStatus::TO_BE_PAID,
                    CustomerSalesOrderStatus::PENDING_CHARGES,
                    CustomerSalesOrderStatus::CHECK_LABEL,
                ])) {
                    $this->orm->table('tb_sys_customer_sales_dropship_file_details')->where('order_id', $id)->update(['status' => 0]);
                }

                $cancel_result = $this->model_account_customer_order->cancelOrderFromNewOrder($id);
                $remove_bind_result = true;
                if ($remove_stock == 1) {
                    $remove_bind_result = $this->model_account_customer_order->removeSalesOrderBind($id);
                    // 解除仓租绑定
                    app(StorageFeeService::class)->unbindBySalesOrder([$id]);
                }

                if ($cancel_result && $remove_bind_result) {
                    // 取消保障服务费用单
                    app(FeeOrderService::class)->cancelSafeguardFeeOrderBySalesOrderId($id);
                    // 仓租费用单退款
                    app(FeeOrderService::class)->refundStorageFeeOrderBySalesOrderId($id);
                    //保存修改记录
                    $log_id = app(DropshipCancelOrderService::class)->addCancelModifyLog([
                        'order_id' => $sales_order_id,
                        'order_status' => $order_status,
                        'order_code' => $order_code,
                        'remove_bind' => $remove_stock,
                        'status' => CommonOrderActionStatus::SUCCESS,
                        'run_id' => $run_id,
                        'cancel_reason' => $cancel_reason,
                    ]);

					// 释放销售订单囤货预绑定
	                if ($orderStatus == CustomerSalesOrderStatus::TO_BE_PAID) {
	                    app(BuyerStockService::class)->releaseInventoryLockBySalesOrderPreAssociated([$sales_order_id], (int)$buyer_id);
	                }

                    
                    $db->commit();
                    return $this->json(['success' => $this->language->get('text_homepick_europe_cancel_success')]);
                } else {
                    //保存修改记录
                    $log_id = app(DropshipCancelOrderService::class)->addCancelModifyLog([
                        'order_id' => $sales_order_id,
                        'order_status' => $order_status,
                        'order_code' => $order_code,
                        'remove_bind' => $remove_stock,
                        'status' => CommonOrderActionStatus::FAILED,
                        'run_id' => $run_id,
                        'cancel_reason' => $cancel_reason,
                    ]);
                    $db->commit();
                    return $this->json(['fail' => $this->language->get('text_homepick_europe_cancel_failed')]);
                }
            }

            //美国的
            if (in_array($orderStatus, [
                CustomerSalesOrderStatus::TO_BE_PAID,
                CustomerSalesOrderStatus::LTL_CHECK,
                CustomerSalesOrderStatus::ASR_TO_BE_PAID,
                CustomerSalesOrderStatus::PENDING_CHARGES,
                CustomerSalesOrderStatus::CHECK_LABEL,
                CustomerSalesOrderStatus::ON_HOLD
            ])) {
                $cancel_result = $this->model_account_customer_order->cancelOrderFromNewOrder($id);
                $remove_bind_result = true;
                if ($remove_stock == 1) {
                    $remove_bind_result = $this->model_account_customer_order->removeSalesOrderBind($id);
                    // 解除仓租绑定
                    app(StorageFeeService::class)->unbindBySalesOrder([$id]);
                }
                if ($cancel_result && $remove_bind_result) {
                    // 取消保障服务费用单
                    app(FeeOrderService::class)->cancelSafeguardFeeOrderBySalesOrderId($id);
                    // 仓租费用单退款
                    app(FeeOrderService::class)->refundStorageFeeOrderBySalesOrderId($id);
                    $log_id = app(DropshipCancelOrderService::class)->addCancelModifyLog([
                        'order_id' => $sales_order_id,
                        'order_status' => $order_status,
                        'order_code' => $order_code,
                        'remove_bind' => $remove_stock,
                        'status' => CommonOrderActionStatus::SUCCESS,
                        'run_id' => $run_id,
                        'cancel_reason' => $cancel_reason,
                    ]);

					// 释放销售订单囤货预绑定
	                if ($orderStatus == CustomerSalesOrderStatus::TO_BE_PAID) {
	                    app(BuyerStockService::class)->releaseInventoryLockBySalesOrderPreAssociated([$sales_order_id], (int)$buyer_id);
	                }
                    
                    $db->commit();
                    return $this->json(['success' => $this->language->get('text_cancel_success')]);
                } else {
                    $log_id = app(DropshipCancelOrderService::class)->addCancelModifyLog([
                        'order_id' => $sales_order_id,
                        'order_status' => $order_status,
                        'order_code' => $order_code,
                        'remove_bind' => $remove_stock,
                        'status' => CommonOrderActionStatus::FAILED,
                        'run_id' => $run_id,
                        'cancel_reason' => $cancel_reason,
                    ]);
                    $db->commit();
                    return $this->json(['fail' => $this->language->get('text_cancel_failed')]);
                }
            } elseif ($orderStatus == CustomerSalesOrderStatus::BEING_PROCESSED) {
                if (!$this->is_auto_buyer || $program_code == CustomerSalesOrderSynMode::B2B_SYN) {
                    //不在OMD里的订单直接执行取消操作
                    $cancel_result = $this->model_account_customer_order->cancelOrderFromNewOrder($id);
                    $remove_bind_result = true;
                    if ($remove_stock == 1) {
                        $remove_bind_result = $this->model_account_customer_order->removeSalesOrderBind($id);
                        // 解除仓租绑定
                        app(StorageFeeService::class)->unbindBySalesOrder([$id]);
                    }
                    if ($cancel_result && $remove_bind_result) {
                        // 取消保障服务费用单
                        app(FeeOrderService::class)->cancelSafeguardFeeOrderBySalesOrderId($id);
                        // 仓租费用单退款
                        app(FeeOrderService::class)->refundStorageFeeOrderBySalesOrderId($id);

                        $log_id = app(DropshipCancelOrderService::class)->addCancelModifyLog([
                            'order_id' => $sales_order_id,
                            'order_status' => $order_status,
                            'order_code' => $order_code,
                            'remove_bind' => $remove_stock,
                            'status' => CommonOrderActionStatus::SUCCESS,
                            'run_id' => $run_id,
                            'cancel_reason' => $cancel_reason,
                        ]);
                        $db->commit();
                        return $this->json(['success' => $this->language->get('text_cancel_success')]);
                    } else {
                        $log_id = app(DropshipCancelOrderService::class)->addCancelModifyLog([
                            'order_id' => $sales_order_id,
                            'order_status' => $order_status,
                            'order_code' => $order_code,
                            'remove_bind' => $remove_stock,
                            'status' => CommonOrderActionStatus::FAILED,
                            'run_id' => $run_id,
                            'cancel_reason' => $cancel_reason,
                        ]);
                        $db->commit();
                        return $this->json(['fail' => $this->language->get('text_cancel_failed')]);
                    }
                } else {
                    $db->commit();
                    //是自动购买账户
                    return $this->json(['fail' => $this->language->get('error_can_cancel')]);
                }
            } else {
                $db->commit();
                //其他状态(如16 Canceled，32 Completed)
                return $this->json(['fail' => $this->language->get('error_can_cancel')]);
            }
        } catch (\Exception $exception) {
            $db->rollBack();
            return $this->json(['fail' => $this->language->get('error_can_cancel')]);
        }
    }

    /**
     * 获取文件编码类型
     * @param string $file_path
     * @param string $filesize
     * @return mixed|string
     */
    public function detect_encoding($file_path)
    {
        $list = array('GBK', 'UTF-8', 'UTF-16LE', 'UTF-16BE', 'ISO-8859-1');
        $str = $this->fileToSrting($file_path);
        foreach ($list as $item) {
            $tmp = mb_convert_encoding($str, $item, $item);
            if (md5($tmp) == md5($str)) {
                return $item;
            }
        }
        return false;
    }

    /**
     * 检测文件编码
     * @param string $file_path 文件路径
     * @param string $filesize 默认为空，获取文件的全部内容，如果仅需要获取文件编码类型，获取前一百个字符即可，配合detect_encoding方法使用
     * @return string|string[] 返回文件内容，自动换行
     */
    public function fileToSrting($file_path, $filesize = '')
    {
        //判断文件路径中是否含有中文，如果有，那就对路径进行转码，如此才能识别
        if (preg_match("/[\x7f-\xff]/", $file_path)) {
            $file_path = iconv('UTF-8', 'GBK', $file_path);
        }
        if (file_exists($file_path)) {
            $fp = fopen($file_path, "r");
            if ($filesize === '') {
                $filesize = filesize($file_path);
            }
            $str = fread($fp, $filesize); //指定读取大小，这里默认把整个文件内容读取出来
            return $str = str_replace("\r\n", "<br />", $str);
        } else {
            die('文件路径错误！');
        }
    }

    /**
     * 发送HTTP请求
     * @param string $url
     * @param array $post_data
     * @return bool|string
     */
    public function sendCurl($url, $post_data)
    {
        $curl = curl_init($url);

        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_FORBID_REUSE, 1);
        curl_setopt($curl, CURLOPT_FRESH_CONNECT, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post_data));

        $response_data = curl_exec($curl);
        curl_close($curl);

        return $response_data;
    }

    /**
     * 修改SKU初步校验
     * @throws Exception
     */
    function checkChangeSku()
    {
        load()->model("account/customer_order");
        load()->language('account/customer_order_import');
        $json = array();
        $line_id = $this->request->request['lineId'];
        $old_sku = $this->request->request['oldSku'];
        $new_sku = $this->request->request['newSku'];
        $customer_id = $this->customer->getId();
        $buyer_id = $this->request->request['buyerId'];
        $country_id = $this->customer->getCountryId();
        // new_sku 不能为空
        if (empty($new_sku)) {
            $json['warning'] = $this->language->get('warning_sku_empty');
        } else {
            if (isset($line_id) || isset($old_sku) || isset($new_sku) || isset($buyer_id)) {
                //校验SKU是否录入，且不存在comboping
                if ($country_id == AMERICAN_COUNTRY_ID) {
                    $valid_code = $this->model_account_customer_order->checkProductExistAndNotCombo($new_sku);
                    if (isset($valid_code)) {
                        switch ($valid_code) {
                            case 1:
                                $json['error'] = $this->language->get('error_sku_exist');
                                break;
                            case 2:
                                $json['error'] = $this->language->get('error_sku_combo');
                                break;
                            case 3:
                                $json['error'] = $this->language->get('error_sku_oversize');
                                break;
                            default;
                        }
                    } else {
                        //相当于库存校验
                        $sku_change = $this->model_account_customer_order->checkLineSkuCanChange($line_id, $old_sku, $buyer_id);
                        if ($sku_change) {
                            //校验已有库存
                            //订单需要的数量
                            $order_qty = $this->model_account_customer_order->getOrderLineSkuQty($line_id);
                            if ($order_qty > 0) {
                                //buyer曾经买过的此sku数量
                                $stock_qty = $this->model_account_customer_order->getBuyerSumStockForSku($buyer_id, $new_sku);
                                $used_qty = $rma_qty = 0;
                                if ($stock_qty > 0) {
                                    //已消耗的数量
                                    $used_qty = $this->model_account_customer_order->getBuyerSumUsedStockForSku($buyer_id, $new_sku);
                                    //申请RMA的数量
                                    $rma_qty = $this->model_account_customer_order->getBuyerSumRmaForSku($buyer_id, $new_sku);
                                }

                                $available_qty = $stock_qty - $used_qty - $rma_qty;
                                $need_more_qty = $order_qty - $available_qty < 0 ? 0 : $order_qty - $available_qty;

                                if ($need_more_qty !== 0) {
                                    $json['success'] = sprintf($this->language->get('text_sku_inventory_1'), $old_sku, $new_sku, $available_qty, $need_more_qty);
                                } else {
                                    $json['success'] = sprintf($this->language->get('text_sku_inventory_2'), $old_sku, $new_sku, $available_qty);
                                }
                            } else {
                                $json['error'] = $this->language->get('error_order_qty');
                            }
                        } else {
                            $json['error'] = $this->language->get('error_sku_change');
                        }
                    }
                } else {
                    $res = $this->model_account_customer_order->checkProductExistInfo($new_sku, $country_id);
                    if ($res == false) {
                        $json['error'] = $this->language->get('error_sku_exist');
                    } else {
                        $sku_change = $this->model_account_customer_order->checkLineSkuCanChange($line_id, $old_sku, $buyer_id);
                        if ($sku_change) {
                            $order_qty = $this->model_account_customer_order->getOrderLineSkuQty($line_id);
                            if ($order_qty > 0) {
                                //buyer曾经买过的此sku数量
                                //$stock_qty = $this->model_account_customer_order->getBuyerSumStockForSku($buyer_id, $new_sku);
                                //直接很残暴的使用如下代码
                                $sql = "SELECT
                                            cost.seller_id,
                                            cost.buyer_id,
                                            cost.onhand_qty,
                                            cost.id,
                                            rline.oc_order_id,
                                            cost.sku_id,
                                            ocp.order_product_id,
                                            cost.original_qty - ifnull(t.associateQty, 0)-ifnull(t2.qty,0) AS leftQty
                                        FROM
                                            tb_sys_cost_detail cost
                                        LEFT JOIN oc_product p ON cost.sku_id = p.product_id
                                        LEFT JOIN tb_sys_receive_line rline ON rline.id = cost.source_line_id
                                        LEFT JOIN oc_order_product ocp ON (
                                            ocp.order_id = rline.oc_order_id
                                            AND ocp.product_id = cost.sku_id
                                        )
                                        LEFT JOIN (
                                            SELECT
                                                sum(qty) AS associateQty,
                                                order_product_id
                                            FROM
                                                tb_sys_order_associated
                                            GROUP BY
                                                order_product_id
                                        ) t ON t.order_product_id = ocp.order_product_id
                                        LEFT JOIN (
                                            SELECT
                                                rop.product_id,
                                                ro.order_id,
                                                sum(rop.quantity) AS qty
                                            FROM
                                                oc_yzc_rma_order ro
                                            LEFT JOIN oc_yzc_rma_order_product rop ON rop.rma_id = ro.id
                                            WHERE
                                                ro.buyer_id = " . $customer_id . "
                                            AND ro.cancel_rma = 0
                                            AND status_refund <> 2
                                            AND ro.order_type = 2
                                            GROUP BY
                                                rop.product_id,ro.order_id
                                        ) t2 on t2.product_id=ocp.product_id and t2.order_id=ocp.order_id";
                                $sql .= " WHERE cost.onhand_qty > 0 AND type = 1 AND  p.sku='" . $new_sku . "' and cost.buyer_id = " . $customer_id;
                                $costArr = $this->db->query($sql)->rows;
                                $stock_qty = 0;
                                foreach ($costArr as $ks => $vs) {
                                    $stock_qty += $vs['leftQty'];
                                }
                                $used_qty = 0;
                                //if ($stock_qty > 0) {
                                //已消耗的数量
                                //$used_qty = $this->model_account_customer_order->getBuyerSumUsedStockForSku($buyer_id, $new_sku);
                                //}
                                $available_qty = $stock_qty - $used_qty;
                                $need_more_qty = $order_qty - $available_qty < 0 ? 0 : $order_qty - $available_qty;
                                if ($need_more_qty !== 0) {
                                    $json['success'] = sprintf($this->language->get('text_sku_inventory_1'), $old_sku, $new_sku, $available_qty, $need_more_qty);
                                } else {
                                    $json['success'] = sprintf($this->language->get('text_sku_inventory_2'), $old_sku, $new_sku, $available_qty);
                                }
                            } else {
                                $json['error'] = $this->language->get('error_order_qty');
                            }

                        } else {
                            $json['error'] = $this->language->get('error_sku_change');
                        }

                    }

                }


            } else {
                $json['error'] = $this->language->get('error_invalid_param');
            }
        }
        $this->response->headers->set('Content-Type', 'application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * 修改订单SKU
     * @throws Exception
     */
    public function changeSku()
    {
        $omd_order_sku_uuid = "c87f0069-e386-486e-a07d-f78e0e962a7c";

        load()->model("account/customer_order");
        load()->model("account/customer_order_import");
        load()->language('account/customer_order_import');
        $json = array();
        $line_id = $this->request->request['lineId'];
        $newSku = $this->request->request['newSku'];
        $country_id = $this->customer->getCountryId();

        if (isset($line_id) || isset($newSku)) {
            $param = array();
            $post_data = array();
            $record_data = array();
            date_default_timezone_set('America/Los_Angeles');
            $order_date_hour = date("G");
            $group_id = $this->customer->getGroupId();

            $currentOrderInfo = $this->model_account_customer_order->getCurrentOrderInfo($line_id);
            $order_info = current($currentOrderInfo);
            $header_id = $order_info['header_id'];
            $is_in_omd = $this->model_account_customer_order->checkOrderShouldInOmd($header_id);

            if ($is_in_omd && !($group_id == 1 && ($order_date_hour < 4 || $order_date_hour >= 12)) && !($group_id == 15 && $order_date_hour >= 13)) {
                $json['error'] = $this->language->get('error_cancel_time_late');
            } else {
                $process_code = CommonOrderProcessCode::CHANGE_SKU;
                $status = CommonOrderActionStatus::PENDING;
                $run_id = time();
                $order_id = $order_info['order_id'];
                $line_item_num = $order_info['line_item_number'];
                $old_sku = $order_info['item_code'];
                $is_syncing = $this->model_account_customer_order->checkOrderIsSyncing($header_id);
                if ($is_syncing) {
                    $json['error'] = $this->language->get('error_is_syncing');
                }
                $omd_store_id = $this->model_account_customer_order->getOmdStoreId($header_id);
                if (!isset($omd_store_id)) {
                    $json['error'] = $this->language->get('error_invalid_param');
                }
                if (!isset($json['fail'])) {
                    //订单类型，暂不考虑重发单
                    $order_type = 1;
                    $create_time = date("Y-m-d H:i:s");
                    $before_record = "Order_Id:" . $order_id . ", Line_item_number:" . $line_item_num . ", ItemCode:" . $old_sku;
                    $modified_record = "Order_Id:" . $order_id . ", Line_item_number:" . $line_item_num . ", ItemCode:" . $newSku;

                    $post_data['uuid'] = $omd_order_sku_uuid;
                    $post_data['runId'] = $run_id;
                    $post_data['storeId'] = $omd_store_id;
                    $post_data['orderId'] = $order_id;
                    $post_data['oldItemCode'] = $old_sku;
                    $post_data['newItemCode'] = $newSku;
                    $post_data['qty'] = $order_info['qty'];
                    $param['apiKey'] = OMD_POST_API_KEY;
                    $param['postValue'] = json_encode($post_data);

                    $record_data['process_code'] = $process_code;
                    $record_data['status'] = $status;
                    $record_data['run_id'] = $run_id;
                    $record_data['before_record'] = $before_record;
                    $record_data['modified_record'] = $modified_record;
                    $record_data['header_id'] = $header_id;
                    $record_data['order_id'] = $order_id;
                    $record_data['line_id'] = $order_info['line_id'];
                    $record_data['order_type'] = $order_type;
                    $record_data['remove_bind'] = 0;
                    $record_data['create_time'] = $create_time;

                    if ($is_in_omd) {
                        //保存修改记录
                        $log_id = $this->model_account_customer_order->saveSalesOrderModifyRecord($record_data);
                        $response = $this->sendCurl(OMD_POST_URL, $param);
                        if (isset($response) && 'OMD PROCESS SUCCESS' == $response) {
                            $json['success'] = $this->language->get('text_sku_wait');
                        } else {
                            $new_status = CommonOrderActionStatus::FAILED;
                            $fail_reason = $this->language->get('error_response');
                            $this->model_account_customer_order->updateSalesOrderModifyLog($log_id, $new_status, $fail_reason);
                            $json['error'] = $this->language->get('error_response');
                        }
                    } else {
                        //未同步到OMD的订单直接修改SKU
                        $change_result = $this->model_account_customer_order->changeSalesOrderLineSku($line_id, $old_sku, $newSku);

                        //非美国获取 这个sku 是否是超大件，然后 进行 order_status的变更
                        $this->model_account_customer_order->changeSkuForUpdateOrderStatus($newSku, $country_id, $order_id);

                        //校验可否绑定sales order和purchase order
                        $flag = $this->model_account_customer_order->checkAssociateOrder($header_id, $this->customer->getId());
                        if ($flag) {
                            $lineIdList = $this->model_account_customer_order->getLineIdListByHeaderId($header_id);
                            foreach ($lineIdList as $id => $sku) {
                                //绑定sales order和purchase order
                                $this->model_account_customer_order_import->associateOrder(null, $id, $sku);
                            }

                            $this->model_account_customer_order->changeSalesOrderStatus($header_id, $order_info['ship_method']);
                        }

                        if ($change_result) {
                            $record_data['status'] = CommonOrderActionStatus::SUCCESS;
                            //保存修改记录
                            $this->model_account_customer_order->saveSalesOrderModifyRecord($record_data);
                            $json['success'] = $this->language->get('text_change_sku_success');
                        } else {
                            $record_data['status'] = CommonOrderActionStatus::FAILED;
                            //保存修改记录
                            $this->model_account_customer_order->saveSalesOrderModifyRecord($record_data);
                            $json['success'] = $this->language->get('text_change_sku_failed');
                        }
                    }
                } else {
                    $json['error'] = $this->language->get('error_invalid_param');
                }
            }
        } else {
            $json['error'] = $this->language->get('error_invalid_param');
        }

        $this->response->headers->set('Content-Type', 'application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * 修改订单发货信息
     * @return JsonResponse
     * @throws Exception
     */
    public function changeOrderShipping()
    {
        //N-130 欧洲+日本New Order状态下增加修改地址和修改ItemCode功能 new order 订单仅仅是自己更改，无其他 ，ok。
        $omd_order_sku_uuid = "c9cedfd2-a209-4ece-be77-fb3915bdca0c";

        load()->model("account/customer_order");
        load()->language('account/customer_order_import');

        $data = $this->request->post();
        $header_id = $this->request->get('id');
        $json = array();
        if (!isset($header_id) || !isset($data)) {
            return $this->json(['error' => $this->language->get('error_invalid_param')]);
        }
        $current_order_info = $this->model_account_customer_order->getCurrentOrderInfoByHeaderId($header_id);
        if (isset($current_order_info) && sizeof($current_order_info) == 1) { //&& isset($omd_store_id)
            $order_info = current($current_order_info);

            $isApiOrder = false;
            if ($order_info['program_code'] == 'B2B SYN') { // 是通过api的单子
                $isApiOrder = true;
            }
            $len = configDB('config_b2b_address_len');
            if ($isApiOrder) {
                $countryId = $this->customer->getCountryId();
                if ($countryId == AMERICAN_COUNTRY_ID) {
                    $len = configDB('config_b2b_address_len_us1');
                } else if ($countryId == UK_COUNTRY_ID) {
                    $len = configDB('config_b2b_address_len_uk');
                } else if ($countryId == DE_COUNTRY_ID) {
                    $len = configDB('config_b2b_address_len_ude');
                } else if ($countryId == JAPAN_COUNTRY_ID) {
                    $len = configDB('config_b2b_address_len_jp');
                }
            }

            $email_reg = "/[\w-\.]+@([\w-]+\.)+[a-z]{2,3}/";
            //数据校验
            if (!isset($data['name']) || empty($data['name']) || strlen($data['name']) > 50) {
                $json['error'] = $this->language->get('error_ship_label_name');
            } elseif (!isset($data['email']) || empty($data['email']) || strlen($data['email']) > 90) {
                $json['error'] = $this->language->get('error_ship_label_email');
            } elseif (!preg_match($email_reg, $data['email'])) {
                $json['error'] = $this->language->get('error_ship_label_email_reg');
            } elseif (!isset($data['phone']) || empty($data['phone']) || strlen($data['phone']) > 45) {
                $json['error'] = $this->language->get('error_ship_label_phone');
            } elseif (!isset($data['address']) || empty($data['address']) || StringHelper::stringCharactersLen($data['address']) > $len) {
                $json['error'] = sprintf($this->language->get('error_ship_label_address_1'), $len);
            } elseif (!isset($data['city']) || empty($data['city']) || strlen($data['city']) > 40) {
                $json['error'] = $this->language->get('error_ship_label_city');
            } elseif (!isset($data['state']) || empty($data['state']) || $data['state'] == '0') {
                $json['error'] = $this->language->get('error_ship_label_state');
            } elseif (!isset($data['code']) || empty($data['code']) || strlen($data['code']) > 18) {
                $json['error'] = $this->language->get('error_ship_label_code');
            } elseif (!isset($data['country']) || empty($data['country']) || $data['country'] == '0') {
                $json['error'] = $this->language->get('error_ship_label_country');
            } elseif (strlen($data['comments']) > 1500) {
                $json['error'] = $this->language->get('error_ship_label_comments');
            }

            if (isset($json['error'])) {
                return $this->json(['error' => $json['error']]);
            }

            date_default_timezone_set('America/Los_Angeles');
            $order_date_hour = date("G");
            $group_id = $this->customer->getGroupId();
            $is_syncing = $this->model_account_customer_order->checkOrderIsSyncing($header_id);
            if ($is_syncing) {
                return $this->json(['error' => $this->language->get('error_is_syncing')]);
            }

            $param = array();
            $post_data = array();
            $record_data = array();

            $omd_store_id = $this->model_account_customer_order->getOmdStoreId($header_id);


            //只要订单BP了就不允许修改
            if (in_array($order_info['order_status'], [
                CustomerSalesOrderStatus::BEING_PROCESSED,
                CustomerSalesOrderStatus::CANCELED,
                CustomerSalesOrderStatus::COMPLETED,
            ])) {
                return $this->json(['error' => $this->language->get('text_change_ship_failed')]);
            }

            if (empty($group_id)) {
                $buyer_info = $this->model_account_customer_order->getSalesOrderBuyerInfo($header_id);
                if (isset($buyer_info['customer_group_id'])) {
                    $group_id = $buyer_info['customer_group_id'];
                }
            }
            if (
                ($order_info['order_status'] == CustomerSalesOrderStatus::BEING_PROCESSED || $order_info['order_status'] == CustomerSalesOrderStatus::ON_HOLD)
                && !($group_id == 1 && ($order_date_hour < 4 || $order_date_hour >= 12))
                && !($group_id == 15 && $order_date_hour >= 13)
            ) {
                return $this->json(['error' => $this->language->get('error_cancel_time_late')]);
            }


            $process_code = CommonOrderProcessCode::CHANGE_ADDRESS;
            $status = CommonOrderActionStatus::PENDING;
            $run_id = time();
            $header_id = $order_info['header_id'];
            $order_id = $order_info['order_id'];
            $order_type = 1;
            $create_time = date("Y-m-d H:i:s");
            $before_record = "Order_Id:" . $order_id . " ShipToName:" . app('db-aes')->decrypt($order_info['ship_name'])
                . " ShipToEmail:" .  app('db-aes')->decrypt($order_info['email']) . " ShipToPhone:" .  app('db-aes')->decrypt($order_info['ship_phone']) . " ShipToAddressDetail:" . app('db-aes')->decrypt($order_info['ship_address1'])
                . " ShipToCity:" . app('db-aes')->decrypt($order_info['ship_city']) . " ShipToState:" . $order_info['ship_state'] . " ShipToPostalCode:" . $order_info['ship_zip_code']
                . " ShipToCountry:" . $order_info['ship_country'] . " OrderComments:" . $order_info['customer_comments'];
            $modified_record = "Order_Id:" . $order_id . " ShipToName:" . $data['name']
                . " ShipToEmail:" . $data['email'] . " ShipToPhone:" . $data['phone'] . " ShipToAddressDetail:" . $data['address']
                . " ShipToCity:" . $data['city'] . " ShipToState:" . $data['state'] . " ShipToPostalCode:" . $data['code']
                . " ShipToCountry:" . $data['country'] . " OrderComments:" . $data['comments'];

            $post_data['uuid'] = $omd_order_sku_uuid;
            $post_data['runId'] = $run_id;
            $post_data['orderId'] = $order_id;
            $post_data['storeId'] = $omd_store_id;
            $post_data['shipData'] = $data;
            $param['apiKey'] = OMD_POST_API_KEY;
            $param['postValue'] = json_encode($post_data);

            $record_data['process_code'] = $process_code;
            $record_data['status'] = $status;
            $record_data['run_id'] = $run_id;
            $record_data['before_record'] = $before_record;
            $record_data['modified_record'] = $modified_record;
            $record_data['header_id'] = $header_id;
            $record_data['order_id'] = $order_id;
            $record_data['order_type'] = $order_type;
            $record_data['remove_bind'] = 0;
            $record_data['create_time'] = $create_time;

            //是否完全没同步
            $isExportInfo = app(CustomerSalesOrderRepository::class)->calculateSalesOrderIsExportedNumber($header_id);
            if ($isExportInfo['is_export_number'] > 0) {
                //根据绑定关系，可能有不同的seller，不同seller可能属于不同分组，分开处理
                $sellerList = app(CustomerRepository::class)->calculateSellerListBySalesOrderId($header_id);
                $haveGigaOnsite = $haveOmd = 0;
                foreach ($sellerList as $seller) {
                    if ($seller['accounting_type'] == CustomerAccountingType::GIGA_ONSIDE) {
                        $haveGigaOnsite = 1; //有卖家需要on_site
                    } else {
                        $haveOmd = 1; //有卖家需要omd
                    }
                }
                if ($haveGigaOnsite == 1 && $haveOmd == 1) {
                    return $this->json(['error' => $this->language->get('error_is_contact_service')]);
                } elseif ($haveGigaOnsite == 1) {
                    $isInOnsite = $this->model_account_customer_order->checkOrderShouldInGigaOnsite($header_id);
                    if ($isInOnsite) {
                        $log_id = $this->model_account_customer_order->saveSalesOrderModifyRecord($record_data);
                        $gigaResult = app(GigaOnsiteHelper::class)->updateOrderAddress($order_id, $data, $run_id);
                        if ($gigaResult['code'] == 1) {
                            return $this->json(['success' => $this->language->get('text_update_address_wait')]);
                        } else {
                            $new_status = CommonOrderActionStatus::FAILED;
                            $fail_reason = $this->language->get('error_response');
                            $this->model_account_customer_order->updateSalesOrderModifyLog($log_id, $new_status, $fail_reason);
                            return $this->json(['error' => $this->language->get('text_change_ship_failed')]);
                        }
                    }
                } elseif ($haveOmd == 1) {
                    if (!isset($omd_store_id)) {
                        return $this->json(['error' => $this->language->get('error_invalid_param')]);
                    }
                    $isInOmd = $this->model_account_customer_order->checkOrderShouldInOmd($header_id);
                    if ($isInOmd) {
                        //保存修改记录
                        $log_id = $this->model_account_customer_order->saveSalesOrderModifyRecord($record_data);
                        $response = $this->sendCurl(OMD_POST_URL, $param);
                        if (isset($response) && 'OMD PROCESS SUCCESS' == $response) {
                            return $this->json(['success' => $this->language->get('text_cancel_wait')]);
                        } else {
                            $new_status = CommonOrderActionStatus::FAILED;
                            $fail_reason = $this->language->get('error_response');
                            $this->model_account_customer_order->updateSalesOrderModifyLog($log_id, $new_status, $fail_reason);
                            return $this->json(['error' => $this->language->get('error_response')]);
                        }
                    }
                } else { //OMD 同步到B2B  没处理成功处于to be paid状态，没有绑定关系，但是需要同步给OMD
                    if ($isExportInfo['is_omd_number'] > 0) {
                        //保存修改记录
                        $log_id = $this->model_account_customer_order->saveSalesOrderModifyRecord($record_data);
                        $response = $this->sendCurl(OMD_POST_URL, $param);
                        if (isset($response) && 'OMD PROCESS SUCCESS' == $response) {
                            return $this->json(['success' => $this->language->get('text_cancel_wait')]);
                        } else {
                            $new_status = CommonOrderActionStatus::FAILED;
                            $fail_reason = $this->language->get('error_response');
                            $this->model_account_customer_order->updateSalesOrderModifyLog($log_id, $new_status, $fail_reason);
                            return $this->json(['error' => $this->language->get('error_response')]);
                        }
                    }
                }
            }

            //没有同步过，直接取消
            $change_result = $this->model_account_customer_order->changeSalesOrderShippingInformation($header_id, $data);
            if ($change_result) {
                $record_data['status'] = CommonOrderActionStatus::SUCCESS;
                //保存修改记录
                $this->model_account_customer_order->saveSalesOrderModifyRecord($record_data);
                return $this->json(['success' => $this->language->get('text_change_ship_success')]);
            } else {
                $record_data['status'] = CommonOrderActionStatus::FAILED;
                //保存修改记录
                $this->model_account_customer_order->saveSalesOrderModifyRecord($record_data);
                return $this->json(['error' => $this->language->get('text_change_ship_failed')]);
            }
        } else {
            return $this->json(['error' => $this->language->get('error_invalid_param')]);
        }
    }

    /**
     * 获取表格形式的修改错误日志输出
     * @param int $process_code
     * @param int $id 订单主键ID
     * @param string|null $line_id
     * @return string
     * @throws Exception
     */
    private function getOrderModifyFailureLog($process_code, $id, $line_id = null)
    {
        load()->model("account/customer_order");
        $failure_log_array = $this->model_account_customer_order->getLastFailureLog($process_code, $id, $line_id);
        $failure_log_html = "";
        if (!empty($failure_log_array)) {
            foreach ($failure_log_array as $log_detail) {
                switch ($log_detail['process_code']) {
                    case CommonOrderProcessCode::CHANGE_ADDRESS:
                        $log_detail['process_code'] = $this->language->get('text_modify_shipping');
                        break;
                    case CommonOrderProcessCode::CHANGE_SKU:
                        $log_detail['process_code'] = $this->language->get('text_modify_sku');
                        break;
                    case CommonOrderProcessCode::CANCEL_ORDER:
                        $log_detail['process_code'] = $this->language->get('text_order_cancel');
                        break;
                    default:
                        break;
                }
                $failure_log_html = "<table class=\"table table-hover\"><tbody>";
                $failure_log_html .= "<tr><th class=\"text-center\">" . $this->language->get('text_table_head_time') . "</th><td class=\"text-center\">" . preg_replace("/\s/", " ", $log_detail['operation_time']) . "</td></tr>";
                $failure_log_html .= "<tr><th class=\"text-center\">" . $this->language->get('text_table_head_type') . "</th><td class=\"text-center\">" . preg_replace("/\s/", " ", $log_detail['process_code']) . "</td></tr>";
                $failure_log_html .= "<tr><th class=\"text-center\">" . $this->language->get('text_table_head_before') . "</th><td class=\"text-center\">" . preg_replace("/\s/", " ", $log_detail['previous_status']) . "</td></tr>";
                $failure_log_html .= "<tr><th class=\"text-center\">" . $this->language->get('text_table_head_target') . "</th><td class=\"text-center\">" . preg_replace("/\s/", " ", $log_detail['target_status']) . "</td></tr>";
                $failure_log_html .= "<tr><th class=\"text-center\">" . $this->language->get('text_table_head_reason') . "</th><td class=\"text-center\">" . preg_replace("/\s/", " ", $log_detail['fail_reason']) . "</td></tr>";
                $failure_log_html .= "</tbody></table>";
            }
            return htmlentities($failure_log_html);
        }
    }

    /**
     * @deprecated
     * @throws Exception
     */
    public function updateUploadInfo()
    {
        load()->model('account/customer_order_import');
        $customer_id = $this->customer->getId();
        $runId = $this->request->get('runId');
        $warning = $this->request->get('warning');
        $update_info = [
            'handle_status' => 0,
            'handle_message' => 'upload failed, ' . $warning,
        ];
        $this->model_account_customer_order_import->updateUploadInfoStatus($runId, $customer_id, $update_info);
        $json = '';
        $this->response->headers->set('Content-Type', 'application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * 判断是否存在需要omd联动取消的订单
     * @throws Exception
     */
    public function checkOrderInOmd()
    {
        load()->model("account/customer_order");
        $ids = trim(request()->post('ids', ''), ',');
        $idArr = explode(',', $ids);
        foreach ($idArr as $id) {
            $is_in_omd = $this->model_account_customer_order->checkOrderShouldInOmd($id);
            if ($is_in_omd) {
                $this->response->headers->set('Content-Type', 'application/json');
                $this->response->setOutput(json_encode(true));
            }
        }
        $this->response->headers->set('Content-Type', 'application/json');
        $this->response->setOutput(json_encode(false));
    }

    /**
     * 批量取消NEW ORDER订单
     * @throws Exception
     */
    public function batchCancelOrder()
    {

        load()->model("account/customer_order");
        load()->language('account/customer_order_import');
        $country_id = $this->customer->getCountryId();
        $msg = [];
        $ids = trim(request()->post('ids', ''), ',');
        $idArr = explode(',', $ids);
        $salesOrderInfo = $this->model_account_customer_order->getSalesOrderInfo($idArr);
        foreach ($salesOrderInfo as $order) {

            if (!in_array($order['order_status'], [CustomerSalesOrderStatus::TO_BE_PAID, CustomerSalesOrderStatus::LTL_CHECK])) {//非New Order,LTL check 不处理
                continue;
            }
            $is_syncing = $this->model_account_customer_order->checkOrderIsSyncing($order['id']);
            if ($is_syncing) {
                $json['fail'] = $this->language->get('error_is_syncing');
            }
            $has_oversize = $this->model_account_customer_order->checkOrderContainsOversizeProduct($order['id']);
            if (!in_array($order['order_status'], [CustomerSalesOrderStatus::TO_BE_PAID, CustomerSalesOrderStatus::LTL_CHECK]) && $has_oversize) {
                $json['fail'] = $this->language->get('error_cancel_oversize');
            }

            $is_in_omd = $this->model_account_customer_order->checkOrderShouldInOmd($order['id']);
            $omd_store_id = $this->model_account_customer_order->getOmdStoreId($order['id']);
            if ($order['order_mode'] == CustomerSalesOrderMode::PICK_UP) {
                // order_status  1 ,2,4,  129
                $omd_store_id = $this->model_account_customer_order->getDropshipOmdStoreId($order['id']);
                if (!in_array($order['order_status'], CustomerSalesOrderStatus::canCancel())) {
                    $json['fail'] = $this->language->get('error_can_cancel');
                }

            } elseif ($is_in_omd) {
                if (isset($order['id']) && isset($order['order_id']) && isset($omd_store_id) && isset($order['order_date'])) {
                    //初步校验是否允许取消
                    //非New Order,On Hold,LTL Check,中国或美国买家经营美国业务的情况，即输入校验
                    $can_cancel = $this->model_account_customer_order->checkOrderCanBeCanceled($order['id'], $order['order_id'], $this->customer->isCollectionFromDomicile());
                    if (!$can_cancel) {
                        $json['fail'] = $this->language->get('error_can_cancel');
                    } else {//取消New Order 理论上不会运行到此，保险起见，此处校验保留
                        if ($order['order_status'] == CustomerSalesOrderStatus::BEING_PROCESSED || $order['order_status'] == CustomerSalesOrderStatus::ON_HOLD) {
                            date_default_timezone_set('America/Los_Angeles');
                            $order_date_hour = date("G");
                            $group_id = $this->customer->getGroupId();

                            if (!($group_id == 1 && ($order_date_hour < 4 || $order_date_hour >= 12)) && !($group_id == 15 && $order_date_hour >= 13)) {
                                $json['fail'] = $this->language->get('error_cancel_time_late');
                            }
                        } else if ($order['order_status'] == CustomerSalesOrderStatus::LTL_CHECK) {
                            $json['fail'] = $this->language->get('error_cancel_time_late');
                        }
                    }
                } else {
                    $json['fail'] = $this->language->get('error_cancel_param');
                }
            }


            if (!isset($json['fail'])) {
                $param = array();
                $post_data = array();

                //订单类型，暂不考虑重发单
                $order_type = 1;
                $create_time = date("Y-m-d H:i:s");
                $before_record = "Order_Id:" . $order['order_id'] . ", status:".CustomerSalesOrderStatus::getDescription(CustomerSalesOrderStatus::TO_BE_PAID);
                $modified_record = "Order_Id:" . $order['order_id'] . ", status:".CustomerSalesOrderStatus::getDescription(CustomerSalesOrderStatus::CANCELED);

                $run_id = time();
                $post_data['uuid'] = self::OMD_ORDER_CANCEL_UUID;
                $post_data['runId'] = $run_id;
                $post_data['orderId'] = $order['order_id'];
                $post_data['storeId'] = $omd_store_id;
                $param['apiKey'] = OMD_POST_API_KEY;
                $param['postValue'] = json_encode($post_data);
                $remove_stock = 0;
                $record_data = [
                    'process_code' => 3,
                    'status' => 1,
                    'run_id' => $run_id,
                    'before_record' => $before_record,
                    'modified_record' => $modified_record,
                    'header_id' => $order['id'],
                    'order_id' => $order['order_id'],
                    'order_type' => $order_type,
                    'remove_bind' => $remove_stock,
                    'create_time' => $create_time,
                    'cancel_reason' => ''
                ];

                if ($country_id != AMERICAN_COUNTRY_ID && CustomerSalesOrderStatus::TO_BE_PAID == $order['order_status']) {
                    $cancel_result = $this->model_account_customer_order->cancelOrderFromNewOrder($order['id']);
                    if ($cancel_result) {
                        $record_data['status'] = CommonOrderActionStatus::SUCCESS;
                        //保存修改记录
                        $this->model_account_customer_order->saveSalesOrderModifyRecord($record_data);
                        $json['success'] = $this->language->get('text_cancel_success');
                    } else {
                        $record_data['status'] = CommonOrderActionStatus::FAILED;
                        //保存修改记录
                        $this->model_account_customer_order->saveSalesOrderModifyRecord($record_data);
                        $json['success'] = $this->language->get('text_cancel_failed');
                    }
                } elseif ($order['order_mode'] == CustomerSalesOrderMode::PICK_UP && CustomerSalesOrderStatus::TO_BE_PAID == $order['order_status']) {
                    ob_end_clean();
                    $this->orm->table('tb_sys_customer_sales_dropship_file_details')->where('order_id', $order['id'])->update(['status' => 0]);

                    $cancel_result = $this->model_account_customer_order->cancelOrderFromNewOrder($order['id']);
                    if ($cancel_result) {
                        $record_data['status'] = CommonOrderActionStatus::SUCCESS;
                        //保存修改记录
                        $this->model_account_customer_order->saveSalesOrderModifyRecord($record_data);
                        $json['success'] = $this->language->get('text_cancel_success');
                    } else {
                        $record_data['status'] = CommonOrderActionStatus::FAILED;
                        //保存修改记录
                        $this->model_account_customer_order->saveSalesOrderModifyRecord($record_data);
                        $json['success'] = $this->language->get('text_cancel_failed');
                    }


                } elseif (!$is_in_omd) {
                    //不在OMD里的订单直接执行取消操作
                    $cancel_result = $this->model_account_customer_order->cancelOrderFromNewOrder($order['id']);
                    $remove_bind_result = true;
                    if ($remove_stock == 1) {
                        $remove_bind_result = $this->model_account_customer_order->removeSalesOrderBind($order['id']);
                        // 解除仓租绑定
                        app(StorageFeeService::class)->unbindBySalesOrder([$order['id']]);
                    }
                    if ($cancel_result && $remove_bind_result) {
                        // 取消保障服务费用单
                        app(FeeOrderService::class)->cancelSafeguardFeeOrderBySalesOrderId($order['id']);
                        // 仓租费用单退款
                        app(FeeOrderService::class)->refundStorageFeeOrderBySalesOrderId($order['id']);

                        $record_data['status'] = CommonOrderActionStatus::SUCCESS;
                        //保存修改记录
                        $this->model_account_customer_order->saveSalesOrderModifyRecord($record_data);
                        $json['success'] = $this->language->get('text_cancel_success');
                    } else {
                        $record_data['status'] = CommonOrderActionStatus::FAILED;
                        //保存修改记录
                        $this->model_account_customer_order->saveSalesOrderModifyRecord($record_data);
                        $json['success'] = $this->language->get('text_cancel_failed');
                    }
                } else {
                    //保存修改记录
                    $log_id = $this->model_account_customer_order->saveSalesOrderModifyRecord($record_data);
                    $response = $this->sendCurl(OMD_POST_URL, $param);
                    //
                    if (isset($response) && 'OMD PROCESS SUCCESS' == $response) {
                        //取消的订单tracking_number 可以重复 ，美国dropship业务使用。
                        //$this->orm->table('tb_sys_customer_sales_dropship_file_details')->where('order_id',$id)->update(['status'=> 0 ]);
                        $json['success'] = $this->language->get('text_cancel_wait');

                    } else {
                        $new_status = CommonOrderActionStatus::FAILED;
                        $fail_reason = $this->language->get('error_response');
                        $this->model_account_customer_order->updateSalesOrderModifyLog($log_id, $new_status, $fail_reason);
                        $json['fail'] = $this->language->get('error_response');
                    }
                }
            }

            $json['order_id'] = $order['order_id'];
            $msg[] = $json;
        }

        $this->response->headers->set('Content-Type', 'application/json');
        $this->response->setOutput(json_encode($msg));
    }

    /**
     * 下载label示例
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function downloadTemplateLabel()
    {
        $request = App::request();
        $label_type = $request->get('label_type');//label_type:1是ltl label,2是packing slip,为空则是普通label
        $path = '';
        if ($label_type == HomePickOtherLabelType::LABEL) {
            $path = "download/LTL Label Sample.zip";
        }
        if ($label_type == HomePickOtherLabelType::PACKING_SLIP) {
            $path = "download/Packing Slip Sample.zip";
        }
        if ($label_type == ' ') {
            $path = "download/Small Parcel label Sample.zip";
        }
        return $this->response->download('storage/' . $path);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws Exception
     */
    public function getLabelViewInfo()
    {
        $json = $this->getLabelViewData($this->customer->getId());
        return $this->response->json($json);
    }

    /**
     * @param int $customerId
     * @return array
     * @throws Exception
     */
    private function getLabelViewData($customerId)
    {
        load()->model('account/customer_order_import');
        load()->language('account/customer_order_import');
        $num = $this->model_account_customer_order_import->getNoticeLabelNum($customerId);
        $json = [
            'error' => YesNoEnum::NO,
            'data' => []
        ];
        if ($num) {
            $json['error'] = YesNoEnum::YES;
            $json['data'][] = sprintf($this->language->get('text_notice_label'), $num);
        }
        // #15337 增加有待支付仓租的提醒
        $needFeeToBePaidNum = app(CustomerSalesOrderRepository::class)
            ->getCustomerSalesOrderCountByStatus($customerId, CustomerSalesOrderStatus::PENDING_CHARGES);
        if ($needFeeToBePaidNum > 0) {
            $json['error'] = YesNoEnum::YES;
            $json['data'][] = "There are <span style='color:red;font-weight: bold'>{$needFeeToBePaidNum}</span> orders with pending charges, and the shipment can only be made after payment.&nbsp;<a style='cursor: pointer;color:#3A75DC' onclick='getFeeToBePaidView(this)'>Click to view</a>.";
        }
        //自提货待提货提醒
        $waitingForPickUpNum = app(CustomerSalesOrderRepository::class)
            ->getCustomerSalesOrderCountByStatus($customerId, CustomerSalesOrderStatus::WAITING_FOR_PICK_UP);
        if ($waitingForPickUpNum > 0) {
            $json['error'] = YesNoEnum::YES;
            $json['data'][] = "There are <span style='color:red;font-weight: bold'>{$waitingForPickUpNum}</span> Buyer Pick-up orders to be picked up, please complete the pick-up within the specified time.&nbsp;<a style='cursor: pointer;color:#3A75DC' onclick='getWaitingForPickUpView(this)'>Click to view</a>.";
        }
        //自提货待确认提醒
        $pickUpInfoTbcNum = CustomerSalesOrderPickUp::query()->alias('pu')
            ->leftJoinRelations(['salesOrder as so'])
            ->where('so.buyer_id', $customerId)
            ->where('so.import_mode', HomePickImportMode::IMPORT_MODE_BUYER_PICK_UP)
            ->where('so.order_status', CustomerSalesOrderStatus::BEING_PROCESSED)
            ->where('pu.pick_up_status', CustomerSalesOrderPickUpStatus::PICK_UP_INFO_TBC)
            ->count();
        if ($pickUpInfoTbcNum > 0) {
            $json['error'] = YesNoEnum::YES;
            $json['data'][] = "There are <span style='color:red;font-weight: bold'>{$pickUpInfoTbcNum}</span> Buyer Pick-up orders to be confirmed. If they are not confirmed within 48 hours, the orders will be cancelled automatically.&nbsp;<a style='cursor: pointer;color:#3A75DC' onclick='getPickUpInfoTbView(this)'>Click to view</a>.";
        }
        return $json;
    }

    /**
     * 美国上门取货other label上传处理
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws Exception
     */
    public function usOtherFileDeal()
    {
        load()->language('account/customer_order_import');
        load()->model('account/customer_order_import');
        //裁剪方式
        $ship_method_type = request('cut_type');
        //container_id
        $container_id = request('container_id');
        $container_info = explode('_', $container_id);
        $json = [];
        //上传文件
        /** @var Symfony\Component\HttpFoundation\File\UploadedFile $fileInfo */
        $fileInfo = $this->request->filesBag->get('files');
        // 检查文件名以及文件类型
        if (isset($fileInfo)) {
            $fileName = $fileInfo->getClientOriginalName();
            $fileType = strrchr($fileName, '.');
            if (in_array($fileType, self::PDF_SUFFIX)) {
                $json['error'] = $this->language->get('error_filetype');
            }
            if ($fileInfo->getError() != UPLOAD_ERR_OK) {
                $json['error'] = $this->language->get('error_upload_' . $fileInfo->getError());
            }
        } else {
            $json['error'] = $this->language->get('error_upload');
        }

        if (!isset($json['error'])) {
            //放置文件
            $path = 'dropshipPdf/' . date('Y-m-d', time()) . '/';
            $file_name = date('YmdHis', time()) . '_' . token(20) . $fileType;
            $uploadFile = $path . $file_name;
            StorageCloud::storage()->writeFile($fileInfo, $path, $file_name);
            //查询订单信息
            $purchaseOrderInfo = $this->model_account_customer_order_import->getUsSalesOrderInfoByOrderIdAndTempId($container_info[0], $container_info[2]);
            $pathPrefix = 'dropshipPdf/' . date('Y-m-d', time()) . '/splitPdf/' . substr($file_name, 0, -4);
            $fileData = [
                "file_name" => $fileInfo->getClientOriginalName(),
                "size" => $fileInfo->getSize(),
                "file_path" => $uploadFile,
                'container_id' => $container_id,
                'order_id' => $purchaseOrderInfo['order_id'],
                'create_user_name' => $this->customer->getId(),
                'create_time' => date("Y-m-d H:i:s"),
                'run_id' => $purchaseOrderInfo['run_id'],
                'label_type' => HomePickOtherLabelType::LABEL,
                'tracking_number_img' => StorageCloud::storage()->getUrl($pathPrefix . '-orderId.png', ['check-exist' => false]),
                'order_id_img' => StorageCloud::storage()->getUrl($pathPrefix . '-trackingNumber.png', ['check-exist' => false]),
                'weight_img' => StorageCloud::storage()->getUrl($pathPrefix . '-weight.png', ['check-exist' => false]),
                'deal_file_path' => StorageCloud::storage()->getUrl($path . 'splitPdf/' . $file_name, ['check-exist' => false]),
                'deal_file_name' => str_replace(' ', '', $fileName)
            ];

            //美国上门取货other导单 ltl：packing slip 不需要裁剪与tracking number
            if ($ship_method_type == HomePickCarrierType::US_PICK_UP_OTHER_LTL_PACKING_SLIP) {
                $fileData['deal_file_path'] = StorageCloud::storage()->getUrl($fileData['file_path'], ['check-exist' => false]);
                $fileData['label_type'] = HomePickOtherLabelType::PACKING_SLIP;
                $fileData['tracking_number'] = '';
                //记录文件上传
                $insertId = $this->model_account_customer_order_import->insertDropshipUploadFile($fileData);
                $json['error'] = 0;
                $json['data'] = $fileData;
                return $this->response->json($json);
            }

            $fileData['deal_file_path'] = StorageCloud::storage()->getUrl($path . 'splitPdf/' . $file_name, ['check-exist' => false]);
            //记录文件上传
            $insertId = $this->model_account_customer_order_import->insertDropshipUploadFile($fileData);

            $sku = $purchaseOrderInfo['item_code'];
            //判断是否为combo
            $comboExists = $this->model_account_customer_order_import->checkComboAmountBySku($sku);
            $page = [];
            if ($comboExists) {
                $sku = $this->model_account_customer_order_import->getSkuByProductId(explode('_', $container_id)[1]);
                $page[0] = explode('_', $container_id)[4];
                $page[1] = explode('_', $container_id)[5];
            } else {
                $page[0] = $page[1] = 1;
            }
            $ship_service_level = CustomerSalesOrder::query()->where(['order_id'=>$fileData['order_id'],'buyer_id'=>customer()->getId()])->value('ship_service_level');
            //裁剪
            $res = $this->getDealPdf($fileData['file_path'], $fileData['order_id'], $sku, $ship_method_type, $page,strtoupper($ship_service_level));
            if (!$res) { //裁剪失败
                $json['error'] = 1;
                $json['msg'] = $this->language->get('error_file_size');
                $this->model_account_customer_order_import->updateDropshipUploadFile(['id' => $insertId], ['status' => 0]);
            } else if (isset($res['error']) && $res['error'] == 1) {//新增尺寸问题，裁剪失败
                $json['error'] = 1;
                $json['msg'] = $this->language->get('error_us_other_file_size');
                $this->model_account_customer_order_import->updateDropshipUploadFile(['id' => $insertId], ['status' => 0]);
            } else { //裁剪成功
                if ($res === true) { // 没有tracking number返回
                    //美国上门取货other导单 非ltl：tracking number要识别到
                    if (!in_array($ship_method_type,[ HomePickCarrierType::US_PICK_UP_OTHER_LTL_LABEL ])){
                        $json['error'] = 1;
                        $json['msg'] = $this->language->get('error_file_size');
                    } else {
                        $json['error'] = 0;
                        $fileData['tracking_number'] = '';
                    }
                } else { // 有tracking number返回
                    $json['error'] = 0;
                    $fileData['tracking_number'] = trim($res);
                }
                if ($json['error'] == 0) {
                    $update['update_time'] = date("Y-m-d H:i:s");
                    $update['update_user_name'] = $this->customer->getId();
                    $update['tracking_number'] = $fileData['tracking_number'];
                    $this->model_account_customer_order_import->updateDropshipUploadFile(['id' => $insertId], $update);
                    $json['data'] = $fileData;
                }
            }
        }
        return $this->response->json($json);
    }

    /**
     * 订单是否可以提交label的修改
     * @return JsonResponse
     * @throws Exception
     */
    public function usOtherReviewStatus()
    {
        load()->language('account/customer_order_import');
        load()->model('account/customer_order_import');
        //裁剪方式
        $order_id = $this->request->post('order_id');
        $res = $this->model_account_customer_order_import->usPickUpOtherOrderAndReviewStatus(intval($order_id));
        $json = [];
        //根据order_status订单状态分四大种可以编辑提交label
        //1.check label
        if ($res['order_status'] == CustomerSalesOrderStatus::CHECK_LABEL) {
            $json = ['error' => 0];
            goto end;
        }
        //2.New order 且label不在审核中
        if ($res['order_status'] == CustomerSalesOrderStatus::TO_BE_PAID && $res['status'] != HomePickLabelReviewStatus::PENDING) {
            $json = ['error' => 0];
            goto end;
        }
        //3.Pending Charges 且label不在审核中
        if ($res['order_status'] == CustomerSalesOrderStatus::PENDING_CHARGES && $res['status'] != HomePickLabelReviewStatus::PENDING) {
            $json = ['error' => 0];
            goto end;
        }
        //4.BP 且label不在审核与审核通过中
        if ($res['order_status'] == CustomerSalesOrderStatus::BEING_PROCESSED && $res['status'] != HomePickLabelReviewStatus::PENDING && $res['status'] != HomePickLabelReviewStatus::APPROVED) {
            $json = ['error' => 0];
            goto end;
        }
        //其余都不能修改
        $json = ['error' => 1, 'msg' => $this->language->get('error_us_other_order_review_approved')];

        end:
        return $this->response->json($json);
    }

    /**
     * 美国上门取货other导单可编辑弹窗
     * @param int $order_id  tb_sys_customer_sales_order主键id
     * @return bool
     */
    public function judgeUsOtherCanEditUploadLabel($order_id)
    {
        $res = $this->model_account_customer_order_import->usPickUpOtherOrderAndReviewStatus(intval($order_id));
        //以下2种状态，展示编辑的按钮
        //1.Check Label 、New Order 、Pending Charges
        if ($res['order_status'] == CustomerSalesOrderStatus::CHECK_LABEL || $res['order_status'] == CustomerSalesOrderStatus::TO_BE_PAID || $res['order_status'] == CustomerSalesOrderStatus::PENDING_CHARGES) {
            return true;
        }
        //2.BP 且label不能为审核通过
        if ($res['order_status'] == CustomerSalesOrderStatus::BEING_PROCESSED && $res['status'] != HomePickLabelReviewStatus::APPROVED) {
            return true;
        }
        return false;
    }

    /**
     * 获取销售单历史的绑定关系
     *
     * @params sales_order_id
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getSalesOrderAssociatedDeletedRecordList()
    {
        $validator = validator($this->request->get(), [
            'sales_order_id' => 'required'
        ]);
        // 判断校验是否正确，与返回处理
        if ($validator->fails()) {
            return $this->jsonFailed($validator->errors()->first());
        }
        $list = OrderAssociatedDeletedRecord::query()
            ->where('sales_order_id', '=', $this->request->get('sales_order_id'))
            ->get(['order_id', 'qty']);
        if ($list->isEmpty()) {
            return $this->jsonFailed('Not Data!');
        }
        return $this->jsonSuccess($list);
    }
    /**
     * onhold 情况下release 订单
     * @see ControllerAccountSalesOrderSalesOrderManagement::releaseOrder()
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function releaseOrder()
    {
        $id = request()->post('id', 0);
        $order_info = $this->sales_model->getReleaseOrderInfo($id);
        $this->sales_model->releaseOrder($id, $order_info['order_status'], $order_info['type'],customer()->getId());
        $order_info_ret = $this->sales_model->getReleaseOrderInfo($id);
        if ($order_info_ret['order_status'] == CustomerSalesOrderStatus::TO_BE_PAID) {
            $json['msg'] = $this->language->get('text_success_release');
            $json['code'] = 200;
        } elseif ($order_info_ret['order_status'] == CustomerSalesOrderStatus::LTL_CHECK) {
            $json['msg'] = $this->language->get('text_success_release');
            $json['code'] = 0;
        } else {
            $json['msg'] = $this->language->get('text_error_release');
            $json['code'] = 0;
        }
        $this->response->headers->set('Content-Type', 'application/json');
        if($json['code'] == 200){
            return $this->jsonSuccess([],$json['msg']);
        } else {
            return $this->jsonFailed($json['msg']);
        }
    }

    /**
     * onhold 情况下批量release 订单
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @see ControllerAccountSalesOrderSalesOrderManagement::batchReleaseOrder()
     * @throws Exception
     */
    public function batchReleaseOrder()
    {
        trim_strings($this->request->post);
        $post = $this->request->post;
        $id = request()->post('id', 0);
        try {
            $this->orm->getConnection()->beginTransaction();
            $order_info = $this->sales_model->getBatchReleaseOrderInfo($id);
            $this->sales_model->batchReleaseOrder($order_info,customer()->getId());
            $order_info_ret = $this->sales_model->getBatchReleaseOrderInfo($id);
            $order_status_num = array_sum(array_unique(array_column($order_info_ret['order_status'],'order_status')));
            $this->orm->getConnection()->commit();
        } catch (Exception $e) {
            $this->log->write('release 订单错误.(上门取货)');
            $this->log->write($post);
            $this->log->write($e);
            $this->orm->getConnection()->rollBack();
            $order_status_num = 0;
        }
        if ($order_status_num = 1 || $order_status_num = 64 || $order_status_num == 65) {
            $json['msg'] = $this->language->get('text_success_release');
            $json['code'] = 200;
        } else {
            $json['msg'] = $this->language->get('text_error_release');
            $json['code'] = 0;
        }
        $this->response->headers->set('Content-Type', 'application/json');
        if($json['code']==200){
            return $this->jsonSuccess([],$json['msg']);
        } else {
            return $this->jsonFailed($json['msg']);
        }
    }
}
