<?php

use App\Catalog\Controllers\AuthBuyerController;
use App\Components\Storage\StorageCloud;
use App\Repositories\Message\TicketRepository;
use App\Services\Message\TicketService;

/**
 * Ticket消息类
 *   Ticket消息Buyer和Selle分离，部分代码搬迁到这边，还有部分继续是使用公用部分
 *
 * Class ControllerAccountMessageCenterTicket
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 * @property ModelAccountTicket $model_account_ticket
 */
class ControllerAccountMessageCenterTicket extends AuthBuyerController
{
    private $customerId;
    private $modelTicket;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->customerId = $this->customer->getId();
        /** @var ModelAccountTicket $modelTicket */
        $this->modelTicket = load()->model('account/ticket');
        $this->load->language('account/message_center/ticket');
    }

    public function index()
    {
        $data['submit_ticket_for'] = 1;//默认
        $data['is_others'] = $this->request->query->getInt('is_others');

        //buyer
        $data['ticket_for_noread'] = $this->modelTicket->noReadCount($this->customer->getId());
        $data['message_column'] = $this->load->controller('account/message_center/column_left');

        return $this->render('account/message_center/ticket/index', $data, 'buyer');
    }

    /**
     * 新建Ticket页
     */
    public function sendPage()
    {
        $this->load->language('account/ticket');

        $information_id = intval($this->config->get('message_ticket_guide'));
        $ticketCategoryGroupList = $this->modelTicket->categoryGroupListKeyStr('buyer');
        $data['guide_url'] = $this->url->link('information/information', 'information_id=' . $information_id, true);
        $data['ticketCategoryGroupList'] = !$ticketCategoryGroupList ? '{}' : json_encode($ticketCategoryGroupList);

        return $this->render('account/message_center/ticket/send_page', $data);
    }

    /**
     * RMA Management
     */
    public function showListsRma()
    {
        $param = $this->getParams();
        $data = $param;

        $categoryGroupList = $this->modelTicket->categoryGroupList();
        $categoryKeyList   = $this->modelTicket->categoryKeyList();
        $results           = $this->modelTicket->searchLists($param);
        $total             = $this->modelTicket->searchTotal($param);

        $data['lists'] = [];
        foreach ($results as $value) {
            $value['ticket_type_name']          = isset($categoryKeyList[$value['ticket_type']]) ? $categoryKeyList[$value['ticket_type']]['name'] : '';
            $value['last_modified_time_format'] = $value['modi_date'];
            $value['status_name']               = $value['create_admin_id'] ? 'Replied' : 'Not Replied';
            $data['lists'][]                    = $value;
        }

        //URL
        $url = '';
        if (strtolower($param['order']) == 'desc') {
            $url .= '&order=asc';
        } else {
            $url .= '&order=desc';
        }
        $data['sort_date_modified'] = '&sort=date_modified' . $url;
        $data['sort_customer_is_read'] = '&sort=customer_is_read' . $url;

        //分页
        $totalPages         = ceil($total / $param['page_limit']);
        $data['total_pages'] = $totalPages;
        $data['total_num'] = $total;
        $data['page_limit'] = $param['page_limit'];

        //未读数量 clusterCount
        $culsterCount             = $this->modelTicket->clusterCount($param);
        $data['culsterCount']     = $culsterCount;
        $data['culsterCountJson'] = json_encode($culsterCount);
        $data['categoryRootid']   = json_encode(array_keys($categoryGroupList[0]));

        //左侧列表
        $left_lists   = [];
        $left_lists[] = [
            'name'     => 'New Reply',
            'count'    => $culsterCount['new_reply_count'],
            'value'    => 'newreply',
            'isNoread' => ($culsterCount['new_reply_count'] > 0) ? 1 : 0,
        ];
        $left_lists[] = [
            'name'     => $this->language->get('column_all_request'),
            'count'    => $culsterCount['all_tickets_count'],
            'value'    => 'alltickets',
            'isNoread' => ($culsterCount['new_reply_count'] > 0) ? 1 : 0,
        ];
        foreach ($categoryGroupList[$param['submit_ticket_for']] as $value) {
            $key_ticket_type_count  = 'ticket_type_count_' . $value['category_id'];
            $key_ticket_type_noread = 'ticket_type_noread_' . $value['category_id'];

            $item             = [];
            $item['name']     = $value['name'];
            $item['count']    = isset($culsterCount[$key_ticket_type_count]) ? intval($culsterCount[$key_ticket_type_count]) : 0;
            $item['value']    = $value['category_id'];
            $item['isNoread'] = isset($culsterCount[$key_ticket_type_noread]) ? $culsterCount[$key_ticket_type_noread] : 0;
            $left_lists[]     = $item;
        }
        $data['left_lists'] = $left_lists;
        $data['send_page'] = $this->load->controller('account/message_center/ticket/sendPage');

        return $this->render('account/message_center/ticket/lists_rma', $data);
    }

    /**
     * Sales Order Management
     */
    public function showListsSalesOrder()
    {
        $param = $this->getParams();
        $data = $param;

        $categoryGroupList = $this->modelTicket->categoryGroupList();
        $categoryKeyList   = $this->modelTicket->categoryKeyList();
        $results           = $this->modelTicket->searchLists($param);
        $total             = $this->modelTicket->searchTotal($param);

        $data['lists'] = [];
        foreach ($results as $value) {
            $value['ticket_type_name']          = isset($categoryKeyList[$value['ticket_type']]) ? $categoryKeyList[$value['ticket_type']]['name'] : '';
            $value['last_modified_time_format'] = $value['modi_date'];
            $value['status_name']               = $value['create_admin_id'] ? 'Replied' : 'Not Replied';
            //101592 一件代发 taixing
            if($this->customer->isCollectionFromDomicile()){
                $value['sales_order_url']           = $this->url->link('account/customer_order/customerOrderSalesOrderDetails', 'id=' . $value['sales_order_key'], true);
            }else{
                $value['sales_order_url']           = $this->url->link('account/sales_order/sales_order_management/customerOrderSalesOrderDetails', 'id=' . $value['sales_order_key'], true);
            }
            $value['processing_method_name']    = isset($categoryKeyList[$value['processing_method']]) ? $categoryKeyList[$value['processing_method']]['name'] : '';
            $data['lists'][]                    = $value;
        }

        //URL
        $url = '';
        if (strtolower($param['order']) == 'desc') {
            $url .= '&order=asc';
        } else {
            $url .= '&order=desc';
        }

        $data['sort_date_modified'] = '&sort=date_modified' . $url;
        $data['sort_customer_is_read'] = '&sort=customer_is_read' . $url;

        //分页
        $total_pages         = ceil($total / $param['page_limit']);
        $data['total_pages'] = $total_pages;
        $data['total_num'] = $total;
        $data['page_limit'] = $param['page_limit'];

        //未读数量 clusterCount
        $culsterCount             = $this->modelTicket->clusterCount($param);
        $data['culsterCount']     = $culsterCount;
        $data['culsterCountJson'] = json_encode($culsterCount);
        $data['categoryRootid']   = json_encode(array_keys($categoryGroupList[0]));

        //左侧列表
        $left_lists   = [];
        $left_lists[] = [
            'name'     => 'New Reply',
            'count'    => $culsterCount['new_reply_count'],
            'value'    => 'newreply',
            'isNoread' => ($culsterCount['new_reply_count'] > 0) ? 1 : 0,
        ];
        $left_lists[] = [
            'name'     => $this->language->get('column_all_request'),
            'count'    => $culsterCount['all_tickets_count'],
            'value'    => 'alltickets',
            'isNoread' => ($culsterCount['new_reply_count'] > 0) ? 1 : 0,
        ];
        foreach ($categoryGroupList[$param['submit_ticket_for']] as $value) {
            $key_ticket_type_count  = 'ticket_type_count_' . $value['category_id'];
            $key_ticket_type_noread = 'ticket_type_noread_' . $value['category_id'];

            $item             = [];
            $item['name']     = $value['name'];
            $item['count']    = isset($culsterCount[$key_ticket_type_count]) ? intval($culsterCount[$key_ticket_type_count]) : 0;
            $item['isNoread'] = isset($culsterCount[$key_ticket_type_noread]) ? intval($culsterCount[$key_ticket_type_noread]) : 0;
            $item['value']    = $value['category_id'];
            $left_lists[]     = $item;
        }
        $data['left_lists'] = $left_lists;
        $data['send_page'] = $this->load->controller('account/message_center/ticket/sendPage');

        return $this->render('account/message_center/ticket/lists_sales_order', $data);
    }

    /**
     * Others
     */
    public function showListsOthers()
    {
        $param = $this->getParams();
        $data = $param;
        $role = 'buyer';

        $categoryGroupList = $this->modelTicket->categoryGroupList($role);
        $categoryKeyList   = $this->modelTicket->categoryKeyList($role);
        $results           = $this->modelTicket->searchLists($param);
        $total             = $this->modelTicket->searchTotal($param);

        $data['lists'] = [];
        foreach ($results as $value) {
            $value['ticket_type_name'] = isset($categoryKeyList[$value['ticket_type']]) ? $categoryKeyList[$value['ticket_type']]['name'] : '';
            $value['last_modified_time_format'] = $value['modi_date'];
            $value['status_name'] = $value['create_admin_id'] ? 'Replied' : 'Not Replied';
            $data['lists'][] = $value;
        }

        //URL
        $url = '';
        if (strtolower($param['order']) == 'desc') {
            $url .= '&order=asc';
        } else {
            $url .= '&order=desc';
        }

        $data['sort_date_modified'] = '&sort=date_modified' . $url;
        $data['sort_customer_is_read'] = '&sort=customer_is_read' . $url;

        //分页
        $total_pages         = ceil($total / $param['page_limit']);
        $data['total_pages'] = $total_pages;
        $data['total_num'] = $total;
        $data['page_limit'] = $param['page_limit'];

        //未读数量 clusterCount
        $culsterCount             = $this->modelTicket->clusterCount($param);
        $data['culsterCount']     = $culsterCount;
        $data['culsterCountJson'] = json_encode($culsterCount);
        $data['categoryRootid']   = json_encode(array_keys($categoryGroupList[0]));

        //左侧列表
        $left_lists   = [];
        $left_lists[] = [
            'name'     => 'New Reply',
            'count'    => $culsterCount['new_reply_count'],
            'value'    => 'newreply',
            'isNoread' => ($culsterCount['new_reply_count'] > 0) ? 1 : 0,
        ];
        $left_lists[] = [
            'name'     => $this->language->get('column_all_request'),
            'count'    => $culsterCount['all_tickets_count'],
            'value'    => 'alltickets',
            'isNoread' => ($culsterCount['new_reply_count'] > 0) ? 1 : 0,
        ];
        foreach ($categoryGroupList[$param['submit_ticket_for']] as $value) {
            $key_ticket_type_count  = 'ticket_type_count_' . $value['category_id'];
            $key_ticket_type_noread = 'ticket_type_noread_' . $value['category_id'];

            $item             = [];
            $item['name']     = $value['name'];
            $item['count']    = isset($culsterCount[$key_ticket_type_count]) ? intval($culsterCount[$key_ticket_type_count]) : 0;
            $item['isNoread'] = isset($culsterCount[$key_ticket_type_noread]) ? intval($culsterCount[$key_ticket_type_noread]) : 0;
            $item['value']    = $value['category_id'];
            $left_lists[]     = $item;
        }
        $data['left_lists'] = $left_lists;
        $data['send_page'] = $this->load->controller('account/message_center/ticket/sendPage');

        switch ($param['submit_ticket_for']) {
            case '21':
                return $this->render('account/message_center/ticket/lists_safeguard', $data);
            default:
                return $this->render('account/message_center/ticket/lists_others', $data);
                break;
        }
    }

    /**
     * 详情
     */
    public function view()
    {
        $id = $this->request->get('id', 0);
        if ($id < 1) {
            $this->redirect(['error/not_found'])->send();
        }

        $data['type'] = $this->request->get('type', 1);
        $data['ticket_title_long'] = $this->language->get('text_ticket');
        $role = 'buyer';
        $categoryKeyList = $this->modelTicket->categoryKeyList($role);
        //属于buyers自己的Ticket
        $info = $this->modelTicket->getTicketInfoById($id);
        if (!$info) {
            $data['lists'] = [];
        } else {
            $submitTicketFroName = isset($categoryKeyList[$info['submit_ticket_for']]) ? $categoryKeyList[$info['submit_ticket_for']]['name'] : '';
            $ticketTypeName = isset($categoryKeyList[$info['ticket_type']]) ? $categoryKeyList[$info['ticket_type']]['name'] : '';
            $processingMethodName = isset($categoryKeyList[$info['processing_method']]) ? $categoryKeyList[$info['processing_method']]['name'] : '';
            $titleSuffix = '&nbsp;(' . $info['ticket_id'] . ')&nbsp;-&nbsp;' . $ticketTypeName;
            $data['ticket_title_long']      = $titleSuffix;
            $data['ticket_id']              = $info['id'];
            $data['ticket_long_id']         = $info['ticket_id'];
            $data['submit_ticket_for']      = $info['submit_ticket_for'];
            $data['submit_ticket_for_name'] = $submitTicketFroName;
            $data['ticket_type']            = $info['ticket_type'];
            $data['ticket_type_name']       = $ticketTypeName;
            $data['rma_id']                 = $info['rma_id'];
            $data['rma_key']                = $info['rma_key'];
            $data['sales_order_id']         = $info['sales_order_id'];
            $data['sales_item_code']        = $info['sales_item_code'];
            $data['tracking_number']        = $info['tracking_number'];
            $data['sales_order_key']        = $info['sales_order_key'];
            $data['processing_method']      = $info['processing_method'];
            $data['processing_method_name'] = $processingMethodName;
            $data['safeguard_claim_no']     = $info['safeguard_claim_no'];
            $data['rma_url']         = $this->url->link('account/rma_order_detail', 'rma_id=' . $info['rma_key']);
            //101592 一件代发 taixing
            if($this->customer->isCollectionFromDomicile()){
                $data['sales_order_url'] = $this->url->link('account/customer_order/customerOrderSalesOrderDetails', 'id=' . $info['sales_order_key'], true);
            }else{
                $data['sales_order_url'] = $this->url->link('account/sales_order/sales_order_management/customerOrderSalesOrderDetails', 'id=' . $info['sales_order_key'], true);
            }

            /** @var ModelAccountCustomerpartner $modelCustomerpartner */
            $modelCustomerpartner = load()->model('account/customerpartner');;
            $LoginInInfo = $modelCustomerpartner->getLoginInInfoByCustomerId();
            //查询该Ticket下所有的 TicketMessages
            $results = $this->modelTicket->ticketMessaes($id);
            //101592 一件代发 taixing
            $isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
            if($isCollectionFromDomicile){
                $order_string = 'index.php?route=account/customer_order/customerOrderSalesOrderDetails&id=';
            }else{
                $order_string = 'index.php?route=account/sales_order/sales_order_management/customerOrderSalesOrderDetails&id=';
            }

            foreach ($results as $value) {
                //上传的附件，需要处理格式。
                $attachmentsList = $value['attachments'] ? json_decode($value['attachments'], true) : [];
                foreach ($attachmentsList as $k=>$v) {
                    $v['is_img']         = $this->isImgByName($v['name']);
                    $fileUrl = $v['url'];
                    if (\Illuminate\Support\Str::contains($fileUrl, '%')) {
                        $fileUrl = urlencode($fileUrl);
                    }
                    if (StorageCloud::root()->fileExists($fileUrl)) {
                        $v['urlencode_url'] = StorageCloud::root()->getUrl($fileUrl, ['check-exist' => false]);
                    } else {
                        $v['urlencode_url'] = str_replace($v['name'], rawurlencode($v['name']), $v['url']);
                    }
                    $attachmentsList[$k] = $v;
                }

                $data['lists'][] = [
                    'id'                 => $value['id'],
                    'from'               => $value['create_admin_id'] > 0 ? 'server' : 'client',
                    'ticket_type'        => $info['ticket_type'],
                    'rma_id'             => $info['rma_id'],
                    'rma_key'            => $info['rma_key'],
                    'rma_url'            => $this->url->link('account/rma_order_detail', 'rma_id=' . $info['rma_key'], true),
                    'sales_order_id'     => $info['sales_order_id'],
                    'sales_order_key'    => $info['sales_order_key'],
                    'sales_order_url'    => $this->url->link($order_string, 'id=' . $info['sales_order_key'], true),
                    'submit_time_format' => $value['date_added'],
                    'nickName'           => $LoginInInfo['nickname'],
                    'adminName'          => 'GIGACLOUD',
                    'showName'           => $value['create_admin_id'] ? 'GIGACLOUD' : ($LoginInInfo['nickname'] . '(' . $LoginInInfo['user_number'] . ')'),
                    'description'        => $value['description'],
                    'attachments'        => $attachmentsList,
                    'is_read'            => $value['is_read'],
                    'safeguard_claim_no' => $info['safeguard_claim_no'],
                ];
            }
        }
        $this->modelTicket->updateReadTime($id);
        $data['message_column'] = $this->load->controller('account/message_center/column_left');
        $data['send_page'] = $this->load->controller('account/message_center/ticket/sendPage');

        return $this->render('account/message_center/ticket/view', $data, 'buyer');
    }

    /**
     * 回复
     */
    public function reply()
    {
        $ticketId = $this->request->post('ticket_id', 0);
        $res['ret'] = 0;
        $res['msg'] = $this->language->get('error_invalid_request');

        if ($ticketId < 1) {
            return $this->json($res);
        }

        $ticketInfo = app(TicketRepository::class)->getTicketInfoById($ticketId);
        if (! $ticketInfo || $ticketInfo->create_customer_id != $this->customer->getId()) {
            return $this->json($res);
        }

        $data['ticket_id'] = $ticketId;
        $data['create_customer_id'] = $this->customer->getId();
        $data['description'] = $this->request->post('ticket_description', '');
        $data['attachments'] = htmlspecialchars_decode($this->request->post('ticket_attachments', ''));

        if (strlen($data['description']) < 1) {
            $res['msg'] = $this->language->get('error_description_empty');
            return $this->json($res);
        }
        //101741 换行不会保存，这里将换行符换成<br>
        $data['description'] = str_replace("\n", "<br>", $data['description']);

        $result = app(TicketService::class)->reply($data, $ticketInfo);
        if ($result) {
            $res['ret'] = 1;
            $res['msg'] = $this->language->get('text_submit_successfully');
        } else {
            $res['msg'] = $this->language->get('error_failed');
        }

        return $this->json($res);
    }

    private function getParams()
    {
        $param['submit_ticket_for'] = $this->request->get('submit_ticket_for', 0);
        $param['right'] = $this->request->get('right', 'newreply');
        $param['wd'] = trim($this->request->get('wd', ''));
        $param['page_num'] = $this->request->get('page_num', 1);
        $param['page_limit'] = $this->request->get('page_limit', 15);
        $param['sort'] = $this->request->get('sort', 'customer_is_read');
        $param['order'] = $this->request->get('order', 'asc');

        return $param;
    }

    /**
     * 判断是否图片
     *
     * @param $name
     * @return bool
     */
    private function isImgByName($name)
    {
        $suffix = strtolower(substr(strrchr($name, '.'), 1));

        $suffixArr = [
            'gif', 'jpg', 'jpeg', 'png', 'swf', 'psd', 'bmp', 'tiff_ii', 'tiff_mm', 'jpc', 'jp2', 'jpx', 'jb2', 'swc', 'iff', 'wbmp', 'xbm'
        ];

        if (in_array($suffix, $suffixArr)) {
            return true;
        } else {
            return false;
        }
    }
}
