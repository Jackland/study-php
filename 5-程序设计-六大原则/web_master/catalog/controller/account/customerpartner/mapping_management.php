<?php

use App\Enums\Common\YesNoEnum;
use App\Models\Customer\CustomerExts;
use Catalog\model\customerpartner\SalesOrderManagement;

/**
 * Class ControllerAccountCustomerpartnerSalesOrderList
 *
 * @property ModelAccountCustomerOrder $model_account_customer_order
 * @property ModelAccountCustomerOrderImport $model_account_customer_order_import
 * @property ModelAccountCustomerpartnerMappingManagement $model_account_customerpartner_mapping_management
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelToolExcel $model_tool_excel
 */
class ControllerAccountCustomerpartnerMappingManagement extends Controller
{

    private $customer_id = null;
    private $country_id = null;
    private $isPartner = false;
    /**
     * @var ModelAccountCustomerpartnerMappingManagement $model
     */
    private $model;

    //控制器权限控制
    private function check_privilege()
    {
        //除了USER ATTRIBUTE为Inner Accounting的Seller之外，其他的seller都有权限。
//        if ($this->customer->isInnerAccount()) {
//            $this->response->redirect($this->url->link('common/home'));
//        }
        if (empty($this->customer_id) || !$this->isPartner) {
            $this->response->redirect($this->url->link('account/login', '', true));
        }

        $notSupportSelfDeliveryExists = CustomerExts::query()->where([
            'customer_id'=> customer()->getId(),
            'not_support_self_delivery'=> YesNoEnum::YES,
        ])->exists();
        if($notSupportSelfDeliveryExists){
            $this->response->redirect(url()->to('customerpartner/seller_center/index'));
        }
    }

    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->customer_id = $this->customer->getId();
        $this->isPartner = $this->customer->isPartner();
        $this->country_id = $this->customer->getCountryId();
        $this->check_privilege();

