<?php

use App\Enums\Common\YesNoEnum;
use App\Enums\ModifyLog\CommonOrderActionStatus;
use App\Enums\ModifyLog\CommonOrderProcessCode;
use App\Helper\AddressHelper;
use App\Helper\StringHelper;
use App\Catalog\Controllers\AuthSellerController;
use App\Models\Customer\CustomerExts;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Models\SalesOrder\CustomerSalesOrder;
use App\Models\SalesOrder\CustomerSalesOrderLine;
use App\Repositories\Setup\SetupRepository;
use App\Services\SalesOrder\SalesOrderService;
use Catalog\model\customerpartner\SalesOrderManagement;
use Catalog\model\account\sales_order\SalesOrderManagement as AccountSalesOrderManagement;
use App\Enums\Customer\CustomerAccountingType;
use App\Helper\GigaOnsiteHelper;
use App\Repositories\SalesOrder\CustomerSalesOrderRepository;

/**
 * Class ControllerAccountCustomerpartnerSalesOrderList
 *
 * @property ModelAccountCustomerOrder $model_account_customer_order
 * @property ModelAccountCustomerOrderImport $model_account_customer_order_import
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelLocalisationZone $model_localisation_zone
 */
class ControllerAccountCustomerpartnerSalesOrderList extends AuthSellerController
{

