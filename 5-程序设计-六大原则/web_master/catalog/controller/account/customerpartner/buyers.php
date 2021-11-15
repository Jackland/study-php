<?php

use App\Helper\CountryHelper;

/**
 * Class ControllerAccountCustomerpartnerBuyers
 *
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 * @property ModelAccountCustomerpartnerBuyerGroup $model_account_customerpartner_BuyerGroup
 * @property ModelCustomerpartnerBuyers $model_customerpartner_buyers
 * @property ModelCustomerPartnerDelicacyManagement $model_customerpartner_DelicacyManagement
 * @deprecated 废弃，使用 Controller/Account/Customerpartner/BuyerManagement/List Lester.You 2019-11-13 14:37:32
 */
class ControllerAccountCustomerpartnerBuyers extends Controller
{
    private $error = array();
    private $customer_id = null;
    private $isPartner = false;

    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->customer_id = $this->customer->getId();
        $this->load->model('account/customerpartner');
        $this->isPartner = $this->model_account_customerpartner->chkIsPartner();
        if (empty($this->customer_id) || !$this->isPartner) {
            $this->response->redirect($this->url->link('account/login', '', true));
        }
    }

    public function index()
    {
        $data = $this->load->language('account/customerpartner/buyers');
        $this->document->addScript("catalog/view/javascript/layer/layer.js");

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('customerpartner/buyers');

        $this->getList($data);
    }

    protected function getList($data)
    {

        $data['chkIsPartner'] = $this->isPartner;

        $url = "";

        if ($this->isPartner) {
            $data['breadcrumbs']   = array();
            $data['breadcrumbs'][] = array(
                'text' => $data['text_home'],
                'href' => $this->url->link('common/home', '', true)
            );
            $data['breadcrumbs'][] = array(
                'text' => $data['heading_title_seller_center'],
                'href' => $this->url->link('customerpartner/seller_center/index', '', true)
            );
            $data['breadcrumbs'][] = array(
                'text' => $data['heading_title_buyer_management'],
                'href' => "javascript:void(0);"
            );
            $data['breadcrumbs'][] = array(
                'text' => $data['heading_title'],
                'href' => $this->url->link('account/customerpartner/buyers', $url, true)
            );
        } else {
            $data['breadcrumbs']   = array();
            $data['breadcrumbs'][] = array(
                'text' => $data['text_home'],
                'href' => $this->url->link('common/home', '', true)
            );
            $data['breadcrumbs'][] = array(
                'text' => $data['heading_title'],
                'href' => $this->url->link('account/customerpartner/buyers', $url, true)
            );
        }


        // 主要页面内容加载
        /* 获取排序字段和方式 */
        if (isset($this->request->get['sort'])) {
            $sort = $this->request->get['sort'];
        } else {
            $sort = 'cc.`nickname`';
        }

        if (isset($this->request->get['order'])) {
            $order = $this->request->get['order'];
        } else {
            $order = 'ASC';
        }

        /* 分页 */
        // 第几页
        if (isset($this->request->get['page'])) {
            $page_num = $this->request->get['page'];
        } else {
            $page_num = 1;
        }
        $data['page_num'] = $page_num;
        // 每页显示数目
        if (isset($this->request->get['page_limit'])) {
            $page_limit = $this->request->get['page_limit'];
        } else {
            $page_limit = 20;
        }
        $data['page_limit'] = $page_limit;

        /* 过滤条件 */
        // (buyer name)
        if (isset($this->request->get['filter_buyer_name'])) {
            $filter_buyer_name = $this->request->get['filter_buyer_name'];
            $url .= "&filter_buyer_name=" . $filter_buyer_name;
        } else {
            $filter_buyer_name = "";
        }
        $data['filter_buyer_name'] = $filter_buyer_name;

        if (isset($this->request->get['filter_status'])) {
            $filter_status = $this->request->get['filter_status'];
            $url .= "&filter_status=" . $filter_status;
        } else {
            $filter_status = "";
        }
        $data['filter_status'] = $filter_status;

        isset_and_not_empty($this->request->get, 'filter_buyer_group_id') && $url .= "&filter_buyer_group_id=" . $this->request->get['filter_buyer_group_id'];
        isset_and_not_empty($this->request->get, 'filter_date_added_from') && $url .= "&filter_date_added_from=" . $this->request->get['filter_date_added_from'];
        isset_and_not_empty($this->request->get, 'filter_date_added_to') && $url .= "&filter_date_added_to=" . $this->request->get['filter_date_added_to'];
        $data['filter_buyer_group_id'] = get_value_or_default($this->request->get, 'filter_buyer_group_id', 0);
        $data['filter_date_added_from'] = get_value_or_default($this->request->get, 'filter_date_added_from', '');
        $data['filter_date_added_to'] = get_value_or_default($this->request->get, 'filter_date_added_to', '');

        $filter_data = array(
            "sort" => $sort,
            "order" => $order,
//            "filter_buyer_email" => $filter_buyer_email,
            "filter_status" => $filter_status,
            "filter_buyer_name" => $filter_buyer_name,
            "filter_buyer_group_id" => $data['filter_buyer_group_id'],
            "filter_date_added_from" => $data['filter_date_added_from'],
            "filter_date_added_to" => $data['filter_date_added_to'],
            "page_num" => $page_num,
            "page_limit" => $page_limit
        );
        $this->load->model('customerpartner/buyers');
        $customer_id = $this->customer->getId();
        $data['customer_id'] = $customer_id;
        $buyers = $this->model_customerpartner_buyers->getBuyersByCustomerId($customer_id, $filter_data);
        $customerTotal = $this->model_customerpartner_buyers->getBuyersTotalByCustomerId($customer_id, $filter_data);
        //用于批量发送站内信搜索条件
        $data['groupContactFilter'] = [
            "sort" => $sort,
            "order" => $order,
            "filter_status" => 1,
            "filter_buyer_name" => $filter_buyer_name,
            "filter_buyer_group_id" => $data['filter_buyer_group_id'],
            "filter_date_added_from" => $data['filter_date_added_from'],
            "filter_date_added_to" => $data['filter_date_added_to'],
        ];
        $data['groupContactAction'] = $this->url->link('account/customerpartner/buyers/getGroupContactIds',  '', true);
        $data['customer_total'] = $customerTotal;
        $total_pages = ceil($customerTotal / $page_limit);
        $data['total_pages'] = $total_pages;
        $num = (($page_num - 1) * $page_limit) + 1;
        $tableData = array();
        foreach ($buyers->rows as $buyer) {
            $tableData[$buyer['id']] = array(
                "num" => $num++,
                "id" => $buyer['id'],
                "buyer_id" => $buyer['customer_id'],
                "buyer_name" => $buyer['buyer_name'],
                "buyer_email" => $buyer['buyer_email'],
                "buy_status" => $buyer['buy_status'] == null ? 0 : $buyer['buy_status'],
                "price_status" => $buyer['price_status'] == null ? 0 : $buyer['price_status'],
                "discount" => $buyer['discount'],
                "remark" => $buyer['remark'],
                "add_time" => $buyer['add_time'],
                "coop_status_seller" => $buyer['seller_control_status'] == null ? 0 : $buyer['seller_control_status'],
                "coop_status_buyer" => $buyer['buyer_control_status'] == null ? 0 : $buyer['buyer_control_status'],
                'buyer_group_name' => $buyer['buyer_group_name'],
                'buyer_group_id' => $buyer['buyer_group_id'],
                'is_default' => $buyer['is_default'],
                'is_home_pickup' => in_array($buyer['customer_group_id'], COLLECTION_FROM_DOMICILE) ? true : false,
                'send_mail_link' => $this->url->link('message/seller/addMessage', '&receiver_id=' . $buyer['customer_id'], true)
            );
        }
        // 排序字段
        $data['sort'] = $sort;
        // 排序
        $data['order'] = $order;
        if ($order == "ASC") {
            $order = "DESC";
        } else {
            $order = "ASC";
        }

        // 获取 buyer group
        $this->load->model('account/customerpartner/BuyerGroup');
        $data['buyer_groups'] = $this->model_account_customerpartner_BuyerGroup->getGroupsForSelect($this->customer_id);

        $data['url_delicacy_management'] = $this->url->link('account/customerpartner/delicacymanagement', '', true);

        $data['tableData'] = $tableData;
        /* 排序 */
        $url .= "&page_limit=" . $page_limit;
        $data['sort_buyer_name'] = $this->url->link('account/customerpartner/buyers',  '&customer_id=' . $customer_id . '&sort=cc.`nickname`' . '&order=' . $order . "&page=" . $page_num . $url, true);
        $data['sort_buy_status'] = $this->url->link('account/customerpartner/buyers',  '&customer_id=' . $customer_id . '&sort=bts.`buy_status`' . '&order=' . $order . "&page=" . $page_num . $url, true);
        $data['sort_price_status'] = $this->url->link('account/customerpartner/buyers',  '&customer_id=' . $customer_id . '&sort=bts.`price_status`' . '&order=' . $order . "&page=" . $page_num . $url, true);
        $data['sort_coop_status_seller'] = $this->url->link('account/customerpartner/buyers',  '&customer_id=' . $customer_id . '&sort=bts.`seller_control_status`' . '&order=' . $order . "&page=" . $page_num . $url, true);
        $data['sort_coop_status_buyer'] = $this->url->link('account/customerpartner/buyers',  '&customer_id=' . $customer_id . '&sort=bts.`buyer_control_status`' . '&order=' . $order . "&page=" . $page_num . $url, true);

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        $url .= "&sort=" . $sort;
        if ($order == "DESC") {
            $url .= "&order=" . "ASC";
        } else {
            $url .= "&order=" . "DESC";
        }
        $pagination = new Pagination();
        $pagination->total = $customerTotal;
        $pagination->page = $page_num;
        $pagination->limit = $page_limit;
        $pagination->url = $this->url->link('account/customerpartner/buyers',  $url . '&page={page}', true);
        $data['pagination'] = $pagination->render();
        $data['results'] = sprintf($this->language->get('text_pagination'), ($customerTotal) ? (($page_num - 1) * $page_limit) + 1 : 0, ((($page_num - 1) * $page_limit) > ($customerTotal - $page_limit)) ? $customerTotal : ((($page_num - 1) * $page_limit) + $page_limit), $customerTotal, $total_pages);

        $data['separate_view'] = false;

        $data['separate_column_left'] = '';

        //seller发送buyer站内信
        $data['contact_buyer'] = $this->load->controller('customerpartner/contact_buyer');

        if ($this->config->get('marketplace_separate_view') && isset($this->session->data['marketplace_separate_view']) && $this->session->data['marketplace_separate_view'] == 'separate') {
            $data['separate_view'] = true;
            $data['column_left'] = '';
            $data['column_right'] = '';
            $data['content_top'] = '';
            $data['content_bottom'] = '';
            $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');

            $data['footer'] = $this->load->controller('account/customerpartner/footer');
            $data['header'] = $this->load->controller('account/customerpartner/header');
        }

        $this->response->setOutput($this->load->view('account/customerpartner/buyers', $data));
    }

    public function getGroupContactIds()
    {
        $groupContactFilter = $this->request->post['groupContactFilter'];
        $this->load->model('customerpartner/buyers');
        $buyers = $this->model_customerpartner_buyers->getBuyersByCustomerId($this->customer->getId(), $groupContactFilter);
        $json['buyerIds'] = array_column($buyers->rows,'customer_id');
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    /**
     * 逻辑：
     * A.如果解除关联关系
     *   1.需要删除 oc_delicacy_management 记录
     *   2.删除 buyer_group_link 记录
     * B.未解除关联关系
     *   1.如果添加分组
     */
    public function update()
    {
        $this->checkPOST();
        trim_strings($this->request->post);
        // 加载Model
        $this->load->model('customerpartner/buyers');

        if (!isset_and_not_empty($this->request->post, 'id')) {
            $response = [
                'error' => 5,
                'msg' => $this->language->get('error_common'),
            ];
            $this->returnJson($response);
        }

        //if (!isset_and_not_empty($this->request->post, 'discount')
        //    || $this->request->post['discount'] < 0
        //    || $this->request->post['discount'] > 1
        //) {
        //    $response = [
        //        'error' => 1,
        //        'msg' => $this->language->get('error_save_discount'),
        //    ];
        //    $this->returnJson($response);
        //}

        $coop_status_seller = $this->request->post["coop_status_seller"] ?? 1;
        $update_data = array(
            "id" => $this->request->post["id"],
            "seller_control_status" => $coop_status_seller,
            //"discount" => $this->request->post["discount"],
            "discount" => 1,
            "remark" => get_value_or_default($this->request->post, 'remark', ''),
        );
        $buyerInfo = $this->model_customerpartner_buyers->getSingle($update_data['id']);

        if (empty($buyerInfo)) {
            $response = [
                'error' => 5,
                'msg' => $this->language->get('error_common'),
            ];
            $this->returnJson($response);
        }

        $this->load->model('account/customerpartner/BuyerGroup');
        // 14086 库存订阅列表中的产品上下架提醒
        // 判断是否更改buyer 的状态
        $res = $this->model_customerpartner_buyers->verifyBuyerStatus($this->request->post['id'],$coop_status_seller);
        if($res){
            $buyer_id = $buyerInfo->buyer_id;
            $seller_id = $this->customer->getId();
            //获取buyer_id 和 seller_id 需要知道关联的产品
            $this->model_customerpartner_buyers->sendProductionInfoToBuyer($buyer_id,$seller_id,$coop_status_seller);
        }

        // 更改了状态则发站内信
        /**
         * 解除关系
         * 1.删除 delicacy_management
         * 2.移除分组
         *
         * 未解除关系
         * 1.如果有分组 则更改
         * 2.如果无分组 则删除
         */
        if ($update_data['seller_control_status'] == 0) {   //解除关系
            // 1.删除 delicacy_management
            $this->load->model('customerpartner/DelicacyManagement');
            $this->model_customerpartner_DelicacyManagement->batchRemoveByBuyer([$buyerInfo->buyer_id], $this->customer_id);

            // 2.移除分组
            $this->model_account_customerpartner_BuyerGroup->linkDeleteByBuyer($this->customer_id, $buyerInfo->buyer_id);
        }else{  // 未解除关系
            // 1.如果有分组 则更改
            if (isset_and_not_empty($this->request->post, 'buyer_group_id')) {
                // 验证分组是否存在
                if (!$this->model_account_customerpartner_BuyerGroup->checkGroupIsExist(
                    $this->customer_id,
                    $this->request->post['buyer_group_id'])
                ) {
                    $response = [
                        'error' => 5,
                        'msg' => $this->language->get('error_common'),
                    ];
                    $this->returnJson($response);
                }
                // 更改分组
                $this->model_account_customerpartner_BuyerGroup->updateGroupLinkByBuyer(
                    $this->customer_id,
                    $buyerInfo->buyer_id,
                    $this->request->post['buyer_group_id']
                );
            }else{  //2.如果无分组 则删除
                $this->model_account_customerpartner_BuyerGroup->linkDeleteByBuyer($this->customer_id, $buyerInfo->buyer_id);
            }
        }

        $this->model_customerpartner_buyers->updateBuyerInfo($update_data);
        $response = [
            'error' => 0,
            'msg' => 'Success'
        ];
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($response));
    }

    /**
     * @var int type 类型 1-> set discount; 2-> set status:enable ; 3-> set status:disable
     * @var int ids  批量操作ID 的字符串 如： 2_3_4
     * @var int discount 折扣 (只有在type=1的情况下才有)
     */
    public function batchUpdate()
    {
        $error = [];
        $customer_id = $this->customer->getId();

        $lanArr = $this->load->language('account/customerpartner/buyers');
        isset($this->request->post) && trim_strings($this->request->post);
        isset($this->request->post['ids']) && $this->request->post['ids'] = trim($this->request->post['ids'], '_');
        if (!isset($this->request->post['ids']) || empty($this->request->post['ids'])) {
            $error[] = $lanArr['error_choose_checkboxes'];
        }

        if (!isset($this->request->post['type']) || !in_array($this->request->post['type'], [1, 2, 3])) {
            $error[] = $lanArr['error_try_again'];
        }

        if ($this->request->post['type'] == 1) {
            if (!isset($this->request->post['discount']) || empty($this->request->post['discount']) || (float)$this->request->post['discount'] < 0 || (float)$this->request->post['discount'] > 1) {
                $error[] = $lanArr['error_save_discount'];
            }
        }

        if (empty($error)) {
            $this->load->model('customerpartner/buyers');
            $update_data = [
                'seller_id' => $customer_id,
                'idArr' => explode('_', $this->request->post['ids'])
            ];
            if($this->request->post['type'] == 2){
                $coop_status_seller = 1;
            }elseif ($this->request->post['type'] == 3){
                $coop_status_seller = 0;
            }
            // 14086 库存订阅列表中的产品上下架提醒
            // 判断是否更改buyer 的状态
            if(isset($coop_status_seller)){
                foreach($update_data['idArr'] as $key => $value){
                    $res = $this->model_customerpartner_buyers->verifyBuyerStatus($value,$coop_status_seller);
                    if($res){
                        $buyer_id = $res;
                        //获取buyer_id 和 seller_id 需要知道关联的产品
                        $this->model_customerpartner_buyers->sendProductionInfoToBuyer($buyer_id,$customer_id,$coop_status_seller);
                    }

                }

            }
            if ($this->request->post['type'] == 1) {
                $update_data['update'] = [
                    'discount' => $this->request->post['discount']
                ];
            } elseif ($this->request->post['type'] == 3) {
                $update_data['update'] = [
                    'seller_control_status' => 0
                ];
                // 同时需要删除delicacy_management表 & 从分组中移除
                $buyerIDArr = $this->model_customerpartner_buyers->getBuyerID($update_data['idArr']);
                $this->load->model('customerpartner/DelicacyManagement');
                if (!empty($buyerIDArr)) {
                    $this->load->model('account/customerpartner/BuyerGroup');
                    $this->model_account_customerpartner_BuyerGroup->batchDeleteLink($this->customer_id, $buyerIDArr);
                    $this->model_customerpartner_DelicacyManagement->batchRemoveByBuyer($buyerIDArr, $customer_id);
                }
            } else {
                $update_data['update'] = [
                    'seller_control_status' => 1
                ];
            }
            $this->model_customerpartner_buyers->batchUpdate($update_data);
            $responseData = [
                'code' => 1,
                'msg' => 'Success!'
            ];
        } else {
            $responseData = [
                'code' => 0,
                'msg' => $error[0]
            ];
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($responseData));
    }

    /**
     * 逻辑：
     * 1.如果移除分组
     *      从 oc_customerpartner_buyer_group_link 中删除
     * 2.如果更改分组
     *    a.原来无分组
     *          如果该分组已和 产品分组 关联，则需要删除之前参与的精细化管理
     *          如果 未关联，则加入分组
     *    b.原来有分组
     *          更改分组即可
     *
     * @var int ids  批量操作ID 的字符串 如： 2_3_4
     */
    public function batchSetGroup()
    {
        $this->checkPOST();
        trim_strings($this->request->post);

        $this->load->model('customerpartner/buyers');
        isset($this->request->post['ids']) && $this->request->post['ids'] = trim($this->request->post['ids'], '_');
        if (!isset($this->request->post['ids']) || empty($this->request->post['ids'])) {
            $response = [
                'error' => 5,
                'msg' => $this->language->get('error_common'),
            ];
            $this->returnJson($response);
        }
        $buyerIDArr = $this->model_customerpartner_buyers->getBuyerID(explode('_', $this->request->post['ids']));
        $buyer_group_id = get_value_or_default($this->request->post, 'buyer_group_id', 0);

        $this->load->model('account/customerpartner/BuyerGroup');

        if ($buyer_group_id == 0) {
            if (!empty($buyerIDArr)) {
                $this->model_account_customerpartner_BuyerGroup->batchDeleteLink($this->customer_id, $buyerIDArr);
            }
        }else{
            $is_delete_dm = $this->model_account_customerpartner_BuyerGroup->checkIsDelicacyManagementGroupByBGID($this->customer_id, $buyer_group_id);
            foreach ($buyerIDArr as $buyerID) {
                $this->model_account_customerpartner_BuyerGroup->updateGroupLinkByBuyer($this->customer_id, $buyerID, $buyer_group_id,$is_delete_dm);
            }
        }
        $response = [
            'error' => 0,
            'msg' => 'Success'
        ];
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($response));

    }

    public function download()
    {
        $filter_data = [];
        $filter_data['sort'] = $this->request->get['sort'] ?? 'cc.`nickname`';
        $filter_data['order'] = $this->request->get['order'] ?? 'ASC';
        isset_and_not_empty($this->request->get, 'filter_buyer_name') && $filter_data['filter_buyer_name'] = $this->request->get['filter_buyer_name'];
        isset_and_not_empty($this->request->get, 'filter_status') && $filter_data['filter_status'] = $this->request->get['filter_status'];
        isset_and_not_empty($this->request->get, 'filter_buyer_group_id') && $filter_data['filter_buyer_group_id'] = $this->request->get['filter_buyer_group_id'];
        isset_and_not_empty($this->request->get, 'filter_date_added_from') && $filter_data['filter_date_added_from'] = $this->request->get['filter_date_added_from'];
        isset_and_not_empty($this->request->get, 'filter_date_added_to') && $filter_data['filter_date_added_to'] = $this->request->get['filter_date_added_to'];

        $this->load->model('customerpartner/buyers');
        $results = $this->model_customerpartner_buyers->getBuyersByCustomerId($this->customer_id, $filter_data);

        //12591 B2B记录各国别用户的操作时间
        $country = session('country', 'USA');
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        $time = dateFormat($fromZone, $toZone, date("YmdHis",time()),'Ymd');
        //12591 end
        $fileName = 'Buyers-' . $time . '.csv';
        $header = [
            'Name',
            'Buyer Number',
            'Buyer Group',
            //'Discount',
            'Remark',
            'Establish Contact Date',
            'Status',
        ];
        $content = array();
        foreach ($results->rows as $key =>$result) {
            $content[$key] = [
                html_entity_decode($result['nickname']),
                $result['user_number'],
                html_entity_decode($result['buyer_group_name']),
                //$result['discount'],
                html_entity_decode($result['remark']),
                $result['add_time'],
                $result['seller_control_status']?'Active':'Inavtive',
            ];
        }
        //12591 B2B记录各国别用户的操作时间
        outputCsv($fileName,$header,$content,$this->session);
        //12591 end
    }

    private function checkPOST()
    {
        if (!request()->isMethod('POST')) {
            $response = [
                'error' => 5,
                'msg' => $this->language->get('error_common'),
            ];
            $this->returnJson($response);
        }
    }

    private function returnJson($response)
    {
        $this->response->returnJson($response);
    }
}