        $this->load->model('account/customerpartner/mapping_management');
        $this->model = $this->model_account_customerpartner_mapping_management;
        $this->load->language('account/customerpartner/mapping_management');
    }

    public function index()
    {
        $this->load->model('account/mapping_management');
        $data['breadcrumbs'] = [
            [
                'text' => 'Sales Order Management',
                'href' => 'javascript:void(0);',
                'separator' => $this->language->get('text_separator')
            ],
            [
                'text' => 'External Platform Mapping',
                'href' => $this->url->link('account/customerpartner/mapping_management'),
                'separator' => $this->language->get('text_separator')
            ]
        ];
        $this->document->setTitle($this->language->get('heading_title'));
        $data['item_code_list'] = $this->url->link('account/customerpartner/mapping_management/list');
        $data['urlBatchDelete'] = $this->url->link('account/customerpartner/mapping_management/batchDelete', '', true);
        $data['urlPlatformSKUCheckOnly'] = $this->url->link('account/customerpartner/mapping_management/platformSKUCheckOnly', '', true);
        $data['itemCodeMappingImport'] = $this->url->link('account/customerpartner/mapping_management/import', '', true);

        $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');
        $data['footer'] = $this->load->controller('account/customerpartner/footer');
        $data['header'] = $this->load->controller('account/customerpartner/header');
        $this->response->setOutput($this->load->view('account/customerpartner/mapping_management/index', $data));
    }


    public function list()
    {
        $platform_id = intval(get_value_or_default($this->request->get, 'filter_platform_id', 0));
        $search_sku = trim($this->request->get('filter_search_sku', ''));
        $search_sku_store = trim(get_value_or_default($this->request->get, 'filter_search_sku_store', ''));
        $page_num = intval(get_value_or_default($this->request->get, 'page', 1));
        $page_limit = intval(get_value_or_default($this->request->get, 'page_limit', 10));
        $sort = trim(get_value_or_default($this->request->get, 'sort', ''));
        $order = trim(get_value_or_default($this->request->get, 'order', 'desc'));

        $data['filter_platform_id'] = $platform_id;
        $data['filter_search_sku'] = $search_sku;
        $data['filter_search_sku_store'] = $search_sku_store;
        $data['sort'] = $sort;
        $data['order'] = ($sort && 'asc' == $order) ? 'desc' : 'asc';
        $data['class_order'] = $order;//页面升序降序的图标

        $param = [
            'platform_id' => $platform_id,
            'search_sku' => $search_sku,
            'search_sku_store' => $search_sku_store,
            'sort' => $sort,
            'order' => $order,
            'page_num' => $page_num > 1 ? $page_num : 1,
            'page_limit' => $page_limit,
            'customer_id' => $this->customer_id
        ];

        $data['total'] = $total = $this->model->lists($param, 'total');
        $data['itemCodeMappingList'] = $this->model->lists($param);

        $data['platformKeyList'] = $this->model->platform(); //platform下拉框
        $data['page_view'] = $this->load->controller('common/pagination', ['total' => $data['total'], 'page_num' => $page_num, 'page_limit' => $page_limit]);
        $data['itemCodeMappingTemplate'] = $this->url->link('account/customerpartner/mapping_management/downloadTemplate', '', true);
        $data['importGuide'] = $this->url->link('account/customerpartner/mapping_management/mapping_guide', '', true);

        $this->response->setOutput($this->load->view('account/mapping_item_code/mapping_item', $data));
    }

    public function mapping_guide()
    {
        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/home'),
                'separator' => false
            ],
            [
                'text' => $this->language->get('Seller Center'),
                'href' => 'javascript:void(0);',
                'separator' => $this->language->get('text_separator')
            ],
            [
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('account/customerpartner/mapping_management'),
                'separator' => $this->language->get('text_separator')
            ],
            [
                'text' => $this->language->get('guide_title'),
                'href' => 'javascript:void(0);',
                'separator' => $this->language->get('text_separator')
            ]
        ];
        $this->document->setTitle($this->language->get('guide_title'));
        $data['itemCodeMappingTemplate'] = $this->url->link('account/customerpartner/mapping_management/downloadTemplate', '', true);

        $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');
        $data['footer'] = $this->load->controller('account/customerpartner/footer');
        $data['header'] = $this->load->controller('account/customerpartner/header');
        $this->response->setOutput($this->load->view('account/customerpartner/mapping_management/mapping_guide', $data));
    }


    /* Api调用 */
    public function save()
    {
        $platform_id  = intval(get_value_or_default($this->request->post, 'platform_id', 0));
        $platform_sku = trim(get_value_or_default($this->request->post, 'platform_sku', ''));
        $platform_sku_store = trim(get_value_or_default($this->request->post, 'platform_sku_store', null));
        $sku          = trim(get_value_or_default($this->request->post, 'b2b_sku', ''));

        $json = [
            'ret' => 0,
            'msg' => 'Marketplace/Marketplace SKU/B2B Item Code can not be blank.'
        ];
        if ($platform_id && $platform_sku && $platform_sku_store && $sku) {
            $json = $this->verify();
            if (1 == $json['ret']) {
                $data['platform_id'] = $platform_id;
                $data['platform_sku'] = $platform_sku;
                $data['platform_sku_store'] = $platform_sku_store;
                $data['sku'] = $sku;
                $data['product_id'] = $json['product_id'];
                $id = $this->model->save($data);
                $info = $this->model->getInfoById($id);
                $this->model->log([], $info, 'add');
                $json['ret'] = 1;
                $json['msg'] = 'Submit successfully!';
            }
        }
        $this->response->returnJson($json);
    }

    public function updates()
    {
        $id = intval(get_value_or_default($this->request->post, 'mapping_item_code_id', 0));
        $platform_id = intval(get_value_or_default($this->request->post, 'platform_id', 0));
        $platform_sku = trim(get_value_or_default($this->request->post, 'platform_sku', ''));
        $platform_sku_store = trim(get_value_or_default($this->request->post, 'platform_sku_store', ''));
        $sku = trim(get_value_or_default($this->request->post, 'b2b_sku', ''));

        $json = [
            'ret' => 0,
            'msg' => 'Marketplace/Marketplace SKU/B2B Item Code can not be blank.'
        ];
        if ($id && $platform_id && $platform_sku && $sku) {
            $json = $this->verify();
            if (1 == $json['ret']) {
                $old_info = $this->model->getInfoById($id);
                if ($old_info) {
                    $param = [
                        'id' => $id,
                        'platform_id' => $platform_id,
                        'platform_sku' => $platform_sku,
                        'platform_sku_store' => $platform_sku_store,
                        'sku' => $sku,
                        'product_id' => $json['product_id']
                    ];
                    $this->model->updateMap($param);
                    $new_info = $this->model->getInfoById($id);
                    $this->model->log($old_info, $new_info, 'update');
                    $json = [
                        'ret' => 1,
                        'msg' => 'Submitted successfully.'
                    ];
                }
            }
        }
        $this->response->returnJson($json);
    }

    /*
     * 参数校验
     * */
    private function verify()
    {
        $id = intval(get_value_or_default($this->request->post, 'mapping_item_code_id', 0));
        $platform_id = intval(get_value_or_default($this->request->post, 'platform_id', 0));
        $platform_sku = trim(get_value_or_default($this->request->post, 'platform_sku', ''));
        $sku = trim(get_value_or_default($this->request->post, 'b2b_sku', ''));
        $platform_name = trim(get_value_or_default($this->request->post, 'platform_name', ''));
        $platform_sku_store = trim(get_value_or_default($this->request->post, 'platform_sku_store', ''));

        $json['ret'] = 1;
        $json['msg'] = '';
        if ($platform_id < 1) {
            $json['ret'] = 0;
            $json['msg'] = 'Marketplace can not be blank.';
            return $json;
        }
        if (strlen($platform_sku) < 1) {
            $json['ret'] = 0;
            $json['msg'] = 'Marketplace SKU can not be blank.';
            return $json;
        }
        if (strlen($_POST['platform_sku']) > 40) {
            $json['ret'] = 0;
            $json['msg'] = 'Marketplace SKU can not be more than 40 characters.';
            return $json;
        }
        if (strlen($sku) < 1) {
            $json['ret'] = 0;
            $json['msg'] = 'B2B Item Code can not be blank.';
            return $json;
        }
        $exists = $this->model->checkPlatformSkuExists($platform_id, $platform_sku_store, $platform_sku, $id);
        if ($exists) {
            $json['ret'] = 0;
            $json['msg'] = 'This ['
                . $platform_name . ' - ' . $platform_sku_store . ' - ' . $platform_sku
                . '] has already built the mapping. Please edit in the listing page.';
            return $json;
        }

        //判断sku有效，b2b系统存在的SKU，才可以绑定。
        $result = $this->model->itemCodeCheck($sku);
        if (!$result) {
            $json['ret'] = 0;
            $json['msg'] = 'This B2B Item Code [' . $sku . '] is invalid, please check the value.';
        } else {
            $json['product_id'] = $result['product_id'];
        }
        return $json;
    }

    public function checkOrderNumByPlatformSku()
    {
        $json['ret'] = 0;
        $json['msg'] = 'The request is wrong.';
        $id = intval($this->request->get['id'] ?? 0); //oc_mapping_sku表主键
        if ($id) { //信息不存在
            $info = $this->model->getInfoById($id);
            if ($info) {
                $num = $this->model->checkOrderNum($info);
                $json['ret'] = 1;
                $json['msg'] = '';
                $json['num'] = $num;
            }
        }
        $this->response->returnJson($json);
    }

    public function platformSKUCheckOnly()
    {
        $id = intval(get_value_or_default($this->request->post, 'mapping_item_code_id', 0));
        $platform_id = intval(get_value_or_default($this->request->post, 'platform_id', 0));
        $platform_sku = trim(get_value_or_default($this->request->post, 'platform_sku', ''));
        $platform_name = trim(get_value_or_default($this->request->post, 'platform_name', ''));
        $platform_store = trim(get_value_or_default($this->request->post, 'platform_sku_store', ''));
        $param = [
            'id' => $id,
            'platform_id' => $platform_id,
            'platform_sku' => $platform_sku,
            'customer_id' => $this->customer_id,
            'platform_store' => $platform_store,
        ];
        $result = $this->model->platformSKUCheckOnly($param);
        if (empty($result)) {
            $json['ret'] = 1;
            $json['msg'] = 'Success';
        } else {
            $json['ret'] = 0;
            $json['msg'] = 'This ['
                . $platform_name . ' - ' . $platform_store . ' - ' . $platform_sku
                . '] has already built the mapping. Please edit in the listing page.';
        }
        $this->response->returnJson($json);
    }

    public function batchDelete()
    {
        $json = [
            'ret' => 0,
            'msg' => 'Failed!'
        ];
        $ids = trim($this->request->post['ids']);
        if (strlen($ids) && is_numeric(str_replace(',', '', $ids))) {
            $idList = explode(',', $ids);
            $flag = $this->model->batchDelete($idList);
            if ($flag) {
                $json = [
                    'ret' => 1,
                    'msg' => 'Deleted successfully! '
                ];
            }
        }
        $this->response->returnJson($json);
    }

    /*
     * 下载模板
     * */
    public function downloadTemplate()
    {
        $file = DIR_DOWNLOAD . 'Seller Item Code Mapping Template.xlsx';
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

    //导入模板
    public function import()
    {
        $json['msg'] = 'No data upload.';
        $json['error'] = 1;
        // 1.校验文件格式，获取excel数据
        $files = $this->request->files['file'];
        $fileType = strrchr($files['name'], '.');
        if (!in_array($fileType, ['.xls', '.xlsx'])) {
            $json['msg'] = 'Required file format: .xls or .xlsx';
        }
        if ($files['error'] != UPLOAD_ERR_OK) {
            $json['msg'] = $this->language->get('error_upload_' . $files['error']);
        }
        $this->load->model('tool/excel');
        $data = $this->model_tool_excel->getExcelData($files['tmp_name']);
        $map = [];
        if (count($data) > 1) {
            // 检测 header
            $excelPK = [];
            $platformKeyList = $this->model->platformKeyValue();
            $platform_string = implode(' / ', $platformKeyList);
            $platformKeyListUpper = [];
            foreach ($platformKeyList as $k => $v) {
                $platformKeyListUpper[$k] = strtoupper($v);
            }
            foreach ($data as $k => $v) {
                if ($k == 0) {
                    if (4 != count($v) || 'Platform' != trim($v[0]) || 'Platform Store Name' != trim($v[1]) || 'Platform SKU' != trim($v[2]) || 'B2B Item Code' != trim($v[3])) {
                        $json['msg'] = 'The file uploaded is not valid. Please refer to our sample template for right format. ';
                        $map = [];
                        break;
                    }
                } else {
                    $p = trim($v[0]);
                    $pStoreName = trim($v[1]);
                    $pSku = trim($v[2]);
                    $sku = trim($v[3]);
                    if (mb_strlen($p) === 0) {
                        $json['msg'] = 'Line ' . ($k + 1) . ', Platform [' . $p . '] can not be blank, please check and try again.';
                        $map = [];
                        break;
                    }
                    if (mb_strlen($pStoreName) === 0) {
                        $json['msg'] = 'Line ' . ($k + 1) . ', Platform Store Name [' . $pStoreName . '] can not be blank, please check and try again.';
                        $map = [];
                        break;
                    }
                    if (mb_strlen($pSku) === 0) {
                        $json['msg'] = 'Line ' . ($k + 1) . ', Platform SKU [' . $pSku . '] can not be blank, please check and try again.';
                        $map = [];
                        break;
                    }
                    if (mb_strlen($sku) === 0) {
                        $json['msg'] = 'Line ' . ($k + 1) . ', B2B Item Code [' . $sku . '] can not be blank, please check and try again.';
                        $map = [];
                        break;
                    }
                    if (!in_array(strtoupper($p), $platformKeyListUpper)) {
                        $json['msg'] = 'Line ' . ($k + 1) . ', Platform [' . $p . '] is wrong, please only enter ' . $platform_string;
                        $map = [];
                        break;
                    } else {
                        $platformId = array_search(strtoupper($p), $platformKeyListUpper);
                    }
                    if (mb_strlen($pSku) > 40) {
                        $json['msg'] = 'Line ' . ($k + 1) . ', Platform [' . $pSku . '] can not be more than 40 characters.';
                        $map = [];
                        break;
                    }
                    $platformSku = iconv('gb2312', 'utf-8', $pSku);
                    $platformSku = $platformSku ? $platformSku : $pSku;
                    $pkStr =  $p . '-' . $pStoreName . '-' . $platformSku;
                    $exists = $this->model->checkPlatformSkuExists($platformId, $pStoreName, $platformSku);
                    if ($exists || in_array($pkStr, $excelPK)) {
                        $json['msg'] = 'Line ' . ($k + 1) . ', [' . $p . '-' . $pStoreName . '-' . $platformSku . '] has already built the mapping. Please edit in the listing page.';
                        $map = [];
                        break;
                    }
                    $skuExists = $this->model->itemCodeCheck($sku);
                    if (empty($skuExists)) {
                        $json['msg'] = 'Line ' . ($k + 1) . ', B2B Item Code [' . $sku . '] is invalid, please check and try again.';
                        $map = [];
                        break;
                    }
                    $excelPK[] = $pkStr;
                    $map[] = [
                        'customer_id' => $this->customer_id,
                        'platform_id' => $platformId,
                        'platform_sku' => $platformSku,
                        'platform_sku_store' => $pStoreName,
                        'sku' => $sku,
                        'product_id' => $skuExists['product_id'],
                        'status' => 1,
                        'date_add' => date('Y-m-d H:i:s'),
                        'date_modified' => date('Y-m-d H:i:s')
                    ];
                }
            }
        }

        if ($map) {
            $flag = $this->model->batchSave($map);
            if ($flag) {
                $json['msg'] = 'Imported successfully';
                $json['error'] = 0;
            }
        }
        $this->response->returnJson($json);
    }

    // 下载csv文件
    public function filterByCsv()
    {
        $filter_data['platform_id'] = intval(get_value_or_default($this->request->get, 'filter_platform_id', 0));
        $filter_data['search_sku'] = trim($this->request->get('filter_search_sku', ''));
        $filter_data['search_sku_store'] = trim(get_value_or_default($this->request->get, 'filter_search_sku_store', ''));
        // 默认desc taixing
        $filter_data['order'] = trim(get_value_or_default($this->request->get, 'order', 'desc'));
        $result = $this->model->lists($filter_data, 'csv_all');
        $fileName = 'Item Code Mapping List ' . date('Ymd') . '.csv';

        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=\"" . $fileName . "\"");
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        //echo chr(239).chr(187).chr(191);
        $fp = fopen('php://output', 'a');
        //在写入的第一个字符串开头加 bom。
        $bom = chr(0xEF) . chr(0xBB) . chr(0xBF);
        fwrite($fp, $bom);
        $head = [
            'Platform',
            'Platform Store Name',
            'Platform SKU',
            'B2B Item Code',
            'Last Modified'
        ];

        fputcsv($fp, $head);
        foreach ($result as $key => $value) {
            $line = [
                $value['platform_name'],
                $value['platform_sku_store'],
                "\t" . $value['platform_sku'],
                "\t" . $value['sku'],
                "\t" . changeOutPutByZone($value['date_modified'], $this->session, 'Y-m-d H:i:s'),
            ];
            fputcsv($fp, $line);
        }
        $output = stream_get_contents($fp);
        fclose($fp);
    }

    public function autocomplete_sku()
    {
        $json = [];
        $sku = $this->request->get['b2b_sku'] ?? '';
        $results = $this->model->autocomplete_sku($sku, 15); //取前15个
        if ($results) {
            foreach ($results as $result) {
                $tmp['sku'] = $result['sku'];
                $json[] = $tmp;
            }
        }
        $this->response->returnJson($json);
    }


    //数据库支持操作 1、修改oc_setting配置：添加seller后台左侧菜单
    public function update_oc_setting()
    {
        $data = $this->orm->table('oc_setting')
            ->where(['key' => 'marketplace_allowed_account_menu'])
            ->select(['value'])
            ->get()->toArray();
        $data = json_decode($data[0]->value, true);
        if (isset($data['external_platform_mapping'])) {
            echo 'ok';
        } else {
            $data['external_platform_mapping'] = 'external_platform_mapping';
            var_export($data);
            $this->orm->table('oc_setting')
                ->where(['key' => 'marketplace_allowed_account_menu'])
                ->update(['key' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        }
    }

    //数据库支持操作 2、修改oc_mapping_sku表：添加platform_sku_store，保存店铺
    public function add_field_to_oc_mapping_sku()
    {
        $PDO = $this->orm->getConnection()->getPdo();
        $data = $PDO->query('show fields from oc_mapping_sku')->fetchAll();
        $fields = array_column($data, 'Field');
        var_export($fields);
        if (in_array('platform_sku_store', $fields)) {
            echo 'ok';
        } else {
            echo PHP_EOL;
            $PDO->query('ALTER TABLE `oc_mapping_sku` ADD `platform_sku_store` VARCHAR(255) NULL DEFAULT NULL COMMENT \'platform_sku来自的店铺名称\' AFTER `platform_sku`');
            $data = $PDO->query('show fields from oc_mapping_sku')->fetchAll();
            $fields = array_column($data, 'Field');
            var_export($fields);
        }
    }

}
