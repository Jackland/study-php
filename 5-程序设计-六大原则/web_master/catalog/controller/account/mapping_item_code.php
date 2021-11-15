<?php

use App\Catalog\Controllers\AuthController;
use App\Enums\Country\Country;

/**
 * Class ControllerAccountMappingItemCode
 * @property ModelAccountMappingItemCode $model_account_mapping_item_code
 * @property ModelAccountPlatform $model_account_platform
 * @property ModelToolExcel $model_tool_excel
 */
class ControllerAccountMappingItemCode extends AuthController
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
    }

    public function index()
    {
        //加载Model类
        load()->model('account/platform');
        load()->model('account/mapping_item_code');
        load()->model('account/mapping_management');

        $platform_id = (int)$this->request->get('filter_platform_id', 0);
        $search_sku = trim($this->request->get('filter_search_sku', ''));
        $page_num = (int)$this->request->get('page_num', 1);
        $page_limit = (int)$this->request->get('page_limit', 10);
        $sort = trim($this->request->get('sort', ''));
        $order = trim($this->request->get('order', 'desc'));

        $data['filter_platform_id'] = $platform_id;
        $data['filter_search_sku'] = $search_sku;
        $data['sort'] = $sort;
        $data['order'] = ($sort && 'asc' == $order) ? 'desc' : 'asc';
        $data['class_order'] = $order;//页面升序降序的图标

        $url = '';
        if ($platform_id) {
            $url .= '&filter_platform_id=' . $platform_id;
        }
        if ($search_sku) {
            $url .= '&filter_search_sku=' . $search_sku;
        }
        if ($sort) {
            $url .= '&sort=' . $sort;
        }
        if ($order) {
            $url .= '&order=' . $order;
        }
        $data['url'] = $url;

        $customer_id = $this->customer->getId();
        $param = [
            'platform_id' => $platform_id,
            'search_sku' => $search_sku,
            'sort' => $sort,
            'order' => $order,
            'page_num' => $page_num,
            'page_limit' => $page_limit,
            'customer_id' => $customer_id
        ];

        $data['total'] = $total = $this->model_account_mapping_item_code->total($param);
        $data['itemCodeMappingList'] = $this->model_account_mapping_item_code->lists($param);

        //platform下拉框
        $platformKeyList = $this->model_account_platform->keyList();
        $data['platformKeyList'] = $platformKeyList;

        //分页
        $total_pages = ceil($total / $page_limit);
        $data['page_limit'] = $page_limit;
        $data['page_num'] = $page_num;
        $data['total_pages'] = $total_pages;
        $data['total_num'] = $total;
        $pagination = new Pagination();
        $pagination->total = $total;
        $pagination->page = $page_num;
        $pagination->limit = $page_limit;
        $pagination->url = $this->url->link('account/order' . $url, 'page={page}', true);
        $resultstring = sprintf($this->language->get('text_pagination'), ($total) ? (($page_num - 1) * $page_limit) + 1 : 0, ((($page_num - 1) * $page_limit) > ($total - $page_limit)) ? $total : ((($page_num - 1) * $page_limit) + $page_limit), $total, $total_pages);
        $data['results'] = $pagination->results($resultstring);

        $data['urlBatchDelete'] = $this->url->link('account/mapping_item_code/batchDelete', '', true);
        $data['urlItemCodeCheck'] = $this->url->link('account/mapping_item_code/itemCodeCheck', '', true);
        $data['urlPlatformSKUCheckOnly'] = $this->url->link('account/mapping_item_code/platformSKUCheckOnly', '', true);
        $data['itemCodeMappingTemplate'] = $this->url->link('account/mapping_item_code/downloadTemplate', '', true);
        $data['itemCodeMappingImport'] = $this->url->link('account/mapping_item_code/import', '', true);
        $data['itemCodeMappingGuide'] = $this->url->link('account/mapping_item_code/guide', '', true);

        $this->response->setOutput(load()->view('account/mapping_item_code/index', $data));
    }


    public function save()
    {
        $platform_id = $this->request->post('platform_id', 0);
        $platform_sku = trim($this->request->post('platform_sku', ''));
        $platform_sku_store = trim($this->request->post('platform_sku_store', null));
        $sku = trim($this->request->post('b2b_sku', ''));
        $json = $this->verify();
        if (0 == $json['ret']) {
            goto end;
        }
        $data = [];
        $data['platform_id'] = $platform_id;
        $data['platform_sku'] = $platform_sku;
        $data['platform_sku_store'] = $platform_sku_store;
        $data['sku'] = $sku;
        $data['product_id'] = $json['product_id'];
        $id = $this->model_account_mapping_item_code->save($data);
        $info = $this->model_account_mapping_item_code->getInfoById($id);
        $this->model_account_mapping_item_code->log([], $info, 'add');
        $json['ret'] = 1;
        $json['msg'] = 'Submitted successfully.';

        end:
        $this->response->json($json);
    }


    public function updates()
    {
        $id = $this->request->post('mapping_item_code_id', 0);
        $platform_id = $this->request->post('platform_id', 0);
        $platform_sku = trim($this->request->post('platform_sku', ''));
        $platform_sku_store = trim($this->request->post('platform_sku_store', null));
        $sku = trim($this->request->post('b2b_sku', ''));

        if ($id && $platform_id && $platform_sku && $sku) {
            $json = $this->verify();
            if (0 == $json['ret']) {
                goto end;
            }
            load()->model('account/mapping_item_code');
            $old_info = $this->model_account_mapping_item_code->getInfoById($id);
            if (empty($old_info)) {
                goto end;
            }
            $param = [
                'id' => $id,
                'platform_id' => $platform_id,
                'platform_sku' => $platform_sku,
                'platform_sku_store' => $platform_sku_store,
                'sku' => $sku,
                'product_id' => $json['product_id']
            ];
            $this->model_account_mapping_item_code->updateMap($param);
            $new_info = $this->model_account_mapping_item_code->getInfoById($id);
            $this->model_account_mapping_item_code->log($old_info, $new_info, 'update');
            $json = [
                'ret' => 1,
                'msg' => 'Submitted successfully.'
            ];
        } else {
            $json = [
                'ret' => 0,
                'msg' => 'Marketplace/Marketplace SKU/B2B Item Code can not be left blank.'
            ];
        }

        end:
        $this->response->json($json);
    }

    /*
     * 参数校验
     * */
    protected function verify()
    {
        $id = $this->request->post('mapping_item_code_id',0);
        $platform_id = $this->request->post('platform_id',0);
        $platform_sku = trim($this->request->post('platform_sku',''));
        $sku = trim($this->request->post('b2b_sku',''));
        $platform_name = trim($this->request->post('platform_name',''));
        $json['ret'] = 1;
        $json['msg'] = '';
        if ($platform_id < 1) {
            $json['ret'] = 0;
            $json['msg'] = 'Marketplace can not be left blank.';
            return $json;
        }
        if (strlen($platform_sku) < 1) {
            $json['ret'] = 0;
            $json['msg'] = 'Marketplace SKU can not be left blank.';
            return $json;
        }
        if (strlen($_POST['platform_sku']) > 40) {
            $json['ret'] = 0;
            $json['msg'] = 'Marketplace SKU can not be more than 40 characters.';
            return $json;
        }
        if (strlen($sku) < 1) {
            $json['ret'] = 0;
            $json['msg'] = 'B2B Item Code can not be left blank.';
            return $json;
        }

        load()->model('account/mapping_item_code');
        $exists = $this->model_account_mapping_item_code->checkPlatformSkuExists($platform_id, $platform_sku, $id);
        if ($exists) {
            $json['ret'] = 0;
            $json['msg'] = 'This [' . $platform_name . ' - ' . $platform_sku . '] has already built the mapping. Please edit in the listing page.';
            return $json;

        }

        //判断sku有效，b2b系统存在的SKU，才可以绑定。
        $result = $this->model_account_mapping_item_code->itemCodeCheck(['sku' => $sku]);
        if (!$result) {
            $json['ret'] = 0;
            $json['msg'] = 'This B2B Item Code [' . $sku . '] is invalid, please check the value.';
        } else {
            $json['product_id'] = $result['product_id'];
        }

        return $json;
    }

    public function platformSKUCheckOnly()
    {
        $id = $this->request->post('mapping_item_code_id',0);
        $platform_id = $this->request->post('platform_id',0);
        $platform_sku = trim($this->request->post('platform_sku',''));
        $platform_name = trim($this->request->post('platform_name',''));
        $customer_id = $this->customer->getId();

        $param = [
            'id' => $id,
            'platform_id' => $platform_id,
            'platform_sku' => $platform_sku,
            'customer_id' => $customer_id
        ];

        load()->model('account/mapping_item_code');
        $result = $this->model_account_mapping_item_code->platformSKUCheckOnly($param);

        if ($result) {
            $json['ret'] = 0;
            $json['msg'] = 'This [' . $platform_name . ' - ' . $platform_sku . '] has already built the mapping. Please edit in the listing page.';
        } else {
            $json['ret'] = 1;
            $json['msg'] = 'Success';
        }

        $this->response->json($json);
    }

    public function itemCodeCheck()
    {

        $sku = trim($this->request->post('b2b_sku',''));
        $param = [
            'sku' => $sku,
        ];

        load()->model('account/mapping_item_code');
        $result = $this->model_account_mapping_item_code->itemCodeCheck($param);

        if ($result) {
            $json['ret'] = 1;
            $json['msg'] = 'Success';

        } else {
            $json['ret'] = 0;
            $json['msg'] = 'B2B Item Code[' . $sku . '] is invalid, please check the value.';
        }

        $this->response->json($json);
    }

    /**
     *
     */
    public function checkOrderNumByPlatformSku()
    {

        $json['ret'] = 0;
        $json['msg'] = 'The request is wrong.';

        $id = (int)trim($this->request->get('id',0)); //oc_mapping_sku表主键
        if ($id < 1) {
            $json['ret'] = 0;
            $json['msg'] = 'The request is wrong.';
            goto end;
        }

        //信息不存在
        load()->model('account/mapping_item_code');
        $info = $this->model_account_mapping_item_code->getInfoById($id);
        if (!$info) {
            $json['ret'] = 0;
            $json['msg'] = 'The request is wrong.';
            goto end;
        }

        $num = $this->model_account_mapping_item_code->checkOrderNum($info);
        $json['ret'] = 1;
        $json['msg'] = '';
        $json['num'] = $num;

        end:
        $this->response->json($json);
    }


    public function autocompletesku()
    {
        $json = [];

        $sku = trim($this->request->get('b2b_sku',''));
        if (strlen($sku) < 1) {
            goto end;
        }
        $filter_data = array(
            'filter_name' => $sku,
            'start' => 0,
            'limit' => 15
        );

        load()->model('account/mapping_item_code');

        $results = $this->model_account_mapping_item_code->autocompletesku($filter_data);
        if ($results) {
            foreach ($results as $result) {
                $tmp['sku'] = $result['sku'];
                $json[] = $tmp;
            }
        }


        end:
        $this->response->json($json);
    }


    public function filterByCsv()
    {
        $platform_id = $this->request->get('filter_platform_id',0);
        $search_sku = trim($this->request->get('filter_search_sku',''));

        $filter_data = [];
        $filter_data['platform_id'] = $platform_id;
        $filter_data['search_sku'] = $search_sku;

        load()->model('account/mapping_item_code');
        $result = $this->model_account_mapping_item_code->lists($filter_data, 1);

        $fileName = 'Item Code Mapping List ' . date('Ymd', time()) . '.csv';

        //header('Content-Encoding: UTF-8');
        //header("Content-Type: text/csv; charset=UTF-8");
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
            'Platform SKU',
            'B2B Item Code',
            'Last Modified'
        ];

        fputcsv($fp, $head);
        foreach ($result as $key => $value) {
            $line = [
                $value['platform_name'],
                "\t" . $value['platform_sku'],
                "\t" . $value['sku'],
                "\t" . changeOutPutByZone($value['date_modified'], $this->session, 'Y-m-d H:i:s'),
            ];
            fputcsv($fp, $line);
        }
        $output = stream_get_contents($fp);
        fclose($fp);
    }

    /*
     * 下载模板
     * */
    public function downloadTemplate()
    {
        $file = DIR_DOWNLOAD . 'Item Code Mapping Template.xls';
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

    /*
     * 删
     * */
    public function batchDelete()
    {
        $ids = trim($this->request->post('ids'));
        $idList = explode(',', $ids);
        $json = [
            'ret' => 0,
            'msg' => 'Failed!'
        ];
        if ($idList) {
            load()->model('account/mapping_item_code');
            $flag = $this->model_account_mapping_item_code->batchDelete($idList);
            if ($flag) {
                $json = [
                    'ret' => 1,
                    'msg' => 'Deleted successfully! '
                ];
            }
        }

        $this->response->json($json);
    }

    /**
     * [import description] 此需求和其他需求冲突，暂时按这个方法写
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    public function import()
    {
        // 1.校验文件格式
        $json = [];
        $files = $this->request->files['file'];
        $fileName = $files['name'];
        $fileType = strrchr($fileName, '.');
        if (!in_array($fileType, ['.xls', '.xlsx'])) {
            $json['msg'] = 'Required file format: .xls or .xlsx';
        }
        if ($files['error'] != UPLOAD_ERR_OK) {
            $json['msg'] = $this->language->get('error_upload_' . $files['error']);
        }
        //2.获取excel数据
        load()->model('tool/excel');
        load()->model('account/mapping_item_code');
        $data = $this->model_tool_excel->getExcelData($files['tmp_name']);
        $country_id = $this->customer->getCountryId();
        $isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
        if ($isCollectionFromDomicile) {
            $platform = $this->model_account_mapping_item_code->getMatchPlatform($country_id);
        } else {
            $platform = $this->model_account_mapping_item_code->platform();
        }
        $platform_compare = [];
        $platform_string = '';
        foreach ($platform as $key => $value) {
            $platform_string .= $value . ' / ';
            $platform_compare[strtoupper($value)] = $key;
        }
        $platform_string = trim($platform_string, ' / ');
        if ($isCollectionFromDomicile) {
            if ($country_id == AMERICAN_COUNTRY_ID) {
                $platform_string = 'Amazon / Wayfair / Walmart / HomeDepot / overstock';
            } elseif ($country_id == Country::BRITAIN) {
                $platform_string = 'Amazon / Wayfair';
            } elseif ($country_id == Country::GERMANY) {
                $platform_string = 'Wayfair';
            } else {
                $platform_string = 'Amazon / Wayfair / Walmart / HomeDepot / overstock';
            }
        }
        $excelPK = [];
        $map = [];
        // 检测 header
        if (count($data) > 1) {
            foreach ($data as $k => $v) {
                if ($k == 0) {
                    if (3 != count($v) || 'Platform' != trim($v[0]) || 'Platform SKU' != trim($v[1]) || 'B2B Item Code' != trim($v[2])) {
                        $json['msg'] = 'The file uploaded is not valid. Please refer to our sample template for right format.';
                        break;
                    }
                } else {
                    $p = trim($v[0]);
                    $pSKU = trim($v[1]);
                    $sku = trim($v[2]);
                    if (!isset($platform_compare[strtoupper($p)])) {
                        $json['msg'] = 'Line ' . ($k + 1) . ', Platform [' . $p . '] is wrong, please only enter<br> ' . $platform_string;
                        break;
                    } else {
                        $platformId = $platform_compare[strtoupper($p)];
                    }
                    if (strlen($pSKU) > 40) {
                        $json['msg'] = 'Line ' . ($k + 1) . ', Platform [' . $pSKU . '] can not be more than 40 characters.';
                        break;
                    }
                    $pkStr = $platformId . '_' . $pSKU;
                    $platformSku = iconv('gb2312', 'utf-8', $pSKU);
                    $exists = $this->model_account_mapping_item_code->checkPlatformSkuExists($platformId, $platformSku);
                    if ($exists || in_array($pkStr, $excelPK)) {
                        $json['msg'] = 'Line ' . ($k + 1) . ', [' . $p . '-' . $platformSku . '] has already built the mapping. Please edit in the listing page.';
                        break;
                    }
                    $skuExists = $this->model_account_mapping_item_code->itemCodeCheck(['sku' => $sku]);
                    if (empty($skuExists)) {
                        $json['msg'] = 'Line ' . ($k + 1) . ', B2B Item Code [' . $sku . '] is invalid, please check the value. ';
                        break;
                    }
                    $excelPK[] = $pkStr;
                    $map[] = [
                        'customer_id' => $this->customer->getId(),
                        'platform_id' => $platformId,
                        'platform_sku' => $platformSku,
                        'sku' => $sku,
                        'product_id' => $skuExists['product_id'],
                        'status' => 1,
                        'date_add' => date('Y-m-d H:i:s'),
                        'date_modified' => date('Y-m-d H:i:s')
                    ];
                }


            }
        } else {
            $json['msg'] = 'No data upload.';
        }

        if (!isset($json['msg']) && $map) {
            $flag = $this->model_account_mapping_item_code->batchSave($map);
            if (!$flag) {
                $json['msg'] = 'Failed';
            }
        }

        if (!isset($json['msg'])) {
            $json['msg'] = 'Imported successfully';
            $json['error'] = 0;
        } else {
            $json['error'] = 1;
        }
        $this->response->json($json);
    }


    /*
     * 导入
     * */
    public function import_origin()
    {
        //$json['ret'] // 0 显示错误信息  1成功  2不显示信息
        $time = time();
        if ($this->session->get('upload_file_sku_time','')) {
            if ($time - $this->session->get('upload_file_sku_time') < 5) {
                $json = [
                    'ret' => 2,
                    'msg' => 'resubmit'
                ];
                goto end;
            } else {
                $this->session->remove('upload_file_sku_time');
            }
        } else {
            $this->session->set('upload_file_sku_time',time());
        }


        //获取文件名
        $filename = $_FILES['file']['name'];
        //获取文件临时路径
        $filePath = $_FILES['file']['tmp_name'];
        $isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
        //$filePath = 'C:\Users\liangbo\Downloads\Item Code Mapping List 20200216.csv';
        if ('.csv' != substr($filename, -4, 4)) {
            $json = [
                'ret' => 0,
                'msg' => 'Invalid file type'
            ];

        } else {
            $handle = fopen($filePath, "rb");
            $data = [];
            while (!feof($handle)) {
                $data[] = fgetcsv($handle);
            }
            fclose($handle);
            $country_id = $this->customer->getCountryId();
            load()->model('account/mapping_item_code');
            //$platform = $this->model_account_mapping_item_code->platform();
            $platform = $this->model_account_mapping_item_code->getMatchPlatform($country_id);
            $json = [
                'ret' => 1,
                'msg' => 'Imported successfully'
            ];
            $csvPK = [];
            $map = [];
            $t = date('Y-m-d H:i:s');
            foreach ($data as $k => $v) {
                if (!$v) {
                    break;
                }
                if (0 == $k) {
                    if (3 != count($v) || 'Platform' != $v[0] || 'Platform SKU' != $v[1] || 'B2B Item Code' != $v[2]) {
                        $json = [
                            'ret' => 0,
                            'msg' => 'The file uploaded is not valid. Please refer to our sample template for right format.638 '
                        ];
                        break;
                    }
                } else {
                    $p = trim($v[0]);
                    $pSKU = trim($v[1]);
                    $sku = trim($v[2]);

                    foreach ($platform as $kk => $vv) {
                        $platform[$kk] = strtoupper($vv);
                    }
                    $platformVK = array_flip($platform);

                    $platformId = empty($platformVK[strtoupper($p)]) ? 0 : $platformVK[strtoupper($p)];
                    if ($isCollectionFromDomicile) {
                        if ($country_id == 223) {
                            $platform_string = 'Amazon / Wayfair / Walmart';
                        } elseif ($country_id == 222) {
                            $platform_string = 'Amazon / Wayfair';
                        } elseif ($country_id == 81) {
                            $platform_string = 'Wayfair';
                        } else {
                            $platform_string = 'Amazon / Wayfair / Walmart';
                        }
                    } else {
                        $platform_string = 'Amazon / Wayfair / Walmart';
                    }

                    if (!$platformId) {
                        $json = [
                            'ret' => 0,
                            'msg' => 'Line ' . ($k + 1) . ', Platform [' . $p . '] is wrong, please only enter<br> ' . $platform_string . '.',
                        ];
                        break;
                    }

                    if (strlen($pSKU) > 40) {
                        $json = [
                            'ret' => 0,
                            'msg' => 'Line ' . ($k + 1) . ', Platform [' . $pSKU . '] can not be more than 40 characters.'
                        ];
                        break;
                    }

                    $pkStr = $platformId . '_' . $pSKU;
                    $platformSku = iconv('gb2312', 'utf-8', $pSKU);
                    $exists = $this->model_account_mapping_item_code->checkPlatformSkuExists($platformId, $platformSku);
                    if ($exists || in_array($pkStr, $csvPK)) {
                        $json = [
                            'ret' => 0,
                            'msg' => 'Line ' . ($k + 1) . ', [' . $p . '-' . $platformSku . '] has already built the mapping. Please edit in the listing page.'
                        ];
                        break;
                    }

                    $skuExists = $this->model_account_mapping_item_code->itemCodeCheck(['sku' => $sku]);
                    if (empty($skuExists)) {
                        $json = [
                            'ret' => 0,
                            'msg' => 'Line ' . ($k + 1) . ', B2B Item Code [' . $sku . '] is invalid, please check the value. '
                        ];
                        break;
                    }

                    $csvPK[] = $pkStr;
                    $map[] = [
                        'customer_id' => $this->customer->getId(),
                        'platform_id' => $platformId,
                        'platform_sku' => $platformSku,
                        'sku' => $sku,
                        'product_id' => $skuExists['product_id'],
                        'status' => 1,
                        'date_add' => $t,
                        'date_modified' => $t
                    ];
                }

            }

            if ($json['ret'] && $map) {
                $flag = $this->model_account_mapping_item_code->batchSave($map);
                if (!$flag) {
                    $json = [
                        'ret' => 0,
                        'msg' => 'failed'
                    ];
                }
            }


            if ($json['ret'] == 1) {
                $this->session->remove('upload_file_sku_time');
            }
        }
        end:
        $this->response->json($json);
    }

    /*
     * Import Item Code Mapping Guide
     * */
    public function guide()
    {
        // 加载语言层
        load()->language('account/mapping_management');
        // 设置文档标题
        $this->document->setTitle($this->language->get('heading_title'));
        // 面包屑导航
        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/home')
            ],
            [
                'text' => $this->language->get('text_account'),
                'href' => $this->url->link('account/account')
            ],
            [
                'text' => $this->language->get('text_mapping_management'),
                'href' => $this->url->link('account/mapping_management')
            ],
            [
                'text' => $this->language->get('text_mapping_guide'),
                'href' => $this->url->link('account/mapping_item_code/guide')
            ]
        ];

        $data['itemCodeMappingTemplate'] = $this->url->link('account/mapping_item_code/downloadTemplate');

        //页面公共布局
        $data['country_id'] = $this->customer->getCountryId();
        $this->response->setOutput(load()->view('account/mapping_item_code/guide', $data));
        return $this->render('account/mapping_item_code/guide', $data, [
            'column_left' => 'common/column_left',
            'column_right' => 'common/column_right',
            'content_top' => 'common/content_top',
            'content_bottom' => 'common/content_bottom',
            'footer' => 'common/footer',
            'header' => 'common/header',
        ]);

    }

}
