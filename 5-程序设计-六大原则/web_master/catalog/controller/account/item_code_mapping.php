<?php

/**
 * @property ModelAccountItemCodeMapping $model_account_item_code_mapping
 * @property ModelToolCsv $model_tool_csv
 */
class ControllerAccountItemCodeMapping extends Controller
{
    private $customer_id;
    private $isTrusteeship;
    private $country_id;
    private $isPartner;

    /**
     * @var ModelAccountItemCodeMapping $model
     */
    private $model;

    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->customer_id = $this->customer->getId();
        $this->isPartner = $this->customer->isPartner();
        $this->isTrusteeship = $this->customer->getTrusteeship();
        $this->country_id = $this->customer->getCountryId();
        //if (empty($this->customer_id) || !$this->isPartner) {
        //    $this->response->redirect($this->url->link('account/login', '', true));
        //}

        $this->load->model('account/item_code_mapping');
        $this->model = $this->model_account_item_code_mapping;

        $this->load->language('account/item_code_mapping');
    }

    /**
     * [index description] 三个li标签的内容
     * @return string
     */
    public function index()
    {
        $this->document->setTitle($this->language->get('heading_title'));
        $data['breadcrumbs'] = [];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_item_code_mapping'),
            'href' => $this->url->link('account/item_code_mapping', '', true)
        ];

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        $this->response->setOutput($this->load->view('account/item_code_management/list', $data));

    }

    public function newList()
    {
        //处理request的请求
        //if (isset($this->request->get['scroll_flag']) && $this->request->get['scroll_flag'] !='') {
        //    $data['scroll_flag'] = 1;
        //}else{
        //    $data['scroll_flag'] = 0;
        //}
        $lanArr = $this->load->language('account/item_code_mapping');
        if (isset($this->request->get['search_flag']) && $this->request->get['search_flag'] != '') {
            $data['search_result'] = $lanArr['text_valid_no_search'];
            $data['search_flag'] = 1;
        } else {
            $data['search_result'] = $lanArr['text_valid_no_data'];
            $data['search_flag'] = 0;
        }

        $param = ['filter_store', 'filter_platform', 'filter_url', 'filter_sku'];
        $param_map = ['id', 'platform', 'asin', 'sku'];
        $condition = [];
        foreach ($param as $key => $value) {
            if (isset($this->request->get[$value])) {
                $data[$value] = $this->request->get[$value];
                $condition[$param_map[$key]] = trim($this->request->get[$value]);
            } else {
                $data[$value] = '';
            }
        }
        $data['store_list'] = $this->model->getAmazonStore($this->customer_id);
        $data['platform_list'] = $this->model->getPlatform($this->customer_id);
        $new_list = $this->model->getUnupdateAmazonProduct($this->customer_id, $condition);
        $data['new_list'] = $new_list;
        $this->response->setOutput($this->load->view('account/item_code_management/new_list', $data));
    }

    /**
     * [batchUpdate description] 针对于sku的批量或者整体操作
     * @return string
     */
    public function batchUpdate()
    {
        // 1  submit | batch submit
        // 2  invalid | batch invalid
        // 3  valid | batch valid
        $posts = $this->request->post;
        $lanArr = $this->load->language('account/item_code_mapping');
        if (!isset($this->request->post['type']) || !in_array($this->request->post['type'], [1, 2, 3])) {
            $error[] = $lanArr['error_try_again'];
        }
        if (empty($error)) {
            if ($posts['type'] == 1) {
                $this->model->setPlatformSkuBind($posts['data'], $this->customer_id);
                $responseData = [
                    'code' => 1,
                    'msg' => $lanArr['text_bind_success'],
                ];

            } elseif ($posts['type'] == 2) {
                $key = $this->model->setPlatformSkuInvalid($posts['ids'], $this->customer_id);
                if ($key == 0) {
                    $responseData = [
                        'code' => 1,
                        'msg' => $lanArr['text_single_invalid_success'],
                    ];
                } else {
                    $responseData = [
                        'code' => 1,
                        'msg' => $lanArr['text_invalid_success'],
                    ];
                }

            } elseif ($posts['type'] == 3) {
                $this->model->setPlatformSkuValid($posts['ids'], $this->customer_id);
                $responseData = [
                    'code' => 1,
                    'msg' => $lanArr['text_valid_success'],
                ];
            }


        } else {
            if ($posts['type'] == 1) {
                $responseData = [
                    'code' => 0,
                    'msg' => $lanArr['text_bind_failed'],
                ];

            } elseif ($posts['type'] == 2) {
                $responseData = [
                    'code' => 0,
                    'msg' => $lanArr['text_invalid_failed'],
                ];
            } elseif ($posts['type'] == 3) {
                $responseData = [
                    'code' => 0,
                    'msg' => $lanArr['text_valid_failed'],
                ];
            }
        }

        $this->response->returnJson($responseData);

    }

    public function getB2bCodeBySearch()
    {
        if (isset($this->request->get['q'])) {
            $sku = $this->request->get['q'];
        } else {
            $sku = '';
        }
        if (isset($this->request->get['page'])) {
            $page = $this->request->get['page'];
        } else {
            $page = 1;
        }

        $map = [
            ['sku', 'like', "%{$sku}%"],
        ];
        $builder = $this->orm->table('oc_product')->where($map)->whereNotNull('sku')->select('product_id as id', 'sku')->groupBy('sku');
        $results['total_count'] = $builder->count('*');
        $results['items'] = $builder->forPage($page, 10)
            ->orderBy('product_id', 'asc')
            ->get();
        $results['items'] = obj2array($results['items']);
        $this->response->returnJson($results);


    }

    /**
     * [getAllcodeBySearch description]
     * @return string
     */
    public function getAllcodeBySearch()
    {
        if (isset($this->request->get['sku'])) {
            $sku = $this->request->get['sku'];
        } else {
            $sku = '';
        }
        $all_sku = [];
        if ($sku) {
            $map = [
                ['sku', 'like', "%{$sku}%"],
            ];
            $res = $this->orm->table('tb_yzc_amazon_product')
                ->where($map)
                ->whereIN('storeId', $this->model->getBuyerStoreId($this->customer_id))
                ->limit(3)->select('sku as id', 'sku')->get();

            $res_b2b = $this->orm->table('oc_product')
                ->where($map)
                ->select('product_id as id', 'sku')
                ->groupBy('sku')->limit(3)->select('sku as id', 'sku')->get();
            foreach ($res as $key => $value) {
                $all_sku[] = $value;
            }

            foreach ($res_b2b as $key => $value) {
                $all_sku[] = $value;
            }

        } else {
            $res = $this->orm->table('tb_yzc_amazon_product')
                ->whereIN('storeId', $this->model->getBuyerStoreId($this->customer_id))
                ->limit(3)->select('sku as id', 'sku')->get();

            $res_b2b = $this->orm->table('oc_product')
                ->select('product_id as id', 'sku')
                ->whereNotNull('sku')
                ->groupBy('sku')->limit(3)->select('sku as id', 'sku')->get();

            foreach ($res as $key => $value) {
                $all_sku[] = $value;
            }

            foreach ($res_b2b as $key => $value) {
                $all_sku[] = $value;
            }
        }
        //$json = obj2array($res);
        $this->response->returnJson($all_sku);

    }


    public function getPlatformCodeBySearch()
    {

        if (isset($this->request->get['sku'])) {
            $sku = $this->request->get['sku'];
        } else {
            $sku = '';
        }
        if ($sku) {
            $map = [
                ['sku', 'like', "%{$sku}%"],
            ];
            $res = $this->orm->table('tb_yzc_amazon_product')
                ->where($map)
                ->whereIN('storeId', $this->model->getBuyerStoreId($this->customer_id))
                ->limit(5)->select('id', 'sku')->get();
            $json = obj2array($res);
            $this->response->returnJson($json);
        } else {
            $res = $this->orm->table('tb_yzc_amazon_product')
                ->whereIN('storeId', $this->model->getBuyerStoreId($this->customer_id))
                ->limit(5)->select('id', 'sku')->get();
            $json = obj2array($res);
            $this->response->returnJson($json);
        }


    }

    public function historyList()
    {
        $data['store_list'] = $this->model->getAmazonStore($this->customer_id);
        $data['platform_list'] = $this->model->getPlatform($this->customer_id);
        $data['sales_person_list'] = $this->model->getSalesPerson($this->customer_id);
        $data['approval_list'] = $this->model->getApprovalStatus();
        $lanArr = $this->load->language('account/item_code_mapping');
        if (isset($this->request->get['search_flag']) && $this->request->get['search_flag'] != '') {
            $data['search_result'] = $lanArr['text_history_no_search'];
            $data['search_flag'] = 1;
        } else {
            $data['search_result'] = $lanArr['text_history_no_data'];
            $data['search_flag'] = 0;
        }

        if (isset($this->request->get['sort']) && $this->request->get['sort'] != '') {
            $data['order_sort'] = $this->request->get['sort'];
        } else {
            $data['order_sort'] = '';
        }
        if (isset($this->request->get['sort_type']) && $this->request->get['sort_type'] != '') {
            $data['sort_type'] = $this->request->get['sort_type'];
        } else {
            $data['sort_type'] = '';
        }
        $url = '';
        $param = ['filter_store_history', 'filter_platform_history', 'filter_sales_person', 'filter_sku_history', 'filter_approval_status'];
        $param_map = ['store_id', 'platform', 'salesperson_id', 'sku', 'approval_status'];
        $condition = [];
        foreach ($param as $key => $value) {
            if (isset($this->request->get[$value])) {
                $data[$value] = $this->request->get[$value];
                $condition[$param_map[$key]] = trim($this->request->get[$value]);
                $url .= '&' . $value . '=' . $this->request->get[$value];
            } else {
                $data[$value] = '';
            }
        }
        //查询
        $page = $this->request->get['page'] ?? 1;
        $perPage = get_value_or_default($this->request->request, 'page_limit', 20);
        $result = $this->model->getMappingHistoryRecord($condition, $page, $perPage, $data['order_sort'], $data['sort_type']);
        $pagination = new Pagination();
        $pagination->total = $total = $result['total'];
        $pagination->page = $page;
        $pagination->limit = $perPage;
        $pagination->renderScript = false;
        $pagination->url = $this->url->link('account/item_code_mapping/historyList' . $url, '&page={page}', true);
        $data['pagination'] = $pagination->render();
        $data['results'] = sprintf($this->language->get('text_pagination'), ($total) ? (($page - 1) * $perPage) + 1 : 0, ((($page - 1) * $perPage) > ($total - $perPage)) ? $total : ((($page - 1) * $perPage) + $perPage), $total, ceil($total / $perPage));
        $data['history_list'] = obj2array($result['data']);
        $data['reject_info'] = json_encode($result['reject_info']);
        $data['page'] = $page;
        $data['page_limit'] = $perPage;
        $this->response->setOutput($this->load->view('account/item_code_management/history_list', $data));
    }

    /**
     * [historyListDownload description]
     * @return void
     * @throws Exception
     */
    public function historyListDownload()
    {

        if (isset($this->request->get['sort']) && $this->request->get['sort'] != '') {
            $data['order_sort'] = $this->request->get['sort'];
        } else {
            $data['order_sort'] = '';
        }
        if (isset($this->request->get['sort_type']) && $this->request->get['sort_type'] != '') {
            $data['sort_type'] = $this->request->get['sort_type'];
        } else {
            $data['sort_type'] = '';
        }
        $url = '';
        $param = ['filter_store_history', 'filter_platform_history', 'filter_sales_person', 'filter_sku_history', 'filter_approval_status'];
        $param_map = ['store_id', 'platform', 'salesperson_id', 'sku', 'approval_status'];
        $condition = [];
        foreach ($param as $key => $value) {
            if (isset($this->request->get[$value])) {
                $data[$value] = $this->request->get[$value];
                $condition[$param_map[$key]] = trim($this->request->get[$value]);
                $url .= '&' . $value . '=' . $this->request->get[$value];
            } else {
                $data[$value] = '';
            }
        }
        $data['platform_list'] = $this->model->getPlatform($this->customer_id);
        $data['approval_list'] = $this->model->getApprovalStatus();

        $result = $this->model->getMappingHistoryDownloadRecord($condition, $data['order_sort'], $data['sort_type']);
        $this->load->model('tool/csv');
        $fileName = 'Item Code Mapping History' . date('Ymd') . '.csv';
        $this->model_tool_csv->getMappingHistorySkuInfo($fileName, $result, $data['platform_list'], $data['approval_list']);


    }

    public function getHistoryMappingDetails()
    {
        $id = $this->request->get['id'];
        $res = $this->model->getLastRejectInfo($id);
        $data['details_info'] = $res;
        $data['id'] = $id;
        $this->response->setOutput($this->load->view('account/item_code_management/history_details_info', $data));
    }

    public function getTimeLineInfoByMappingHistory()
    {
        $id = $this->request->get['id'];
        $res = $this->model->getTimeLineById($id);
        $data['time_line'] = $res;
        $this->response->setOutput($this->load->view('account/item_code_management/time_line', $data));
    }

    public function invalidList()
    {
        $lanArr = $this->load->language('account/item_code_mapping');
        if (isset($this->request->get['search_flag']) && $this->request->get['search_flag'] != '') {
            $data['search_result'] = $lanArr['text_invalid_no_search'];
        } else {
            $data['search_result'] = $lanArr['text_invalid_no_data'];
        }

        $param = ['filter_store_invalid', 'filter_platform_invalid', 'filter_url_invalid', 'filter_sku_invalid'];
        $param_map = ['id', 'platform', 'asin', 'sku'];
        $condition = [];
        foreach ($param as $key => $value) {
            if (isset($this->request->get[$value])) {
                $data[$value] = $this->request->get[$value];
                $condition[$param_map[$key]] = trim($this->request->get[$value]);
            } else {
                $data[$value] = '';
            }
        }
        if (isset($this->request->get['sort']) && $this->request->get['sort'] != '') {
            $data['invalid_sort'] = $this->request->get['sort'];
        } else {
            $data['invalid_sort'] = '';
        }

        $data['store_list'] = $this->model->getAmazonStore($this->customer_id);
        $data['platform_list'] = $this->model->getPlatform($this->customer_id);
        $invalid_list = $this->model->getInvalidAmazonProduct($this->customer_id, $condition, $data['invalid_sort']);
        $data['invalid_list'] = $invalid_list;
        //
        $this->response->setOutput($this->load->view('account/item_code_management/invalid_list', $data));
    }

    /**
     * [mappingHistoryResubmit description] reject 后重复提交
     * @return string
     */
    public function mappingHistoryResubmit()
    {
        $lanArr = $this->load->language('account/item_code_mapping');
        $post = $this->request->post;
        $files = $this->request->files;
        $this->model->setMappingSkuPending($post, $files, 2);
        $responseData = [
            'code' => 1,
            'msg' => $lanArr['text_bind_success'],
        ];
        $this->response->returnJson($responseData);

    }

    public function mappingHistoryEdit()
    {
        $lanArr = $this->load->language('account/item_code_mapping');
        $post = $this->request->post;
        $this->model->setMappingSkuPending($post, [], 1);
        $responseData = [
            'code' => 1,
            'msg' => $lanArr['text_bind_success'],
        ];
        $this->response->returnJson($responseData);

    }


    /**
     * [getMappingSkuHistoryIsValid description] 获取是否是有效的sku提交
     * @return string
     */
    public function getMappingSkuHistoryIsValid()
    {
        $post = $this->request->post;
        $responseData = $this->model->verifyMappingSku($post);
        $this->response->returnJson($responseData);
    }
}