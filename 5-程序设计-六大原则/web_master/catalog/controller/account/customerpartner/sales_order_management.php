<?php

use App\Components\Storage\StorageCloud;
use App\Components\Storage\StorageLocal;
use App\Enums\Common\YesNoEnum;
use App\Enums\Country\Country;
use App\Enums\Product\ProductType;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Logging\Logger;
use App\Models\Customer\CustomerExts;
use App\Models\Product\Product;
use Catalog\model\customerpartner\SalesOrderManagement as sales_model;

/**
 * @property ModelExtensionModuleShipmentTime $model_extension_module_shipment_time
 * @property ModelToolExcel $model_tool_excel
 */
class ControllerAccountCustomerpartnerSalesOrderManagement extends Controller
{

    private $customer_id;
    private $country_id;
    private $isPartner;
    private $sales_model;

    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->customer_id = $this->customer->getId();
        $this->isPartner = $this->customer->isPartner();
        $this->country_id = $this->customer->getCountryId();
        $this->load->language('account/customerpartner/sales_order_management');
        $this->sales_model = new sales_model($registry);
        if (empty($this->customer_id) || !$this->isPartner) {
            $this->response->redirect($this->url->link('account/login'));
        }

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
            $this->response->redirect($this->url->link('customerpartner/seller_center/index'));
        }

    }

    /**
     * [index description] 全部的页面
     */
    public function index()
    {
        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_sales_order_management'),
                'href' => url()->to(['account/customerpartner/sales_order_management']),
                'separator' => $this->language->get('text_separator')
            ],
            [
                'text' => $this->language->get('text_external_platform_mapping'),
                'href' => url()->to(['account/customerpartner/sales_order_management']),
                'separator' => $this->language->get('text_separator')
            ]
        ];
        $this->document->setTitle($this->language->get('text_sales_order_upload'));
        $data['help_info'] = url()->to(['account/customerpartner/sales_order_management/shippingInformationGuide']);
        $data['upload_url'] = url()->to(['account/customerpartner/sales_order_management']);
        $data['sales_order_url'] = url()->to(['account/customerpartner/sales_order_list']);
        $data['separate_view'] = true;
        $data['column_left'] = '';
        $data['column_right'] = '';
        $data['content_top'] = '';
        $data['content_bottom'] = '';
        $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');
        $data['footer'] = $this->load->controller('account/customerpartner/footer');
        $data['header'] = $this->load->controller('account/customerpartner/header');

        return $this->render('account/customerpartner/sales_order_upload', $data);

    }

    /**
     * [uploadInfo description] 导单页面
     */
    public function salesOrderUploadInfo()
    {
        // 基本用户订单导入模式
        $data['downloadTemplateHref'] = "index.php?route=account/customerpartner/sales_order_management/downloadTemplateFile";
        $data['downloadInterpretationHref'] = "index.php?route=account/customerpartner/sales_order_management/downloadTemplateInterpretationFile";
        $data['upload_history_records'] = str_replace('&amp;', '&', $this->url->link('account/customerpartner/sales_order_management/uploadHistoryRecords/'));
        //获取上传历史数据
        $data['historys'] = $this->sales_model->getUploadHistory($this->customer_id);
        $data['sales_order_url'] = url()->to(['account/customerpartner/sales_order_list']);
        $data['isEurope'] = $this->customer->isEurope();
        return $this->render('account/customerpartner/sales_order_upload_info', $data);
    }

    /**
     * [uploadHistoryRecords description] 文件上传历史数据
     */
    public function uploadHistoryRecords()
    {

        $url = '';
        $param = [];
        $this->document->setTitle('Upload History');
        trim_strings($this->request->get['filter_orderDate_from']);
        if (isset($this->request->get['filter_orderDate_from']) && $this->request->get['filter_orderDate_from'] != '') {
            $data['filter_orderDate_from'] = $this->request->get['filter_orderDate_from'];
            $param['filter_orderDate_from'] = $this->request->get['filter_orderDate_from'];
            $url .= '&filter_orderDate_from=' . $this->request->get['filter_orderDate_from'];
        }
        if (isset($this->request->get['filter_orderDate_to']) && $this->request->get['filter_orderDate_to'] != '') {
            $data['filter_orderDate_to'] = $this->request->get['filter_orderDate_to'];
            $param['filter_orderDate_to'] = $this->request->get['filter_orderDate_to'];
            $url .= '&filter_orderDate_to=' . $this->request->get['filter_orderDate_to'];
        }
        $page = $this->request->get['page'] ?? 1;
        $perPage = $this->request->get['page_limit'] ?? 100;
        $total = $this->sales_model->getSuccessfullyUploadHistoryTotal($param, $this->customer_id);
        $result = $this->sales_model->getSuccessfullyUploadHistory($param, $this->customer_id, $page, $perPage);

        $pagination = new Pagination();
        $pagination->total = $total;
        $pagination->page = $page;
        $pagination->limit = $perPage;
        $data['submit_url'] = url()->to(['account/customerpartner/sales_order_management/uploadHistoryRecords/']);

        $pagination->url = $this->url->link('account/customerpartner/sales_order_management/uploadHistoryRecords/' . $url, '&page={page}', true);
        $data['pagination'] = $pagination->render();
        $data['results'] = sprintf($this->language->get('text_pagination'), ($total) ? (($page - 1) * $perPage) + 1 : 0, ((($page - 1) * $perPage) > ($total - $perPage)) ? $total : ((($page - 1) * $perPage) + $perPage), $total, ceil($total / $perPage));
        $data['historys'] = $result;
        $data['page'] = $page;
        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_sales_order_management'),
                'href' => url()->to(['account/customerpartner/sales_order_management']),
                'separator' => $this->language->get('text_separator')
            ],
            [
                'text' => $this->language->get('text_sales_order_upload'),
                'href' => url()->to(['account/customerpartner/sales_order_management/uploadHistoryRecords']),
                'separator' => $this->language->get('text_separator')
            ]
        ];
        $data['separate_view'] = true;
        $data['column_left'] = '';
        $data['column_right'] = '';
        $data['content_top'] = '';
        $data['content_bottom'] = '';
        $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');
        $data['footer'] = $this->load->controller('account/customerpartner/footer');
        $data['header'] = $this->load->controller('account/customerpartner/header');
        $data['app_version'] = APP_VERSION;
        return $this->render('account/customerpartner/sales_order_upload_history_records', $data);

    }

    public function shippingInformationGuide()
    {
        $this->document->setTitle('Shipping Information');
        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_sales_order_management'),
                'href' => url()->to(['account/customerpartner/sales_order_management']),
                'separator' => $this->language->get('text_separator')
            ],
            [
                'text' => $this->language->get('text_sales_order_upload'),
                'href' => url()->to(['account/customerpartner/sales_order_management/uploadHistoryRecords']),
                'separator' => $this->language->get('text_separator')
            ]
        ];
        $data['separate_view'] = true;
        $data['column_left'] = '';
        $data['column_right'] = '';
        $data['content_top'] = '';
        $data['content_bottom'] = '';
        $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');
        $data['footer'] = $this->load->controller('account/customerpartner/footer');
        $data['header'] = $this->load->controller('account/customerpartner/header');

        $this->load->model('extension/module/shipment_time');
        $shipmentTimePage = $this->model_extension_module_shipment_time->getShipmentTime($this->country_id);
        $data['url_back'] = request()->serverBag->get('HTTP_REFERER');
        $data['description'] = html_entity_decode($shipmentTimePage->page_description, ENT_QUOTES, 'UTF-8');
        $data['app_version'] = APP_VERSION;
        return $this->render('account/customerpartner/sales_order_shipping_infomation_guide', $data);

    }

    /**
     * [downloadTemplateFile description]
     */
    public function downloadTemplateFile()
    {
        if (Customer()->isEurope()) {
            return StorageLocal::root()->browserDownload('download/OrderTemplateEURSeller.xls');
        }
        return StorageLocal::root()->browserDownload('download/OrderTemplate.xls');

    }

    public function downloadTemplateInterpretationFile()
    {
        $this->document->setTitle('OrderTemplateInterpretation');
        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_sales_order_management'),
                'href' => url()->to(['account/customerpartner/sales_order_management']),
                'separator' => $this->language->get('text_separator')
            ],
            [
                'text' => $this->language->get('text_sales_order_upload'),
                'href' => url()->to(['account/customerpartner/sales_order_management/uploadHistoryRecords']),
                'separator' => $this->language->get('text_separator')
            ]
        ];
        $data['separate_view'] = true;
        $data['column_left'] = '';
        $data['column_right'] = '';
        $data['content_top'] = '';
        $data['content_bottom'] = '';
        $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');
        $data['footer'] = $this->load->controller('account/customerpartner/footer');
        $data['header'] = $this->load->controller('account/customerpartner/header');
        $data['country_id'] = $this->country_id;
        $data['country_us_id'] = AMERICAN_COUNTRY_ID;
        $data['country_jp_id'] = JAPAN_COUNTRY_ID;
        $data['url_back'] = request()->serverBag->get('HTTP_REFERER');
        $data['isEurope'] = Customer()->isEurope();
        $data['app_version'] = APP_VERSION;
        if($data['isEurope']){
            return $this->render('account/customerpartner/sales_order_europe_instruction', $data);
        }
        return $this->render('account/customerpartner/sales_order_instruction', $data);
    }

    public function uploadFile()
    {
        $json = [];
        $validator = Request()->validate([
            'upload_type' => 'required',
            'file' => 'required',
        ]);
        if ($validator->fails()) {
            $json['error'] = $validator->errors()->first();
        }
        //验证文件
        $upload_type = Request('upload_type');
        /** @var Symfony\Component\HttpFoundation\File\UploadedFile $fileInfo */
        $fileInfo = Request()->file('file');
        if( $fileInfo->isValid()){
            $fileType = $fileInfo->getClientOriginalExtension();
            if(!in_array(strtolower($fileType),['xls', 'xlsx'])){
                $json['error'] = __('错误的文件格式！', [], 'common/upload');
            }
        }else{
            $json['error'] = __('文件不能上传！', [], 'common/upload');
        }

        if ($json) {
            return $this->response->json($json);
        }
        //记录上传文件数据
        $save_info = $this->sales_model->saveUploadFile($fileInfo, $this->customer_id, $upload_type);
        $json['text'] = $this->language->get('text_upload');
        $json['runId'] = $save_info['run_id'];
        $json['next'] = url()->to(['account/customerpartner/sales_order_management/saveOrder',
            'run_id' => $save_info['run_id'],
            'import_mode' => $save_info['import_mode'],
            'file_id' => $save_info['file_id']
        ]);
        return $this->response->json($json);
    }

    /**
     * [saveOrder description] 导单核心数据处理
     */
    public function saveOrder()
    {
        trim_strings($this->request->get);
        load()->model('tool/excel');
        $get = $this->request->get;
        $file_info = $this->sales_model->getUploadFileInfo($get);
        //使用时需要使用临时文件夹中的地址，使用完成之后删除文件
        $file_path = StorageCloud::orderCsv()->getLocalTempPath($file_info['file_path']);
        $ret = false;
        try {
            $excel_data = $this->model_tool_excel->getExcelData($file_path);
            $ret = $this->sales_model->dealWithFileData($excel_data, $get, $this->country_id, $this->customer_id);
        } catch (\Exception $e) {
            Logger::salesOrder($e);
        }
        if ($ret !== true) {
            $update_info = [
                'handle_status' => 0,
                'handle_message' => 'upload failed, ' . $ret,
            ];
            $this->sales_model->updateUploadInfoStatus($get['run_id'], $this->customer_id, $update_info);
            $json['error'] = $ret;
        } else {
            $update_info = [
                'handle_status' => 1,
                'handle_message' => 'uploaded successfully.',
            ];
            $this->sales_model->updateUploadInfoStatus($get['run_id'], $this->customer_id, $update_info);
            $json['text'] = 'Orders Processing';
            $json['next'] = url()->to(['account/customerpartner/sales_order_management/orderPurchase', 'runId' => $get['run_id'], 'importMode' => $get['import_mode']]);
        }
        StorageCloud::orderCsv()->deleteLocalTempFile($file_path);
        return $this->response->json($json);
    }

    public function cityComparison()
    {
        $this->document->setTitle($this->language->get('text_european_country_code_chart'));
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
        switch (Customer()->getCountryId()) {
            case Country::GERMANY://德国
                $data['list'] = $listGermany;
                break;
            case Country::BRITAIN://英国
                $data['list'] = $listEngland;
                break;
            default:
                break;
        }

        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => url()->to(['common/home']),
                'separator' => false
            ],
            [
                'text' => $this->language->get('Seller Center'),
                'href' => 'javascript:void(0);',
                'separator' => $this->language->get('text_separator')
            ],
            [
                'text' => $this->language->get('text_sales_order_management'),
                'href' => url()->to(['account/customerpartner/sales_order_management']),
                'separator' => $this->language->get('text_separator')
            ],
            [
                'text' => $this->language->get('text_sales_order_city_comparison'),
                'href' => url()->to(['account/customerpartner/sales_order_management/cityComparison']),
                'separator' => $this->language->get('text_separator')
            ]
        ];

        $data['separate_view'] = true;
        $data['url_back'] = request()->serverBag->get('HTTP_REFERER');
        $data['isEurope'] = Customer()->isEurope();
        $data['app_version'] = APP_VERSION;
        return $this->render('account/customerpartner/city_comparison', $data, [
            'separate_column_left' => 'account/customerpartner/column_left',
            'footer' => 'account/customerpartner/footer',
            'header' => 'account/customerpartner/header',
        ]);
    }

    public function orderPurchase()
    {
        $runId = Request('runId');
        $importMode = Request('importMode');
        $this->sales_model->updateOriginalSalesOrderStatus($runId, $this->customer_id);
        $json['success'] = 'Process success!';
        $json['final'] = url()->to(['account/customerpartner/sales_order_management/orderConfirm', 'runId' => $runId, 'importMode' => $importMode]);
        return $this->response->json($json);
    }

    public function orderConfirm()
    {
        $runId = Request('runId');
        $orderColumn = Cache()->get($this->customer_id . '_' . $runId . '_column_exception');
        $exceptionList = [];
        if ($orderColumn) {
            foreach ($orderColumn as $key => $items) {
                $status = $this->sales_model->getCommonOrderStatus($key, $runId);
                foreach ($items as $ks => $vs) {
                    $exceptionList[] = [
                        'sales_order_id' => $key,
                        'field' => $ks,
                        'content' => $vs['position'],
                        'order_status' => $status,
                        'order_status_value' => CustomerSalesOrderStatus::getDescription($status),
                    ];
                }

            }
        }

        //根据run_id 和 buyer_id 区分是库存问题还是订单问题
        $originalList = $this->sales_model->getOriginalSalesOrder(
            $runId,
            Customer()->getId(),
            [
                CustomerSalesOrderStatus::ON_HOLD,
                CustomerSalesOrderStatus::LTL_CHECK,
                CustomerSalesOrderStatus::ABNORMAL_ORDER
            ]
        );
        $data['sku_list'] = [];
        $data['stock_list'] = [];
        $data['ltl_list'] = [];
        $data['on_hold_list'] = [];
        $data['internationalList'] = [];
        $data['internationalRuleInvalid'] = [];
        if ($originalList) {
            $unDoList = $this->sales_model->getUndoOrder($originalList);
            $data['sku_list'] = $unDoList['skuList'];
            $data['stock_list'] = $unDoList['stockList'];
            $data['ltl_list'] = $unDoList['ltlList'];
            $data['on_hold_list'] = $unDoList['onHoldList'];
            $originalList = $unDoList['allList'];
            $data['internationalList'] = $unDoList['internationalList'];
            $data['internationalRuleInvalid'] = $unDoList['internationalRuleInvalid'];
        }
        $data['all_list'] = array_merge($this->sales_model->getDoOrder($this->sales_model->getOriginalSalesOrder($runId, $this->customer_id, [CustomerSalesOrderStatus::BEING_PROCESSED]))['stock_list'], $originalList);
        $data['exception_list'] = $exceptionList;
        $data['salesOrderIsLTLCheck'] = $this->sales_model->verifySalesOrderIsLTLCheck($this->customer_id, $runId);
        $data['runId'] = $runId;
        $data['app_version'] = APP_VERSION;
        Cache()->delete($this->customer_id . '_' . $runId . '_column_exception');
        return $this->render('account/customerpartner/sales_order_confirm', $data);
    }


    public function salesOrderLtlUpdateToBP()
    {
        trim_strings($this->request->post);
        $post = $this->request->post;
        try {
            $this->orm->getConnection()->beginTransaction();
            $json = $this->sales_model->changeLtlStatusToBP($post['id']);
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


    public function getB2bCodeBySearch()
    {
        $sku = Request('q', '');
        $page = Request('page', 1);

        $map = [
            ['p.sku', 'like', "%{$sku}%"],
            ['ctp.customer_id', '=', $this->customer_id],
        ];
        $builder = Product::query()->alias('p')
            ->leftJoinRelations(['customerPartnerToProduct as ctp'])
            ->where($map)
            ->whereIn('p.product_type', [ProductType::NORMAL])
            ->whereNotNull('p.sku')
            ->select(['p.product_id as id', 'p.sku'])
            ->groupBy('p.sku');
        $results['total_count'] = $builder->count();
        $results['items'] = $builder->forPage($page, 10)
            ->orderBy('p.product_id')
            ->get()
            ->toArray();
        return $this->response->json($results);
    }

    public function salesOrderSkuChange()
    {
        $json = [];
        $orderId = Request('order_id');
        $lineId = Request('line_id');
        $productId = Request('product_id');
        // order_id line_id product_id
        $ret = $this->sales_model->updateSalesOrderLineSku($orderId, $lineId, $productId, $this->customer_id);
        if (!$ret) {
            $json['success'] = 0;
            $json['msg'] = 'Operation failed, please try again.';
        } else {
            $json['success'] = 1;
            $json['msg'] = 'Operation succeeded.';
        }
        return $this->response->json($json);
    }

    public function releaseOrder()
    {
        $orderId = Request('id');
        $order_info = $this->sales_model->getReleaseOrderInfo($orderId);
        $releaseRes = $this->sales_model->releaseOrder($orderId, $order_info['order_status'], $order_info['type']);
        $order_info_ret = $this->sales_model->getReleaseOrderInfo($orderId);
        $json = [];
        if ($releaseRes && isset($releaseRes['code']) && $releaseRes['code'] == 104) {
            // 返回状态是104则为风控限制订单release，其余状态需要显示特殊原因自行增加
            $json['msg'] = 'Failed to release order!<br><br>' . $releaseRes['msg'];
            return $this->response->json($json);
        }
        switch ($order_info_ret['order_status']) {
            case CustomerSalesOrderStatus::ABNORMAL_ORDER:
                $json['msg'] = $releaseRes['msg'];
                break;
            case CustomerSalesOrderStatus::BEING_PROCESSED:
            case CustomerSalesOrderStatus::LTL_CHECK:
                $json['msg'] = $this->language->get('text_success_release');
                break;
            default:
                $json['msg'] = $releaseRes['msg'];
        }

        return $this->response->json($json);
    }

    public function onHoldOrder()
    {
        $orderId = Request('id');
        $json = $this->sales_model->onHoldSalesOrder($orderId, $this->country_id);
        return $this->response->json($json);
    }

    public function cancelOrder()
    {

        $orderId = Request('id');
        $json = $this->sales_model->cancelSalesOrder($orderId, $this->country_id);
        return $this->response->json($json);

    }

    public function getSalesOrderLtlOne()
    {

        $id = Request('id');
        $list = $this->sales_model->getLtlCheckInfoByOrderId($id);
        $data['list'] = $list;
        $data['id'] = $id;
        $data['app_version'] = APP_VERSION;
        return $this->render('account/customerpartner/sales_order_check_ltl', $data);
    }

    public function getSalesOrderLtlMore()
    {
        $runId = Request('runId');
        $ltl_list = $this->sales_model->getOriginalSalesOrder($runId, Customer()->getId(), [CustomerSalesOrderStatus::LTL_CHECK]);
        $id_arr = array_column($ltl_list, 'id');
        $data['id'] = implode('_', $id_arr);
        $data['list'] = $this->sales_model->getLtlCheckInfoByOrderId($data['id']);
        $data['from_page'] = 'upload';
        $data['app_version'] = APP_VERSION;
        return $this->render('account/customerpartner/sales_order_check_ltl', $data);
    }
}
