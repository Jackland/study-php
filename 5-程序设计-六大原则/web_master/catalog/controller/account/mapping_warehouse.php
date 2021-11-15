<?php

use App\Catalog\Controllers\AuthController;

/**
 * Class ControllerAccountMappingWarehouse
 * @property ModelAccountMappingWarehouse $model_account_mapping_warehouse
 * @property ModelAccountPlatform $model_account_platform
 */
class ControllerAccountMappingWarehouse extends AuthController
{

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
    }

    public function index()
    {
        //加载Model类
        load()->model('account/platform');
        load()->model('account/mapping_warehouse');
        load()->model('account/mapping_management');

        $customer_id = $this->customer->getId();
        $platform_id = intval($this->request->get('platform_id', 0));
        $platform_warehouse_name = trim($this->request->get('platform_warehouse_name', ''));
        $warehouse_id = intval($this->request->get('warehouse_id', 0));
        $sort = trim($this->request->get('sort', ''));
        $order = trim($this->request->get('order', ''));

        $page_num = intval($this->request->get('page_num', 1));
        $page_limit = intval($this->request->get('page_limit', 10));


        $param = [
            'customer_id' => $customer_id,
            'platform_id' => $platform_id,
            'warehouse_id' => $warehouse_id,
            'platform_warehouse_name' => $platform_warehouse_name,
            'sort' => $sort,
            'order' => $order,
            'page_num' => $page_num,
            'page_limit' => $page_limit
        ];

        $data['total'] = $total = $this->model_account_mapping_warehouse->total($param);
        $data['warehouseMappingList'] = $this->model_account_mapping_warehouse->lists($param);
        foreach ($data['warehouseMappingList'] as $key => $val) {
            $address1 = $val->Address1 ? $val->Address1 : '';
            $address2 = $val->Address2 ? ',' . $val->Address2 : '';
            $address3 = $val->Address3 ? ',' . $val->Address3 : '';
            $data['warehouseMappingList'][$key]->Address1 = trim($address1 . $address2 . $address3, ',');
        }

        //platform下拉框
        $platformKeyList = $this->model_account_platform->keyList('warehouse');
        $data['platformKeyList'] = $platformKeyList;
        //B2B Warehouse Code下拉框
        $warehouseKeyList = $this->model_account_mapping_warehouse->keyList();
        $data['warehouseKeyList'] = $warehouseKeyList;

        $tmp = $warehouseKeyList;
        foreach ($tmp as $key => $val) {
            $address1 = $val['Address1'] ? $val['Address1'] : '';
            $address2 = $val['Address2'] ? ',' . $val['Address2'] : '';
            $address3 = $val['Address3'] ? ',' . $val['Address3'] : '';
            $tmp[$key]['Address1'] = trim($address1 . $address2 . $address3, ',');
        }
        $data['warehouseKeyListJson'] = json_encode($tmp);

        $data['filter_platform_id'] = $platform_id;
        $data['platform_warehouse_name'] = $platform_warehouse_name;
        $data['filter_warehouse_id'] = $warehouse_id;
        $data['sort'] = $sort;
        $data['order'] = ($sort && 'asc' == $order) ? 'desc' : 'asc';
        $data['class_order'] = $order;//页面升序降序的图标
        $url = '';
        if ($platform_id) {
            $url .= '&filter_platform_id=' . $platform_id;
        }
        if ($platform_warehouse_name) {
            $url .= '&platform_warehouse_name=' . $platform_warehouse_name;
        }
        if ($warehouse_id) {
            $url .= '&warehouse_id=' . $warehouse_id;
        }
        if ($sort) {
            $url .= '&sort=' . $sort;
        }
        if ($order) {
            $url .= '&order=' . $order;
        }
        $data['url'] = $url;


        //分页
        $total_pages = ceil($total / $page_limit);
        $data['page_num'] = $page_num;
        $data['total_pages'] = $total_pages;
        $data['page_limit'] = $page_limit;
        $data['results'] = sprintf($this->language->get('text_pagination'), ($total) ? (($page_num - 1) * $page_limit) + 1 : 0, ((($page_num - 1) * $page_limit) > ($total - $page_limit)) ? $total : ((($page_num - 1) * $page_limit) + $page_limit), $total, $total_pages);


        //$data['urlWarehouseMappingCheckBuilt']  = $this->url->link('account/mapping_warehouse/checkBuilt', '', true);
        $data['urlWarehouseMapping'] = str_replace('&amp;', '&', $this->url->link('account/mapping_warehouse/index', $url, true));
        $data['urlWarehouseMappingRest'] = str_replace('&amp;', '&', $this->url->link('account/mapping_warehouse/index', '', true));
        $data['urlWarehouseMappingDownload'] = $this->url->link('account/mapping_warehouse/download', '', true);
        $data['urlWarehouseMappingToDelete'] = $this->url->link('account/mapping_warehouse/toDelete', '', true);

        $this->response->setOutput(load()->view('account/mapping_warehouse/index', $data));
    }

    public function save()
    {

        $platform_id = intval($this->request->post('platform_id', 0));
        $platform_name = trim($this->request->post('platform_name', ''));
        $platform_warehouse_name = trim($this->request->post('platform_warehouse_name', ''));
        $warehouse_id = intval($this->request->post('warehouse_id', 0));

        $json['ret'] = 0;
        $json['msg'] = 'Submit Method Error.';
        if (!is_post()) {
            $json['ret'] = 0;
            $json['msg'] = 'Submit Method Error.';
            goto end;
        }
        if ($platform_id < 1) {
            $json['ret'] = 0;
            $json['msg'] = 'Marketplace can not be left blank.';
            goto end;
        }
        if (utf8_strlen($platform_warehouse_name) < 1) {
            $json['ret'] = 0;
            $json['msg'] = 'Marketplace Warehouse Name can not be left blank.';
            goto end;
        }
        if (utf8_strlen($platform_warehouse_name) > 200) {
            $json['ret'] = 0;
            $json['msg'] = 'Marketplace Warehouse Name can not be more than 200 characters.';
            goto end;
        }
        if ($warehouse_id < 1) {
            $json['ret'] = 0;
            $json['msg'] = 'B2B Warehouse Code can not be left blank.';
            goto end;
        }

        $data = [
            'customer_id' => $this->customer->getId(),
            'platform_id' => $platform_id,
            'platform_warehouse_name' => $platform_warehouse_name,
            'warehouse_id' => $warehouse_id,
        ];

        load()->model('account/mapping_warehouse');


        //判断唯一性，避免重复操作
        $num = $this->model_account_mapping_warehouse->warehouseNameCheck($data);
        if ($num) {
            $json['ret'] = 0;
            $json['msg'] = 'This [' . $platform_name . ' - ' . $platform_warehouse_name . '] has already built the mapping. Please edit in the listing page.';
            goto end;
        }


        $id = $this->model_account_mapping_warehouse->save($data);
        $new_info = $this->model_account_mapping_warehouse->getInfoById($id);
        $this->model_account_mapping_warehouse->log([], $new_info, 'add');
        $json['ret'] = 1;
        $json['msg'] = 'Submitted successfully!';


        end:

        $this->response->json($json);
    }

    public function updates()
    {

        $id = intval($this->request->post('mapping_warehouse_id', 0));
        $platform_id = intval($this->request->post('platform_id', 0));
        $warehouse_id = intval($this->request->post('warehouse_id', 0));
        $platform_name = trim($this->request->post('platform_name', ''));
        $platform_warehouse_name = trim($this->request->post('platform_warehouse_name', ''));

        if (utf8_strlen($platform_warehouse_name) < 1) {
            $json['ret'] = 0;
            $json['msg'] = 'Marketplace Warehouse Name can not be left blank.';
            goto end;
        }
        if (utf8_strlen($platform_warehouse_name) > 200) {
            $json['ret'] = 0;
            $json['msg'] = 'Marketplace Warehouse Name can not be more than 200 characters.';
            goto end;
        }

        load()->model('account/mapping_warehouse');
        $json['ret'] = 0;
        $json['msg'] = 'Submission failed.';
        $info = $this->model_account_mapping_warehouse->getInfoById($id);
        if (!$info) {
            goto end;
        }

        $param = [
            'id' => $id,
            'platform_id' => $platform_id,
            'warehouse_id' => $warehouse_id,
            'platform_warehouse_name' => $platform_warehouse_name
        ];
        $result = $this->model_account_mapping_warehouse->warehouseNameCheck($param);
        if ($result) {
            $json['ret'] = 0;
            $json['msg'] = 'This [' . $platform_name . ' - ' . $platform_warehouse_name . '] has already built the mapping. Please edit in the listing page.';
            goto end;
        }

        $flag = $this->model_account_mapping_warehouse->updates($param);
        if ($flag) {
            $new_info = $this->model_account_mapping_warehouse->getInfoById($id);
            $this->model_account_mapping_warehouse->log($info, $new_info, 'update');

            $json['ret'] = 1;
            $json['msg'] = 'Submitted successfully.';
        } else {
            $json['ret'] = 0;
            $json['msg'] = 'The operation failed. Please try again later.';
        }

        end:

        $this->response->json($json);
    }

    public function checkBuilt()
    {

        $mapping_warehouse_id = intval($this->request->post('mapping_warehouse_id', 0));
        $platform_id = intval($this->request->post('platform_id', 0));
        $platform_warehouse_name = trim($this->request->post('platform_warehouse_name', ''));
        $warehouse_id = intval($this->request->post('warehouse_id', 0));
        $customer_id = $this->customer->getId();

        $param = [
            'id' => $mapping_warehouse_id,
            'platform_id' => $platform_id,
            'platform_warehouse_name' => $platform_warehouse_name,
            'warehouse_id' => $warehouse_id,
            'customer_id' => $customer_id
        ];

        load()->model('account/mapping_warehouse');
        $result = $this->model_account_mapping_warehouse->checkBuilt($param);

        if ($result) {
            $json['ret'] = 0;
            $json['msg'] = '不可以重复创建映射';
        } else {
            $json['ret'] = 1;
            $json['msg'] = 'Success';
        }


        $this->response->json($json);
    }

    /**
     * x值为该Buyer系统中使用了该Platform Warehouse Name的状态为New Order/Check label 的 Dropship销售订单数量
     * @throws Exception
     */
    public function checkOrderNum()
    {

        $json['ret'] = 0;
        $json['msg'] = 'The request is wrong.';
        $id = intval($this->request->get('id', 0));
        if ($id < 1) {
            goto end;
        }
        load()->model('account/mapping_warehouse');
        $info = $this->model_account_mapping_warehouse->getInfoById($id);
        if (!$info) {
            goto end;
        }

        $num = $this->model_account_mapping_warehouse->checkOrderNum($info);
        $json['ret'] = 1;
        $json['msg'] = '';
        $json['num'] = $num;

        end:

        $this->response->json($json);
    }

    /*
     * 下载
     * */
    public function download()
    {
        //加载Model类
        load()->model('account/mapping_warehouse');

        $customer_id = $this->customer->getId();

        $platform_id = intval($this->request->get('platform_id', 0));
        $platform_warehouse_name = trim($this->request->get('platform_warehouse_name', ''));
        $warehouse_id = intval($this->request->get('warehouse_id', 0));
        $sort = trim($this->request->get('sort', ''));
        $order = trim($this->request->get('order', ''));
        $param = [
            'customer_id' => $customer_id,
            'platform_id' => $platform_id,
            'warehouse_id' => $warehouse_id,
            'platform_warehouse_name' => $platform_warehouse_name,
            'sort' => $sort,
            'order' => $order
        ];
        $result = $this->model_account_mapping_warehouse->lists($param, 1);

        $fileName = 'Warehouse Mapping List ' . date('Ymd') . '.csv';

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
            'Platform Warehouse Name',
            'B2B Warehouse Code',
            'Zip Code',
            'State',
            'City',
            'Address',
            'Last Modified'
        ];

        fputcsv($fp, $head);
        foreach ($result as $key => $value) {
            $value = (array)$value;
            $address1 = $value['Address1'] ? $value['Address1'] : '';
            $address2 = $value['Address2'] ? ',' . $value['Address2'] : '';
            $address3 = $value['Address3'] ? ',' . $value['Address3'] : '';
            $line = [
                $value['platform_name'],
                "\t" . $value['platform_warehouse_name'],
                $value['WarehouseCode'],
                "\t" . $value['ZipCode'],
                $value['State'],
                $value['City'],
                "\t" . trim($address1 . $address2 . $address3, ','),
                "\t" . changeOutPutByZone($value['date_modified'], $this->session, 'Y-m-d H:i:s'),
            ];
            fputcsv($fp, $line);
        }
        $output = stream_get_contents($fp);
        fclose($fp);
    }

    /*
     * 删除
     * */
    public function toDelete()
    {

        $id = trim($this->request->post('id'));
        $json = [
            'ret' => 0,
            'msg' => 'Failed! '
        ];
        if ($id) {
            load()->model('account/mapping_warehouse');
            $flag = $this->model_account_mapping_warehouse->toDelete($id);

            if ($flag) {
                $json = [
                    'ret' => 1,
                    'msg' => 'Deleted successfully! '
                ];
            }
        }

        $this->response->json($json);

    }

}