    private $customer_id;
    private $country_id;
    private $isPartner;
    private $sales_model;
    protected $tracking_privilege;

    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->customer_id = $this->customer->getId();
        $this->isPartner = $this->customer->isPartner();
        $this->country_id = $this->customer->getCountryId();
        $this->load->language('account/customerpartner/sales_order_management');
        $this->load->model('account/customer_order');
        $this->sales_model = new SalesOrderManagement($registry);
        $this->tracking_privilege = $this->sales_model->getTrackingPrivilege($this->customer_id, $this->isPartner);
        if (
            !(($this->customer->isUSA() && $this->customer->isOuterAccount())
            || $this->customer->getGroupId() == 23
            || ($this->customer->isUSA() && $this->customer->isTesterAccount() && $this->customer->isPartner())
            ||  ($this->customer->isEurope() && !$this->customer->isInnerAccount() && !in_array($this->customer->getId(),SERVICE_STORE_ARRAY) && $this->customer->isPartner()))
            || CustomerExts::query()->where([
                'customer_id'=> customer()->getId(),
                'not_support_self_delivery'=> YesNoEnum::YES,
            ])->exists()
        ){
            return $this->response->redirectTo(url()->to(['customerpartner/seller_center/index']))->send();
        }
    }

    public function index()
    {
        $this->document->setTitle($this->language->get('text_sales_order_list'));
        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_sales_order_management'),
                'href' => 'javascript:void(0);',
                'separator' => $this->language->get('text_separator')
            ],
            [
                'text' => $this->language->get('text_sales_order_list'),
                'href' => url()->to(['account/customerpartner/sales_order_list']),
                'separator' => $this->language->get('text_separator')
            ]
        ];
        $data['help_info'] = url()->to(['account/customerpartner/sales_order_management/shippingInformationGuide']);
        $data['upload_url'] = url()->to(['account/customerpartner/sales_order_management']);
        $data['sales_order_url'] = url()->to(['account/customerpartner/sales_order_list']);
        // js css
        $this->document->addScript('catalog/view/javascript/jquery/datetimepicker/moment/moment.min.js');
        $this->document->addScript('catalog/view/javascript/jquery/datetimepicker/moment/moment-with-locales.min.js');
        $this->document->addScript('catalog/view/javascript/bootstrap/js/bootstrap-paginator.min.js');
        $this->document->addScript('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.js');
        $this->document->addStyle('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.css');

        if (
            configDB('marketplace_separate_view')
            && isset($this->session->data['marketplace_separate_view'])
            && $this->session->data['marketplace_separate_view'] == 'separate'
        ) {
            $data['separate_view'] = true;
            $data['column_left'] = '';
            $data['column_right'] = '';
            $data['content_top'] = '';
            $data['content_bottom'] = '';
            $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');
            $data['footer'] = $this->load->controller('account/customerpartner/footer');
            $data['header'] = $this->load->controller('account/customerpartner/header');
        } else {
            $data['column_left'] = $this->load->controller('common/column_left');
            $data['column_right'] = $this->load->controller('common/column_right');
            $data['content_top'] = $this->load->controller('common/content_top');
            $data['content_bottom'] = $this->load->controller('common/content_bottom');
            $data['footer'] = $this->load->controller('common/footer');
            $data['header'] = $this->load->controller('common/header');
            $data['separate_view'] = false;
            $data['separate_column_left'] = '';
        }
        $this->response->setOutput($this->load->view('account/customerpartner/sales_order_list', $data));
    }

    // 订单列表
    public function orderList()
    {
        // request里面的数据也需要返回
        $data = $this->request->request;
        $data['tracking_privilege'] = $this->tracking_privilege;
        // url
        $data['order_url'] = url()->to(['account/customerpartner/sales_order_list/orderList']);
        $data['download_url'] = url()->to(['account/customerpartner/sales_order_list/downloadCsv']);
        // 订单状态列表 用于前台显示
        $data['order_status_list'] = [
            CustomerSalesOrderStatus::ABNORMAL_ORDER => 'Abnormal Order',
            CustomerSalesOrderStatus::BEING_PROCESSED => 'Being Processed',
            CustomerSalesOrderStatus::ON_HOLD => 'On Hold',
            CustomerSalesOrderStatus::CANCELED => 'Canceled',
            CustomerSalesOrderStatus::COMPLETED => 'Completed',
            CustomerSalesOrderStatus::LTL_CHECK => 'LTL Check',
        ];
        // 订单状态对应的颜色关系
        $data['order_status_color_list'] = [
            CustomerSalesOrderStatus::ABNORMAL_ORDER => '#D9001B',
            CustomerSalesOrderStatus::BEING_PROCESSED => '#008000',
            CustomerSalesOrderStatus::ON_HOLD => '#666666',
            CustomerSalesOrderStatus::CANCELED => '#666666',
            CustomerSalesOrderStatus::COMPLETED => '#666666',
            CustomerSalesOrderStatus::LTL_CHECK => '#008000',
        ];
        $isEurope = Customer()->isEurope();
        if($isEurope){
            unset( $data['order_status_list'][CustomerSalesOrderStatus::LTL_CHECK]);
            unset( $data['order_status_color_list'][CustomerSalesOrderStatus::LTL_CHECK]);
        }
        $sales_model = new SalesOrderManagement($this->registry);
        $accountSalesModel = new AccountSalesOrderManagement($this->registry);
        // orders
        $data['page'] = $data['page'] ?? 1;
        $data['page_limit'] = $data['page_limit'] ?? 10;
        $orders = $sales_model->getSalesOrderList($this->customer->getId(), $data);
        array_walk($orders, function (&$item) use ($sales_model, $accountSalesModel) {
            // 判断信息是否有误
            $judge_column = ['ship_phone', 'ship_address1', 'ship_state', 'ship_city', 'ship_zip_code'];
            foreach ($judge_column as $k => $v) {
                $s = $sales_model->dealErrorCode($item[$v]);
                $item[$v . '_show'] = $item[$v];
                if ($s != false) {
                    $item[$v] = $s;
                    $column = 'text_error_column_' . $item['order_status'];
                    if ($k == 0) {
                        $ship_phone_tips = sprintf(
                            $this->language->get('text_error_tool_tip'),
                            sprintf($this->language->get($column), 'Recipient Phone#')
                        );
                    } else {
                        $ship_address_tips = sprintf(
                            $this->language->get('text_error_tool_tip'),
                            sprintf($this->language->get($column), 'Shipping Address')
                        );
                    }
                }
            }

            $item['detail_address'] = $item['ship_address1']
                . ',' . $item['ship_city'] . ',' . $item['ship_zip_code']
                . ',' . $item['ship_state'] . ',' . $item['ship_country'] . ($ship_address_tips ?? '');
            $item['ShipPhone'] = $item['ship_phone'] . ($ship_phone_tips ?? '');
            // tracking info
            $trackingNumber = [];
            $trackStatus = [];
            $carrierName = [];
            if ((CustomerSalesOrderStatus::COMPLETED == $item['order_status'] && $this->tracking_privilege) || !$this->tracking_privilege) {
                $tracking_array = $this->model_account_customer_order->getTrackingNumber($item['id'], $item['order_id']);
            } else {
                $tracking_array = [];
            }
            if (!empty($tracking_array)) {
                foreach ($tracking_array as $track) {
                    $track_temp = explode(',', $track['trackingNo']);
                    $track_size = count($track_temp);
                    for ($i = 0; $i < $track_size; $i++) {
                        $carrierName[] = $track['carrierName'];
                        $trackStatus[] = ($track['status'] == 0) ? 0 : 1;
                    }
                    $trackingNumber = array_merge($trackingNumber, $track_temp);
                }
            }
            $item['TrackingNumber'] = $trackingNumber;
            $item['TrackingStatus'] = $trackStatus;
            $item['CarrierName'] = $carrierName;
            // ASR
            $item['SignatureService'] = (strcasecmp(trim($item['ship_method']), 'ASR') == 0) ? 'Yes' : 'No';
            // Order Status
            $item['OrderStatus'] = $item['order_status'];
            // tags
            $item['product_tags'] = $this->getSalesOrderProductTag($item['id']);
            // failure log
            $failure_log_html = $this->getOrderModifyFailureLog(CommonOrderProcessCode::CANCEL_ORDER, $item['id']);
            //如果取消操作的失败日志没有，再查询修改发货信息的错误日志
            if (empty($failure_log_html)) {
                $failure_log_html = $this->getOrderModifyFailureLog(CommonOrderProcessCode::CHANGE_ADDRESS, $item['id']);
            }
            $item['failure_log'] = $failure_log_html;
            $item['freight_error'] = '';
            if (
                $item['is_international']
                && in_array($this->country_id, EUROPE_COUNTRY_ID)
                && !$accountSalesModel->getInternationalOrder($item['ship_country'], $this->country_id, $item['ship_zip_code'])
            ) {
                $item['freight_error'] = 'An auto-generated fulfillment quote estimate is currently not available for the country selected. Please contact Customer Service for a fulfillment quote after successfully importing the sales order.';
            }
        });
        $data['orders'] = $orders;
        // 分页信息
        $paramTotal = $this->request->request;
        $paramTotal['tracking_privilege'] = $this->tracking_privilege;
        $data['total'] = $sales_model->getSalesOrderTotal($this->customer->getId(), $paramTotal);
        $data['total_pages'] = ceil($data['total'] / $data['page_limit']);
        // other
        $data['country_id'] = $this->customer->getCountryId();
        $data['is_europe'] = $this->customer->isEurope();

        $this->response->setOutput($this->load->view('account/customerpartner/sales_order_info_lists', $data));
    }

    // 获取旧的地址信息
    public function orderOldAddress()
    {
        $order_id = request('order_id');
        $accountSalesModel = new AccountSalesOrderManagement($this->registry);
        $info = $accountSalesModel->orderAddress($order_id);
        if ($info) {
            foreach ($info as $key => $val) {
                $info[$key] = trim($val);
                if ($info[$key] == 'NULL' || $info[$key] == 'null' || $info[$key] == null) {
                    $info[$key] = '';
                }
            }
            $info['orignName'] = $info['ship_name'] != '' ? $info['ship_name'] . '  ' : '';
            $info['orignName'] .= $info['ship_phone'] != '' ? $info['ship_phone'] . '  ' : '';
            $info['orignName'] .= $info['email'];
            $info['orignAddr'] = '';
            $info['orignAddr'] .= $info['ship_address1'] != '' ? $info['ship_address1'] . ',' : '';
            $info['orignAddr'] .= $info['ship_city'] != '' ? $info['ship_city'] . ',' : '';
            $info['orignAddr'] .= $info['ship_zip_code'] != '' ? $info['ship_zip_code'] . ',' : '';
            $info['orignAddr'] .= $info['ship_state'] != '' ? $info['ship_state'] . ',' : '';
            $info['orignAddr'] .= $info['ship_country'];
            $info['is_japan'] = customer()->isJapan();
            //如果是JP
            if ($info['is_japan']) {
                $info['state_exists'] = $accountSalesModel->existsByCountyAndCountry(trim($info['ship_state']), JAPAN_COUNTRY_ID);
                $info['state'] = $accountSalesModel->getStateByCountry(JAPAN_COUNTRY_ID);
            }
        }

        return $this->response->json(['error' => 0, 'info' => $info]);
    }

    public function getCountryInfo()
    {
        $keyword = $this->request->query->get('keyword');
        $json = (new AccountSalesOrderManagement($this->registry))->getCountryList($keyword, $this->country_id);
        return $this->response->json($json);
    }

    public function salesOrderDetails()
    {
        $this->load->language('account/customer_order_import');
        $this->load->language('account/customer_order');
        $this->load->model("account/customer_order_import");
        $country_id = $this->country_id;
        $order_id = $this->request->get['id'];
        // document
        $this->document->setTitle($this->language->get('text_sales_order_detail'));
        //是否为欧洲
        $isEurope = false;
        if ($this->country->isEuropeCountry($this->customer_id)) {
            $isEurope = true;
        }
        $res = $this->sales_model->getCustomerOrderAllInformation($order_id,$this->tracking_privilege);
        $data['service_type'] = SERVICE_TYPE;
        $data['isCollectionFromDomicile'] = $this->customer->isCollectionFromDomicile();
        $data['isEurope'] = $isEurope;
        $data['base_info'] = $res['base_info'];
        $data['item_list'] = $res['item_list'];
        $data['shipping_information'] = $res['shipping_information'];
        $data['signature_list'] = $res['signature_list'];
        $data['sub_total'] = $res['sub_total'];
        //$data['fee_total'] =$res['fee_total'];
        $data['all_total'] = $res['all_total'];
        if ($res['item_total_price']) {
            $data['item_total_price'] = $this->currency->formatCurrencyPrice($res['item_total_price'], $this->session->get('currency'));
        } else {
            $data['item_total_price'] = null;
        }
        $data['settle_flag'] = $res['settle_flag'];
        $data['over_specification_flag'] = $res['over_specification_flag'];
        $data['shipping_address'] = implode(',', array_filter([$res['base_info']['ship_address1'], $res['base_info']['ship_city'], $res['base_info']['ship_state'], $res['base_info']['ship_zip_code'], $res['base_info']['ship_country']]));
        $data['country_id'] = $country_id;
        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => url()->to(['common/home']),
                'separator' => false
            ],
            [
                'text' => $this->language->get('Seller Central'),
                'href' =>url()->to(['customerpartner/seller_center/index']),
                'separator' => $this->language->get('text_separator')
            ],
            [
                'text' => $this->language->get('text_sales_order_management'),
                'href' => url()->to(['account/customerpartner/sales_order_management']),
                'separator' => $this->language->get('text_separator')
            ],
            [
                'text' => $this->language->get('text_sales_order_list'),
                'href' => url()->to(['account/customerpartner/sales_order_list']),
                'separator' => $this->language->get('text_separator')
            ],
            [
                'text' => $this->language->get('text_sales_order_detail'),
                'href' => url()->to(['account/customerpartner/sales_order_list/salesOrderDetails', 'id' => $order_id]),
                'separator' => $this->language->get('text_separator')
            ]
        ];
        if (
            configDB('marketplace_separate_view')
            && isset($this->session->data['marketplace_separate_view'])
            && $this->session->data['marketplace_separate_view'] == 'separate'
        ) {
            $data['separate_view'] = true;
            $data['column_left'] = '';
            $data['column_right'] = '';
            $data['content_top'] = '';
            $data['content_bottom'] = '';
            $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');
            $data['footer'] = $this->load->controller('account/customerpartner/footer');
            $data['header'] = $this->load->controller('account/customerpartner/header');
        } else {
            $data['column_left'] = $this->load->controller('common/column_left');
            $data['column_right'] = $this->load->controller('common/column_right');
            $data['content_top'] = $this->load->controller('common/content_top');
            $data['content_bottom'] = $this->load->controller('common/content_bottom');
            $data['footer'] = $this->load->controller('common/footer');
            $data['header'] = $this->load->controller('common/header');
            $data['separate_view'] = false;
            $data['separate_column_left'] = '';
        }
        $data['href_go_back'] = url()->to(['account/customerpartner/sales_order_list']);
        $data['order_id'] = $order_id;
        $this->response->setOutput($this->load->view('account/customerpartner/sales_order_details', $data));
    }

    // 下载csv文件
    public function downloadCsv()
    {
        $sales_model = new SalesOrderManagement($this->registry);
        $this->load->model('account/customer_order_import');
        $paramTotal = $this->request->request;
        $paramTotal['tracking_privilege'] = $this->tracking_privilege;
        $orders = $sales_model->getSalesOrderList($this->customer->getId(), $paramTotal);
        $content = [];
        $head = [
            'Sales Order ID', 'Item Code', 'Quantity', 'Sub-item Code', 'Sub-item Quantity',
            'Carrier Name', 'Tracking Number', 'Ship Date', 'ShipToService'
        ];
        foreach ($orders as $order) {
            $results = $this->model_account_customer_order_import->getTrackingNumberInfoByOrderParam([$order['id']]);
            foreach ($results as $result) {
                if(isset($this->request->get['filter_tracking_number']) && $this->request->get['filter_tracking_number'] != 0){
                    if(($this->tracking_privilege && $order['order_status'] == CustomerSalesOrderStatus::COMPLETED) || !$this->tracking_privilege){
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
                        $ShipDeliveryDate = '';
                    }

                }else{
                    $carrier_name = '';
                    $tracking_number = '';
                    $ShipDeliveryDate = '';
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
                    strcasecmp(trim($result['ship_method']), 'ASR') == 0 ? 'ASR' : ''
                ];
            }
        }
        // 输出
        $fileName = "SalesOrderManagement" . date('YmdHis') . ".csv";
        outputCsv($fileName, $head, $content, $this->session);
    }

    // 获取销售订单是否包含超大件 或 combo商品
    private function getSalesOrderProductTag(int $sales_order_id)
    {
        $this->load->model('catalog/product');
        $tags = [];
        $this->orm
            ->table('tb_sys_customer_sales_order_line')
            ->where('header_id', $sales_order_id)
            ->whereNotNull('product_id')
            ->groupBy(['product_id'])
            ->pluck('product_id')
            ->each(function ($id) use (&$tags) {
                $tags = array_unique(array_merge($tags, $this->model_catalog_product->getProductTagHtmlForThumb($id)));
            });
        return $tags;
    }


    const SHIP_TO_NAME = 'ShipToName';
    const SHIP_TO_EMAIL = 'ShipToEmail';
    const SHIP_TO_PHONE = 'ShipToPhone';
    const STREET_ADDRESS = 'StreetAddress';
    const SHIP_TO_POSTAL_CODE = 'ShipToPostalCode';
    const SHIP_TO_CITY = 'ShipToCity';
    const SHIP_TO_STATE = 'ShipToState';
    const SHIP_TO_COUNTRY = 'ShipToCountry';
    const ORDER_COMMENTS = 'OrderComments';
    /**
     * 修改订单发货信息
     * @throws
     */
    public function changeOrderShipping()
    {
        //N-130 欧洲+日本New Order状态下增加修改地址和修改ItemCode功能 new order 订单仅仅是自己更改，无其他 ，ok。
        $omd_order_sku_uuid = "c9cedfd2-a209-4ece-be77-fb3915bdca0c";
        $this->load->model("account/customer_order");
        $this->load->language('account/customer_order_import');
        $data = $this->request->input->all();
        trim_strings($data);
        $header_id = request('id');
        // 只有美国才会有LTL订单
        $isLTL = ($this->country_id == AMERICAN_COUNTRY_ID) ? app(CustomerSalesOrderRepository::class)->isLTL($this->country_id,
            app(CustomerSalesOrderRepository::class)->getItemCodesByHeaderId(intval($header_id))) : false;
        $len = 0;
        if (!$isLTL) {
            if ($this->country_id == AMERICAN_COUNTRY_ID) {
                $len = configDB('config_b2b_address_len_us1');
            } else if ($this->country_id == UK_COUNTRY_ID) {
                $len = configDB('config_b2b_address_len_uk');
            } else if ($this->country_id == DE_COUNTRY_ID) {
                $len = configDB('config_b2b_address_len_de');
            } else if ($this->country_id == JAPAN_COUNTRY_ID) {
                $len = configDB('config_b2b_address_len_jp');
            }
        } else {
            $len = configDB('config_b2b_address_len');
        }
        $json = array();
        if (isset($header_id) && isset($data)) {
            $email_reg = "/[\w\-.]+@([\w\-]+\.)+[a-z]{2,3}/";
            //数据校验
            if (!isset($data['name']) || empty($data['name']) || strlen($data['name']) > 40) {
                $json['error'] = $this->language->get('error_ship_label_name');
                $json['error_id'] = static::SHIP_TO_NAME;
            } elseif (!isset($data['email']) || empty($data['email']) || strlen($data['email']) > 90) {
                $json['error'] = $this->language->get('error_ship_label_email');
                $json['error_id'] = static::SHIP_TO_EMAIL;
            } elseif (!preg_match($email_reg, $data['email'])) {
                $json['error'] = $this->language->get('error_ship_label_email_reg');
                $json['error_id'] = static::SHIP_TO_EMAIL;
            } elseif (!isset($data['phone']) || empty($data['phone']) || strlen($data['phone']) > 45) {
                $json['error'] = $this->language->get('error_ship_label_phone');
            } elseif (!isset($data['address']) || empty($data['address']) || StringHelper::stringCharactersLen($data['address']) > $len) {
                $json['error'] = sprintf($this->language->get('error_ship_label_address_1'), intval($len));
                $json['error_id'] = static::STREET_ADDRESS;
            } elseif (!isset($data['city']) || empty($data['city']) || strlen($data['city']) > 30) {
                $json['error'] = $this->language->get('error_ship_label_city');
                $json['error_id'] = static::SHIP_TO_CITY;
            } elseif (!isset($data['state']) || empty($data['state']) || $data['state'] == '0') {
                $json['error'] = $this->language->get('error_ship_label_state');
                $json['error_id'] = static::SHIP_TO_STATE;
            } elseif (strlen($data['state']) > 30) {
                $json['error'] = $this->language->get('error_ship_label_state_length');
                $json['error_id'] = static::SHIP_TO_STATE;
            } elseif (!isset($data['code']) || empty($data['code']) || strlen($data['code']) > 18) {
                $json['error'] = $this->language->get('error_ship_label_code');
                $json['error_id'] = static::SHIP_TO_POSTAL_CODE;
            } elseif (!isset($data['country']) || empty($data['country']) || $data['country'] == '0') {
                $json['error'] = $this->language->get('error_ship_label_country');
                $json['error_id'] = static::SHIP_TO_COUNTRY;
            } elseif (strlen($data['comments']) > 1500) {
                $json['error'] = $this->language->get('error_ship_label_comments');
                $json['error_id'] = static::ORDER_COMMENTS;
            }


            if (!$isLTL && ($this->country_id == AMERICAN_COUNTRY_ID)) {
                if (AddressHelper::isPoBox($data['address'] ?? '')) {
                    $json['error'] = 'ShipToAddressDetail in P.O.BOX doesn\'t support delivery,Please see the instructions.';
                    $json['error_id'] = static::STREET_ADDRESS;
                }

                if (AddressHelper::isRemoteRegion($data['state'] ?? '')) {
                    $json['error'] = 'ShipToState in PR, AK, HI, GU, AA, AE, AP doesn\'t support delivery,Please see the instructions';
                    $json['error_id'] = static::SHIP_TO_STATE;
                }
            }

            // 由于修改地址的原因 需要校验修改的地址是否合法
            // 关于国际单校验 需要讨论两种情况
            // 1.当前国别允许国际单 但是当前的国际单非法 2.当前国别不允许国际单 但是校验为国际单
            $salesOrder = CustomerSalesOrder::find($header_id);
            $salesService = app(SalesOrderService::class);
            // 是否允许国际单
            if (
            !$salesService->checkSalesOrderCanEditAddress(
                $salesOrder->buyer->country_id, $data['country'], $data['code']
            )
            ) {
                $json['error'] = 'Invalid Shipping Country:' . $data['country'];
                $json['error_id'] = static::SHIP_TO_COUNTRY;
            }
            if (isset($json['error'])) goto END;
            date_default_timezone_set('America/Los_Angeles');
            $is_syncing = $this->model_account_customer_order->checkOrderIsSyncing($header_id);
            $europeExported = CustomerSalesOrderLine::query()
                ->whereNotNull('is_synchroed')
                ->where('header_id', $header_id)
                ->exists();
            if ($is_syncing || $europeExported) {
                $json['error'] = $this->language->get('error_is_syncing');
            }
            if (!isset($json['error'])) {
                $param = array();
                $post_data = array();
                $record_data = array();
                $omd_store_id = $this->model_account_customer_order->getOmdStoreId($header_id);
                $current_order_info = $this->model_account_customer_order->getCurrentOrderInfoByHeaderId($header_id);
                if (!empty($current_order_info) && count($current_order_info) == 1) {  // && !empty($omd_store_id)
                    $order_info = current($current_order_info);
                    $process_code = CommonOrderProcessCode::CHANGE_ADDRESS;  //操作码 1:修改发货信息,2:修改SKU,3:取消订单
                    $status = CommonOrderActionStatus::PENDING;        //操作状态 1:操作中,2:成功,3:失败
                    $run_id = time();
                    $header_id = $order_info['header_id'];
                    $order_id = $order_info['order_id'];
                    $order_type = 1;
                    $create_time = date("Y-m-d H:i:s");
                    $before_record = "Order_Id:" . $order_id . " ShipToName:" . app('db-aes')->decrypt($order_info['ship_name'])
                        . " ShipToEmail:" . $order_info['email'] . " ShipToPhone:" . app('db-aes')->decrypt($order_info['ship_phone']) . " ShipToAddressDetail:" . app('db-aes')->decrypt($order_info['ship_address1'])
                        . " ShipToCity:" . app('db-aes')->decrypt($order_info['ship_city']) . " ShipToState:" . $order_info['ship_state'] . " ShipToPostalCode:" . $order_info['ship_zip_code']
                        . " ShipToCountry:" . $order_info['ship_country'] . " OrderComments:" . $order_info['customer_comments'];
                    $modified_record = "Order_Id:" . $order_id . " ShipToName:" . $data['name']
                        . " ShipToEmail:" . $data['email'] . " ShipToPhone:" . $data['phone'] . " ShipToAddressDetail:" . $data['address']
                        . " ShipToCity:" . $data['city'] . " ShipToState:" . $data['state'] . " ShipToPostalCode:" . $data['code']
                        . " ShipToCountry:" . $data['country'] . " OrderComments:" . $data['comments'];;

                    //omd提交数据组织
                    $post_data['uuid'] = $omd_order_sku_uuid;
                    $post_data['runId'] = $run_id;
                    $post_data['orderId'] = $order_id;
                    $post_data['storeId'] = $omd_store_id;
                    $post_data['shipData'] = $data;

                    $param['apiKey'] = OMD_POST_API_KEY;
                    $param['postValue'] = json_encode($post_data);

                    //日志数据组织
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

                    //区分执行omd和onsite，互斥的  这个地方是卖家的操作，卖家属于哪个分组已确定，不需要判断来源于多个
                    if (customer()->getAccountType() == CustomerAccountingType::GIGA_ONSIDE) {  //走onsite流程
                        $isInOnsite = $this->model_account_customer_order->checkOrderShouldInGigaOnsite($header_id);
                        if ($isInOnsite) {
                            //保存修改记录
                            $logId = $this->model_account_customer_order->saveSalesOrderModifyRecord($record_data);
                            $gigaResult = app(GigaOnsiteHelper::class)->updateOrderAddress($order_id, $data,$run_id);
                            if ($gigaResult['code'] == 1) {
                                $json['success'] = $this->language->get('text_cancel_seller_wait');
                            } else {
                                $newStatus = CommonOrderActionStatus::FAILED;
                                $failReason = $this->language->get('text_cancel_failed');
                                $this->model_account_customer_order->updateSalesOrderModifyLog($logId, $newStatus, $failReason);
                                $json['error'] = $this->language->get('text_cancel_failed');
                            }
                        } else {
                            //未同步到onsite的订单直接修改地址信息
                            $change_result = $this->model_account_customer_order->changeSalesOrderShippingInformation($header_id, $data);
                            if ($change_result) {
                                $record_data['status'] = CommonOrderActionStatus::SUCCESS;
                                //保存修改记录
                                $this->model_account_customer_order->saveSalesOrderModifyRecord($record_data);
                                $json['success'] = $this->language->get('text_change_ship_success');
                            } else {
                                $record_data['status'] = CommonOrderActionStatus::FAILED;
                                //保存修改记录
                                $this->model_account_customer_order->saveSalesOrderModifyRecord($record_data);
                                $json['error'] = $this->language->get('text_change_ship_failed');
                            }
                        }
                    } else {
                        if (!empty($omd_store_id)) {
                            $is_in_omd = $this->model_account_customer_order->checkOrderShouldInOmd($header_id);
                            if ($is_in_omd) {
                                //保存修改记录
                                $log_id = $this->model_account_customer_order->saveSalesOrderModifyRecord($record_data);
                                $response = $this->sendCurl(OMD_POST_URL, $param);
                                if (isset($response) && 'OMD PROCESS SUCCESS' == $response) {
                                    $json['success'] = $this->language->get('text_cancel_seller_wait');
                                } else {
                                    $new_status = CommonOrderActionStatus::FAILED;
                                    $fail_reason = $this->language->get('text_cancel_failed');
                                    $this->model_account_customer_order->updateSalesOrderModifyLog($log_id, $new_status, $fail_reason);
                                    $json['error'] = $this->language->get('text_cancel_failed');
                                }
                            } else {
                                //未同步到OMD的订单直接修改SKU
                                $change_result = $this->model_account_customer_order->changeSalesOrderShippingInformation($header_id, $data);
                                if ($change_result) {
                                    $record_data['status'] = CommonOrderActionStatus::SUCCESS;
                                    //保存修改记录
                                    $this->model_account_customer_order->saveSalesOrderModifyRecord($record_data);
                                    $json['success'] = $this->language->get('text_change_ship_success');
                                } else {
                                    $record_data['status'] = CommonOrderActionStatus::FAILED;
                                    //保存修改记录
                                    $this->model_account_customer_order->saveSalesOrderModifyRecord($record_data);
                                    $json['error'] = $this->language->get('text_change_ship_failed');
                                }
                            }
                        } else {
                            $json['error'] = $this->language->get('error_invalid_param');
                        }
                    }

                } else {
                    $json['error'] = $this->language->get('error_invalid_param');
                }
            }
        } else {
            $json['error'] = $this->language->get('error_invalid_param');
        }
        END:
        if (isset($json['error']) && $json['error']) {
            // 携带error id 实现编辑框下面显示错误信息
            if (isset($json['error_id'])) {
                $ret = ['error' => 1, 'info' => [['id' => $json['error_id'], 'info' => $json['error']]]];
            } else {
                $ret = ['error' => 2, 'info' => $json['error']];
            }
        } else {
            // 修改地址后release order 如果release order失败，暂时不会做任何处理
            $order_info = $this->sales_model->getReleaseOrderInfo($header_id);
            $this->sales_model->releaseOrder($header_id, $order_info['order_status'], $order_info['type']);
            $ret = ['error' => 0, 'info' => $json['success']];
        }
        return $this->response->json($ret);
    }

    /**
     * 获取表格形式的修改错误日志输出
     * @param $process_code
     * @param int $id
     * @param string $line_id
     * @return string
     * @throws Exception
     */
    private function getOrderModifyFailureLog($process_code, $id, $line_id = null)
    {
        $this->load->model("account/customer_order");
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
                $failure_log_html = "<table class=\"table table-hover\" style=\"text-align: left\"><tbody>";
                $failure_log_html .= "<tr><th class=\"text-left\">" . $this->language->get('text_table_head_time') . "</th><td class=\"text-left\">" . preg_replace("/\s/", " ", $log_detail['operation_time']) . "</td></tr>";
                $failure_log_html .= "<tr><th class=\"text-left\">" . $this->language->get('text_table_head_type') . "</th><td class=\"text-left\">" . preg_replace("/\s/", " ", $log_detail['process_code']) . "</td></tr>";
                $failure_log_html .= "<tr><th class=\"text-left\">" . $this->language->get('text_table_head_before') . "</th><td class=\"text-left\">" . preg_replace("/\s/", " ", $log_detail['previous_status']) . "</td></tr>";
                $failure_log_html .= "<tr><th class=\"text-left\">" . $this->language->get('text_table_head_target') . "</th><td class=\"text-left\">" . preg_replace("/\s/", " ", $log_detail['target_status']) . "</td></tr>";
                $failure_log_html .= "<tr><th class=\"text-left\">" . $this->language->get('text_table_head_reason') . "</th><td class=\"text-left\">" . preg_replace("/\s/", " ", $log_detail['fail_reason']) . "</td></tr>";
                $failure_log_html .= "</tbody></table>";
            }
        }
        return htmlentities($failure_log_html);
    }

    public function getCountryState()
    {
        $this->load->model('localisation/zone');
        $country_id = request('country_id');
        $arr = [
            'US' => 223,
            'GB' => 222,
            'DE' => 81,
            'JP' => 107,
        ];
        if (isset($arr[$country_id])) {
            if ($arr[$country_id] == 107) {
                $json = $this->orm->table('tb_sys_country_state')->where(['country_id' => 107])->selectRaw('county as name,county as code')->get()->toArray();
            } else {
                $json = $this->model_localisation_zone->getZonesByCountryId($arr[$country_id]);
            }
        } else {
            $json = $this->model_localisation_zone->getZonesByCountryId(null);
        }

        return $this->response->json($json);
    }

    /**
     * 发送HTTP请求
     * @param $url
     * @param $post_data
     * @return mixed
     */
    private function sendCurl($url, $post_data)
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
    // endregion
}
