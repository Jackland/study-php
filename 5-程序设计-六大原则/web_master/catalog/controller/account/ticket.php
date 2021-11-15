<?php

use App\Catalog\Controllers\AuthController;
use App\Models\Safeguard\SafeguardClaim;
use App\Components\Storage\StorageCloud;
use App\Repositories\Message\TicketRepository;
use App\Repositories\Safeguard\SafeguardClaimRepository;
use App\Services\Message\TicketService;
use Symfony\Component\HttpFoundation\File\UploadedFile;

//buyer
//需求文档见：http://192.168.10.172:9089/review/download/attachment/requirement/4382.html
//做3.1到3.4

/**
 * Class ControllerAccountTicket
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 * @property ModelAccountTicket $model_account_ticket
 */
class ControllerAccountTicket extends AuthController
{
    public function __construct($registry)
    {
        parent::__construct($registry);

        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/login', '', true));
        }
    }

    public function index()
    {

    }


    public function test()
    {
        $json = ['name' => 'zhangsan'];

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode(!$json ? (object)[] : $json));

        //return ;


        $json = ['name' => 'lisi'];

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode(!$json ? (object)[] : $json));
    }


    public function add()
    {
        $this->load->model('account/ticket');
        $this->load->language('account/ticket');

        $json = [];

        if ((request()->isMethod('POST'))) {


            $data['create_customer_id'] = $this->customer->getId();
            $data['role'] = $this->customer->isPartner() ? 'seller' : 'buyer';
            $data['submit_ticket_for'] = intval($this->request->post('submit_ticket_for', 0));
            $data['ticket_type'] = intval($this->request->post('ticket_type', 0));
            $data['rma_id'] = trim($this->request->post('rma_id', ''));
            $data['sales_order_id'] = trim($this->request->post('sales_order_id', ''));
            $data['processing_method'] = trim($this->request->post('processing_method', ''));
            $data['sales_item_code'] = trim($this->request->post('sales_item_code', ''));
            $data['seller_item_code'] = trim($this->request->post('seller_item_code', ''));
            $data['tracking_number'] = trim($this->request->post('tracking_number', ''));
            $data['safeguard_claim_no'] = trim($this->request->post('safeguard_claim_no', ''));
            $data['description'] = addslashes($this->request->post('ticket_description', ''));
            $data['attachments'] = addslashes(htmlspecialchars_decode($this->request->post('ticket_attachments', '')));
            //校验sale_order_id 的状态不能为complete
            $order_status = $this->model_account_ticket->getOrderStatusBySaleOrderId($data['sales_order_id'],$data['create_customer_id']);
            if($order_status == 32 && $data['ticket_type'] != 19){
                //101301需求，类型19下不限制订单状态
                $json['ret'] = 0;
                $json['msg'] = 'This order is a completed order which has been shipped. It cannot be intercepted.';
                header('Content-Type: application/json');
                die(json_encode($json));
            }

            //判断必填，剔除多余值
            if ($data['submit_ticket_for'] < 1) {
                $json['ret'] = 0;
                $json['msg'] = 'Submit a Request For can not be left blank.';
                header('Content-Type: application/json');
                die(json_encode($json));
            }
            if ($data['submit_ticket_for'] == 1) {
                if ($data['ticket_type'] < 1) {
                    $json['ret'] = 0;
                    $json['msg'] = 'Request Type can not be left blank.';
                    header('Content-Type: application/json');
                    die(json_encode($json));
                }
                if (!$data['rma_id']) {
                    $json['ret'] = 0;
                    $json['msg'] = 'RMA ID can not be left blank.';
                    header('Content-Type: application/json');
                    die(json_encode($json));
                }
                if($data['ticket_type']  == 18){
                    $rma_id = $this->request->post['rma_id'];
                    $status_reshipment = $this->model_account_ticket->checkRmaId($rma_id);
                    if (isset($status_reshipment)) {
                        if ($status_reshipment != 1) {
                            $json['ret'] = 0;
                            $json['msg'] = "Cannot submit a reshipment ticket, this RMA ID is not the one approved by seller for reshipment.";
                            header('Content-Type: application/json');
                            die(json_encode($json));
                        }
                    } else {
                        $json['ret'] = 0;;
                        $json['msg'] = "RMA ID is invalid.";
                        header('Content-Type: application/json');
                        die(json_encode($json));
                    }
                }

                $data['sales_order_id']    = '';
                $data['processing_method'] = 0;

            } elseif ($data['submit_ticket_for'] == 2) {
                if ($data['ticket_type'] < 1) {
                    $json['ret'] = 0;
                    $json['msg'] = 'Request Type can not be left blank.';
                    header('Content-Type: application/json');
                    die(json_encode($json));
                }
                if (!$data['sales_order_id']) {
                    $json['ret'] = 0;
                    $json['msg'] = 'Sales Order ID can not be left blank.';
                    header('Content-Type: application/json');
                    die(json_encode($json));
                }
                if ($data['ticket_type'] == 19) {
                    //新加的item code 校验
                    if (!$data['sales_item_code']) {
                        $json['ret'] = 0;
                        $json['msg'] = 'Item Code can not be left blank.';
                        header('Content-Type: application/json');
                        die(json_encode($json));
                    }
                    $data['processing_method'] = '';
                } else {
                    if ($data['processing_method'] < 1) {
                        $json['ret'] = 0;
                        $json['msg'] = 'Processing Method can not be left blank.';
                        header('Content-Type: application/json');
                        die(json_encode($json));
                    }
                    $data['sales_item_code'] = '';
                    $data['tracking_number'] = '';
                }
                $data['rma_id'] = '';
            } elseif ($data['submit_ticket_for'] == 9) {
                if($data['ticket_type'] == 20){
                    //Warehouse services->New Parts Retrieval and Reshipment
                    if (!$data['seller_item_code']) {
                        $json['ret'] = 0;
                        $json['msg'] = 'Item Code can not be left blank.';
                        header('Content-Type: application/json');
                        die(json_encode($json));
                    }
                    //替换字段，删除掉没用的
                    $data['sales_item_code'] = $data['seller_item_code'];
                    unset($data['seller_item_code']);
                    if (!$data['rma_id']) {
                        $json['ret'] = 0;
                        $json['msg'] = 'RMA ID can not be left blank.';
                        header('Content-Type: application/json');
                        die(json_encode($json));
                    }
                } else {
                    $data['sales_item_code'] = '';
                    $data['seller_item_code'] = '';
                    $data['rma_id'] = '';
                }
            } elseif ($data['submit_ticket_for'] == 21 && $data['ticket_type'] == 22) {
                if (!$data['safeguard_claim_no']) {
                    $json['ret'] = 0;
                    $json['msg'] = 'Claim Application ID can not be left blank.';
                    header('Content-Type: application/json');
                    die(json_encode($json));
                }
                $checkSafeguardNo = $this->checkSafeguardNo($data['safeguard_claim_no']);
                if (!$checkSafeguardNo['success']) {
                    $json['ret'] = 0;
                    $json['msg'] = $checkSafeguardNo['msg'];
                    header('Content-Type: application/json');
                    die(json_encode($json));
                }
            }

            if (strlen($data['description']) < 1) {
                $json['ret'] = 0;
                $json['msg'] = 'Description can not be left blank.';
                header('Content-Type: application/json');
                die(json_encode($json));
            }
            //101741 换行不会保存，这里将换行符换成<br>
            $data['description'] = str_replace("\n", "<br>", $data['description']);

            //如果是rma_id，则判断是否提交过。
            //如果是sales_order_id，则判断是否提交过。
            $result          = $this->model_account_ticket->isSubmited($data);
            $categoryType = $this->customer->isPartner() ? 'seller' : 'buyer';
            $categoryKeyList = $this->model_account_ticket->categoryKeyList($categoryType);
            if ($result) {
                $json['ret'] = 0;
                if ($data['submit_ticket_for'] == 2 && $data['ticket_type'] == 19) {
                    $msg_sales_order_id = $data['sales_order_id'];
                    $msg_sales_item_code = $data['sales_item_code'];
                    $msg_ticket_type_name = isset($categoryKeyList[$data['ticket_type']]) ? $categoryKeyList[$data['ticket_type']]['name'] : '';
                    $msg_ticket_id = $result->ticket_id;

                    $json['msg'] = "This sales order ID and Item Code ({$msg_sales_order_id} - {$msg_sales_item_code}) has already filed an 'Unsuccessful Processing (Marketplace)' claim and cannot refile. Please add additional feedback or concerns on the original request page (Request ID: {$msg_ticket_id}).";
                    header('Content-Type: application/json');
                    die(json_encode($json));
                }
                if (!empty($data['rma_id'])) {
                    $msg_rmaid = $data['rma_id'];
                    $msg_ticket_type_name = isset($categoryKeyList[$data['ticket_type']]) ? $categoryKeyList[$data['ticket_type']]['name'] : '';
                    $msg_ticket_id = $result->ticket_id;

                    $json['msg'] = 'This RMA【' . $msg_rmaid . '】 has already submitted a Request of 【' . $msg_ticket_type_name . '】, Request ID【' . $msg_ticket_id . '】, please go to the Request details for an additional reply. ';
                    //$this->response->addHeader('Content-Type: application/json');
                    //$this->response->setOutput(json_encode($json));
                    header('Content-Type: application/json');
                    die(json_encode($json));
                }
                if (!empty($data['sales_order_id'])) {
                    $msg_sales_order_id = $data['sales_order_id'];
                    $msg_ticket_type_name = isset($categoryKeyList[$data['ticket_type']]) ? $categoryKeyList[$data['ticket_type']]['name'] : '';
                    $msg_ticket_id = $result->ticket_id;

                    $json['msg'] = 'This Sales Order ID【' . $msg_sales_order_id . '】 has already submitted a Request of 【' . $msg_ticket_type_name . '】, Request ID【' . $msg_ticket_id . '】, please go to the Request details for an additional reply. ';
                    //$this->response->addHeader('Content-Type: application/json');
                    //$this->response->setOutput(json_encode($json));
                    header('Content-Type: application/json');
                    die(json_encode($json));
                }
            }

            if ($data['submit_ticket_for'] == 1) {
                //验证 rma_id 属于当前用户
                $result = $this->model_account_ticket->getRamOrderInfoByRmaid($data['rma_id']);
                if (!$result) {
                    $json['ret'] = 0;
                    $json['msg'] = $this->language->get('error_rma_invalid');
                    header('Content-Type: application/json');
                    die(json_encode($json));
                }
                $data['rma_key'] = $result['id'];
                //$data['to_customer_id'] = $result['seller_id'];
            } elseif ($data['submit_ticket_for'] == 2) {
                //验证 sales_order_id 属于当前用户
                $result = $this->model_account_ticket->getSalesOrderInfoByRmaid($data['sales_order_id']);
                if (!$result) {
                    $json['ret'] = 0;
                    $json['msg'] = $this->language->get('error_sales_order_invalid');
                    header('Content-Type: application/json');
                    die(json_encode($json));
                }
                $data['sales_order_key'] = $result['id'];
                //验证 item sales_order_id
                if($data['ticket_type'] == 19){
                    $buyerId = intval($this->customer->getId());
                    $itemCodeList = $this->model_account_ticket->getItemCodeListByOrderId($buyerId,$data['sales_order_id']);
                    if (empty($itemCodeList) || $itemCodeList->where('item_code', $data['sales_item_code'])->count() < 1) {
                        $json['ret'] = 0;
                        $json['msg'] = 'Item Code is invalid.';
                        header('Content-Type: application/json');
                        die(json_encode($json));
                    }
                }
            } elseif ($data['submit_ticket_for'] == 9 && $data['ticket_type'] == 20) {
                $sellerId = intval($this->customer->getId());
                //验证item code
                $itemCodeStatus = $this->model_account_ticket->checkSellerItemCode($sellerId,$data['sales_item_code']);
                if (!$itemCodeStatus) {
                    $json['ret'] = 0;
                    $json['msg'] = 'Item Code is invalid.';
                    header('Content-Type: application/json');
                    die(json_encode($json));
                }
                //库存不大于0
                if (!$itemCodeStatus->instock_qty || $itemCodeStatus->instock_qty < 1) {
                    $json['ret'] = 0;
                    $json['msg'] = 'This item code is currently out of stock.';
                    header('Content-Type: application/json');
                    die(json_encode($json));
                }
                //验证rma id属于这个seller
                //验证 rma_id 属于当前用户
                $rmaData = $this->model_account_ticket->getSellerRamOrderInfoByRmaid($sellerId, $data['rma_id']);
                if (!$rmaData) {
                    $json['ret'] = 0;
                    $json['msg'] = $this->language->get('error_rma_invalid');
                    header('Content-Type: application/json');
                    die(json_encode($json));
                }
                $data['rma_key'] = $rmaData->id;
            }


            $result = $this->model_account_ticket->add($data);

            if ($result['ret']) {
                $json['ret'] = 1;
                $json['msg'] = $this->language->get('text_submit_successfully');
            } else {
                $json['ret'] = 0;
                $json['msg'] = $result['msg'];
            }
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
        } else {
            $json['ret'] = 0;
            $json['msg'] = 'Submit Method Error.';
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
        }
    }


    /**
     * 后面Buyer 和 Seller 代码区分开，此处Buyer不在使用（Buyer:account/message_center/ticket）
     */
    public function reply()
    {
        $this->load->language('account/ticket');

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


    //自动补全 rma_id
    public function autocompletermaid()
    {
        $json = [];

        $this->load->model('account/ticket');
        $rma_id = $this->request->get['rma_id'];
        if (strlen($rma_id) > 0) {

            $results = $this->model_account_ticket->autocompletermaid($rma_id);
            foreach ($results as $result) {
                $json[] = [
                    'rma_id' => $result['rma_order_id']
                ];
            }


            $sort_order = array();

            foreach ($json as $key => $value) {
                $sort_order[$key] = $value['rma_id'];
            }

            array_multisort($sort_order, SORT_DESC, $json);
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    //自动补全 订单id
    public function autocompleteorderid()
    {
        $json = [];

        $this->load->model('account/ticket');
        $order_id = $this->request->get['order_id'];
        if (strlen($order_id) > 0) {

            $results = $this->model_account_ticket->autocompleteorderid($order_id);
            foreach ($results as $result) {
                $json[] = [
                    'order_id' => $result['order_id']
                ];
            }


            $sort_order = array();

            foreach ($json as $key => $value) {
                $sort_order[$key] = $value['order_id'];
            }

            array_multisort($sort_order, SORT_DESC, $json);
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * 获取item code
     *
     * 如果是buyer 查询指定的order下的item code
     * 如果是seller 查询店铺下的，可模糊查询
     *
     * @throws Exception
     */
    public function getItemCodeList()
    {
        $this->load->model('account/ticket');
        $itemCodeList = [];
        if($this->customer->isPartner()){
            $itemCode = $this->request->post['item_code'];
            if ($itemCode) {
                $sellerId = intval($this->customer->getId());
                $itemCodeList = $this->model_account_ticket->getItemCodeListBySeller($sellerId,$itemCode);
            }
        } else{
            $order_id = $this->request->post['order_id'];
            if (strlen($order_id) > 0) {
                $buyerId = intval($this->customer->getId());
                $itemCodeList = $this->model_account_ticket->getItemCodeListByOrderId($buyerId,$order_id);
            }
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($itemCodeList));
    }


    //点击头部导航，进入
    public function lists()
    {
        if($this->customer->isPartner()){
            $this->lists_for_sellers();
        } else {
            $this->lists_for_buyers();
        }
    }


    public function lists_for_sellers()
    {
        //RMA
        $data['type'] = request('type');


        $this->load->model('account/ticket');
        // 加载语言层
        $this->load->language('account/ticket');
        $this->document->setTitle($this->language->get('text_tickets'));//<title>Tickets</title>


        $data['submit_ticket_for'] = 1;//默认
        $data['isParter']          = $this->customer->isPartner();
        $data['is_others']         = get_value_or_default($this->request->get,'is_others',0);

        //未读数量 clusterCount
        $culsterCount         = $this->model_account_ticket->clusterCount(); //echo '<pre>';print_r($culsterCount);die;
        $data['culsterCount'] = $culsterCount;


        // 面包屑导航
        $data['breadcrumbs']   = array();
        $data['breadcrumbs'][] = array(
            'text' => 'Message Center',
            'href' => 'javascript:void(0);',
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_tickets'),
            'href' => $this->url->link('account/ticket/lists', '', true)
        );

        $data['heading_name'] = $this->language->get('text_tickets');

        $data['heading_title'] = 'Tickets';

        //seller
        $data['separate_view']        = true;
        $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');
        $data['footer']               = $this->load->controller('account/customerpartner/footer');
        $data['header']               = $this->load->controller('account/customerpartner/header');
        $data['ticketBuyer']          = $this->load->controller('common/ticket');

        $data['message_column']    = $this->load->controller('account/customerpartner/message_column');
        $data['ticket_for_noread'] = $this->model_account_ticket->noReadCount($this->customer->getId());


        $this->response->setOutput($this->load->view('account/ticket/lists_seller', $data));
    }

    public function lists_for_buyers()
    {
        //RMA
        $data = [];


        $this->load->model('account/ticket');
        // 加载语言层
        $this->load->language('account/ticket');
        $this->document->setTitle($this->language->get('text_leave_a_message'));//<title>Tickets</title>


        $data['submit_ticket_for'] = 1;//默认
        $data['isParter']          = $this->customer->isPartner();
        $data['is_others']         = $this->request->query->getInt('is_others');

        //未读数量 clusterCount
        $culsterCount         = $this->model_account_ticket->clusterCount(); //echo '<pre>';print_r($culsterCount);die;
        $data['culsterCount'] = $culsterCount;
        // 获取一级分类
        $data['categories'] = app(TicketRepository::class)->getCategoriesByParentId(0, 'buyer');

        // 面包屑导航
        $data['breadcrumbs'] = $this->getBreadcrumbs([
            ['text' => 'Message Center', 'href' => $this->url->link('message/seller'),],
            ['text' => 'Customer Service', 'href' => 'javascript:void(0)'],
            ['text' => $this->language->get('text_leave_a_message'), 'href' => $this->url->link('account/ticket/lists', '', true)]
        ]);

        $data['heading_name'] = $this->language->get('text_leave_a_message');

        $data['heading_title'] = 'Tickets';
        //buyer
        $data['separate_view']     = false;
        $data['message_column']    = $this->load->controller('account/customerpartner/message_column', ['checked_btn_id' => 'account-ticket-list']);
        $data['ticket_for_noread'] = $this->model_account_ticket->noReadCount($this->customer->getId());
        if (!$data['isParter']) {
            $data['styles'][] = 'catalog/view/theme/yzcTheme/stylesheet/stylesheetMessage.css';
        }

        return $this->render('account/ticket/lists', $data, 'buyer');
    }

    /**
     *  后面Buyer 和 Seller 代码区分开，此处Buyer不在使用（Buyer:account/message_center/ticket）
     */
    public function showListsRma()
    {
        $data = [];

        $this->load->model('account/ticket');
        // 加载语言层
        $this->load->language('account/ticket');

        //查询数据
        $submit_ticket_for = max(0, isset($this->request->get['submit_ticket_for']) ? intval($this->request->get['submit_ticket_for']) : 0);
        $right             = isset($this->request->get['right']) ? $this->request->get['right'] : 'newreply';
        $wd                = '';
        if (isset($this->request->get['wd'])) {
            $wd = trim($this->request->get['wd']);
        }
        if (isset($this->request->get['page_num'])) {
            $page_num = $this->request->get['page_num'];
        } else {
            $page_num = 1;
        }
        if (isset($this->request->get['page_limit'])) {
            $page_limit = $this->request->get['page_limit'];
        } else {
            $page_limit = 15;
        }
        $sort  = isset($this->request->get['sort']) ? $this->request->get['sort'] : 'customer_is_read';
        $order = isset($this->request->get['order']) ? $this->request->get['order'] : 'asc';


        $param                      = [];
        $param['submit_ticket_for'] = $submit_ticket_for;
        $param['right']             = $right;
        $param['wd']                = $wd;
        $param['page_num']          = $page_num;
        $param['page_limit']        = $page_limit;
        $param['sort']              = $sort;
        $param['order']             = $order;


        $data = array_merge($param, $data);


        $categoryGroupList = $this->model_account_ticket->categoryGroupList();
        $categoryKeyList   = $this->model_account_ticket->categoryKeyList();
        $results           = $this->model_account_ticket->searchLists($param);
        $total             = $this->model_account_ticket->searchTotal($param);
        //print_r($results);

        $data['lists'] = [];
        $isPartner = $this->customer->isPartner();
        foreach ($results as $value) {
            $value['ticket_type_name']          = isset($categoryKeyList[$value['ticket_type']]) ? $categoryKeyList[$value['ticket_type']]['name'] : '';
            $value['last_modified_time_format'] = $value['modi_date'];
            $value['status_name']               = $value['create_admin_id'] ? 'Replied' : 'Not Replied';
            if($isPartner){
                $value['rma_url'] = $this->url->link('account/customerpartner/rma_management/rmaInfo', 'rmaId=' . $value['rma_key']);
            } else {
                $value['rma_url'] = $this->url->link('account/rma_order_detail', 'rma_id=' . $value['rma_key']);
            }
            $value['view_url']                  = url(['account/ticket/messages', 'id' => $value['id'], 'type' => $submit_ticket_for]);
            $data['lists'][]                    = $value;
        }
        //print_r($data['lists']);


        //URL
        $url = '';
        if (strtolower($order) == 'desc') {
            $url .= '&order=asc';
        } else {
            $url .= '&order=desc';
        }
        $data['sort_date_modified'] = '&sort=date_modified' . $url;
        $this->url->link('account/ticket/showListsRma', '' . '&sort=date_modified' . $url, true);
        $data['sort_customer_is_read'] = '&sort=customer_is_read' . $url;
        $this->url->link('account/ticket/showListsRma', '' . '&sort=customer_is_read' . $url, true);


        //分页
        $total_pages         = ceil($total / $page_limit);
        $data['total_pages'] = $total_pages;
        $data['total_num'] = $total;
        $data['page_limit'] = $page_limit;

        //未读数量 clusterCount
        $culsterCount             = $this->model_account_ticket->clusterCount($param);
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
        foreach ($categoryGroupList[$submit_ticket_for] as $value) {
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
        $data['is_seller'] = $this->customer->isPartner();;


        $this->response->setOutput($this->load->view('account/ticket/lists_rma', $data));
    }

    /**
     *  后面Buyer 和 Seller 代码区分开，此处Buyer不在使用（Buyer:account/message_center/ticket）
     */
    public function showListsSalesOrder()
    {
        $data = [];

        $this->load->model('account/ticket');
        // 加载语言层
        $this->load->language('account/ticket');

        //查询数据
        $submit_ticket_for = max(0, isset($this->request->get['submit_ticket_for']) ? intval($this->request->get['submit_ticket_for']) : 0);
        $right             = isset($this->request->get['right']) ? $this->request->get['right'] : 'newreply';
        $wd                = '';
        if (isset($this->request->get['wd'])) {
            $wd = trim($this->request->get['wd']);
        }
        if (isset($this->request->get['page_num'])) {
            $page_num = $this->request->get['page_num'];
        } else {
            $page_num = 1;
        }
        if (isset($this->request->get['page_limit'])) {
            $page_limit = $this->request->get['page_limit'];
        } else {
            $page_limit = 15;
        }
        $sort  = isset($this->request->get['sort']) ? $this->request->get['sort'] : 'customer_is_read';
        $order = isset($this->request->get['order']) ? $this->request->get['order'] : 'asc';


        $param                      = [];
        $param['submit_ticket_for'] = $submit_ticket_for;
        $param['right']             = $right;
        $param['wd']                = $wd;
        $param['page_num']          = $page_num;
        $param['page_limit']        = $page_limit;
        $param['sort']              = $sort;
        $param['order']             = $order;


        $data = array_merge($param, $data);


        $categoryGroupList = $this->model_account_ticket->categoryGroupList();
        $categoryKeyList   = $this->model_account_ticket->categoryKeyList();
        $results           = $this->model_account_ticket->searchLists($param);
        $total             = $this->model_account_ticket->searchTotal($param);
        //print_r($results);

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
            $value['view_url']                  = url(['account/ticket/messages', 'id' => $value['id'], 'type' => $submit_ticket_for]);
            $data['lists'][]                    = $value;
        }
        //print_r($data['lists']);


        //URL
        $url = '';
        if (strtolower($order) == 'desc') {
            $url .= '&order=asc';
        } else {
            $url .= '&order=desc';
        }

        $data['sort_date_modified'] = '&sort=date_modified' . $url;
        $data['sort_customer_is_read'] = '&sort=customer_is_read' . $url;


        //分页
        $total_pages         = ceil($total / $page_limit);
        $data['total_pages'] = $total_pages;
        $data['total_num'] = $total;
        $data['page_limit'] = $page_limit;

        //未读数量 clusterCount
        $culsterCount             = $this->model_account_ticket->clusterCount($param);
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
        foreach ($categoryGroupList[$submit_ticket_for] as $value) {
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
        $data['is_seller'] = $this->customer->isPartner();


        $this->response->setOutput($this->load->view('account/ticket/lists_sales_order', $data));
    }

    /**
     *  后面Buyer 和 Seller 代码区分开，此处Buyer不在使用（Buyer:account/message_center/ticket）
     */
    public function showListsOthers()
    {
        $data = [];

        $this->load->model('account/ticket');
        // 加载语言层
        $this->load->language('account/ticket');

        //查询数据
        $submit_ticket_for = max(0, isset($this->request->get['submit_ticket_for']) ? intval($this->request->get['submit_ticket_for']) : 0);
        $right             = isset($this->request->get['right']) ? $this->request->get['right'] : 'newreply';
        $wd                = '';
        if (isset($this->request->get['wd'])) {
            $wd = trim($this->request->get['wd']);
        }
        if (isset($this->request->get['page_num'])) {
            $page_num = (int)$this->request->get['page_num'];
        } else {
            $page_num = 1;
        }
        if (isset($this->request->get['page_limit'])) {
            $page_limit = (int)$this->request->get['page_limit'] ?: 15;
        } else {
            $page_limit = 15;
        }
        $sort  = isset($this->request->get['sort']) ? $this->request->get['sort'] : 'customer_is_read';
        $order = isset($this->request->get['order']) ? $this->request->get['order'] : 'asc';


        $param                      = [];
        $param['submit_ticket_for'] = $submit_ticket_for;
        $param['right']             = $right;
        $param['wd']                = $wd;
        $param['page_num']          = $page_num;
        $param['page_limit']        = $page_limit;
        $param['sort']              = $sort;
        $param['order']             = $order;


        $data = array_merge($param, $data);


        if($this->customer->isPartner()){
            $role = 'seller';
        } else {
            $role = 'buyer';
        }

        $categoryGroupList = $this->model_account_ticket->categoryGroupList($role);
        $categoryKeyList   = $this->model_account_ticket->categoryKeyList($role);
        $results           = $this->model_account_ticket->searchLists($param);
        $total             = $this->model_account_ticket->searchTotal($param);
        //print_r($results);

        $data['lists'] = [];
        $isPartner = $this->customer->isPartner();
        foreach ($results as $value) {
            $value['ticket_type_name']          = isset($categoryKeyList[$value['ticket_type']]) ? $categoryKeyList[$value['ticket_type']]['name'] : '';
            $value['last_modified_time_format'] = $value['modi_date'];
            $value['status_name']               = $value['create_admin_id'] ? 'Replied' : 'Not Replied';
            $value['view_url']                  = url(['account/ticket/messages', 'id' => $value['id'], 'type' => $submit_ticket_for]);
            if ($value['rma_id'] && $value['rma_key']) {
                if($isPartner){
                    $value['rma_url'] = $this->url->link('account/customerpartner/rma_management/rmaInfo', 'rmaId=' . $value['rma_key']);
                } else {
                    $value['rma_url'] = $this->url->link('account/rma_order_detail', 'rma_id=' . $value['rma_key']);
                }
            }
            $data['lists'][]                    = $value;
        }
        //print_r($data['lists']);die;


        //URL
        $url = '';
        if (strtolower($order) == 'desc') {
            $url .= '&order=asc';
        } else {
            $url .= '&order=desc';
        }

        $data['sort_date_modified'] = '&sort=date_modified' . $url;
        $data['sort_customer_is_read'] = '&sort=customer_is_read' . $url;


        //分页
        $total_pages         = ceil($total / $page_limit);
        $data['total_pages'] = $total_pages;
        $data['total_num'] = $total;
        $data['page_limit'] = $page_limit;

        //未读数量 clusterCount
        $culsterCount             = $this->model_account_ticket->clusterCount($param);
        $data['culsterCount']     = $culsterCount;
        $data['culsterCountJson'] = json_encode($culsterCount);
        $data['categoryRootid']   = json_encode(array_keys($categoryGroupList[0]));
        //print_r($culsterCount);


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
        foreach ($categoryGroupList[$submit_ticket_for] as $value) {
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
        $data['is_seller'] = $this->customer->isPartner();

        switch ($submit_ticket_for) {
            case '9':
                $this->response->setOutput($this->load->view('account/ticket/lists_warehouse_services', $data));
                break;
            case '14':
                $this->response->setOutput($this->load->view('account/ticket/lists_questions_dimensions', $data));
                break;
            case '21':
                return $this->render('account/ticket/lists_safeguard', $data);
            default:
                $this->response->setOutput($this->load->view('account/ticket/lists_others', $data));
                break;
        }
    }


    /**
     * tickets 点击列表页右侧的小眼睛，打开某Ticket下所有的消息，不用分页
     * 后面Buyer 和 Seller 代码区分开，此处Buyer不在使用（Buyer:account/message_center/ticket）
     */
    public function messages()
    {

        $id = max(0, isset($this->request->get['id']) ? intval($this->request->get['id']) : 0);//Ticket主键


        $this->load->model('account/ticket');
        $this->load->model('account/customerpartner');
        // 加载语言层
        $this->load->language('account/ticket');
        $this->document->setTitle($this->language->get('text_tickets'));//<title>Tickets</title>


        $data                      = [];
        $data['ticket_title_long'] = $this->language->get('text_ticket');


        $isPartner = $this->customer->isPartner();


        if ($id < 1) {
            $data['lists'] = [];
        } else {
            if ($isPartner) {
                $role = 'seller';
            } else {
                $role = 'buyer';
            }
            $categoryKeyList = $this->model_account_ticket->categoryKeyList($role);
            //属于buyers自己的Ticket
            $info = $this->model_account_ticket->getTicketInfoById($id);
            if (!$info) {
                $data['lists'] = [];
            } else {

                $submit_ticket_for_name = isset($categoryKeyList[$info['submit_ticket_for']]) ? $categoryKeyList[$info['submit_ticket_for']]['name'] : '';
                $ticket_type_name       = isset($categoryKeyList[$info['ticket_type']]) ? $categoryKeyList[$info['ticket_type']]['name'] : '';
                $processing_method_name = isset($categoryKeyList[$info['processing_method']]) ? $categoryKeyList[$info['processing_method']]['name'] : '';

                $title_suffix                   = '&nbsp;(' . $info['ticket_id'] . ')&nbsp;-&nbsp;' . $ticket_type_name;
                $data['ticket_title_long']      .= $title_suffix;
                $data['ticket_id']              = $info['id'];
                $data['ticket_long_id']         = $info['ticket_id'];
                $data['submit_ticket_for']      = $info['submit_ticket_for'];
                $data['submit_ticket_for_name'] = $submit_ticket_for_name;
                $data['ticket_type']            = $info['ticket_type'];
                $data['ticket_type_name']       = $ticket_type_name;
                $data['rma_id']                 = $info['rma_id'];
                $data['rma_key']                = $info['rma_key'];
                $data['sales_order_id']         = $info['sales_order_id'];
                $data['sales_item_code']        = $info['sales_item_code'];
                $data['tracking_number']        = $info['tracking_number'];
                $data['sales_order_key']        = $info['sales_order_key'];
                $data['processing_method']      = $info['processing_method'];
                $data['processing_method_name'] = $processing_method_name;
                $data['safeguard_claim_no']     = $info['safeguard_claim_no'];

                $data['rma_url']         = $this->url->link('account/rma_order_detail', 'rma_id=' . $info['rma_key']);
                //101592 一件代发 taixing
                if($this->customer->isCollectionFromDomicile()){
                    $data['sales_order_url'] = $this->url->link('account/customer_order/customerOrderSalesOrderDetails', 'id=' . $info['sales_order_key'], true);
                }else{
                    $data['sales_order_url'] = $this->url->link('account/sales_order/sales_order_management/customerOrderSalesOrderDetails', 'id=' . $info['sales_order_key'], true);
                }
                ////所有管理员
                //$adminUsers    = $this->model_account_ticket->getUsers();
                //$adminUsersArr = [];
                //foreach ($adminUsers as $key => $value) {
                //    $adminUsersArr[$value['user_id']] = $value;
                //}
                //unset($adminUsers);

                //$data['chkIsPartner'] = $this->model_account_customerpartner->chkIsPartner();
                $LoginInInfo = $this->model_account_customerpartner->getLoginInInfoByCustomerId();

                //查询该Ticket下所有的 TicketMessages
                $results = $this->model_account_ticket->ticketMessaes($id);
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
                        //'adminName'          => isset($adminUsersArr[$value['create_admin_id']]) ? ($adminUsersArr[$value['create_admin_id']]['firstname'] . '&nbsp;' . $adminUsersArr[$value['create_admin_id']]['lastname']) : '',
                        'adminName'          => 'GIGACLOUD',
                        'showName'           => $value['create_admin_id'] ? 'GIGACLOUD' : ($LoginInInfo['nickname'] . '(' . $LoginInInfo['user_number'] . ')'),
                        'description'        => $value['description'],
                        'attachments'        => $attachmentsList,
                        'is_read'            => $value['is_read'],
                        'safeguard_claim_no' => $info['safeguard_claim_no'],
                    ];
                }
            }

            $this->model_account_ticket->updateReadTime($id);
        }

        $data['cancel']       = url(['account/ticket/lists', 'type' => request('type', 1)]);
        $data['title_cancel'] = 'Return to the list page';

        $ticketCategoryGroupList = $this->model_account_ticket->categoryGroupListKeyStr($this->customer->isPartner() ? 'seller' : 'buyer');
        $data['ticketCategoryGroupList'] = !$ticketCategoryGroupList ? '{}' : json_encode($ticketCategoryGroupList);

        // 面包屑导航
        if($this->customer->isPartner()){
            $data['breadcrumbs'][] = array(
                'text' => 'Message Center',
                'href' => 'javascript:void(0);',
            );
            $data['breadcrumbs'][] = array(
                'text' => $this->language->get('text_tickets'),
                'href' => $this->url->link('account/ticket/lists', '', true)
            );
            $data['breadcrumbs'][] = array(
                'text' => $this->language->get('heading_title_request_details'),
                'href' => $this->url->link('account/ticket/messages', '&id='.$id, true)
            );
            $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');
            $data['footer']               = $this->load->controller('account/customerpartner/footer');
            $data['header']               = $this->load->controller('account/customerpartner/header');
        } else {
            $data['breadcrumbs']   = array();
            $data['breadcrumbs'][] = array(
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/home')
            );
            $data['breadcrumbs'][] = array(
                'text' => $this->language->get('text_tickets'),
                'href' => $this->url->link('account/ticket/lists', '', true)
            );
            $data['footer'] = $this->load->controller('common/footer');
            $data['header'] = $this->load->controller('common/header');
        }

        $this->response->setOutput($this->load->view('account/ticket/messages', $data));
    }


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


    /**
     * @param $fileArr $_FILE['file']
     * @return bool
     */
    private function isImg($fileArr)
    {
        $type = $fileArr['type'];
        if (strpos($type, 'image') === false) {
            return false;
        }

        $tmp_name  = $fileArr['tmp_name'];
        $mimetype  = exif_imagetype($tmp_name);
        $resultArr = [
            IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_SWF, IMAGETYPE_PSD, IMAGETYPE_BMP, IMAGETYPE_TIFF_II, IMAGETYPE_TIFF_MM, IMAGETYPE_JPC, IMAGETYPE_JP2, IMAGETYPE_JPX, IMAGETYPE_JB2, IMAGETYPE_SWC, IMAGETYPE_IFF, IMAGETYPE_WBMP, IMAGETYPE_XBM
        ];
        if (in_array($mimetype, $resultArr)) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * @param $fileArr $_FILE['file']
     * @return bool
     */
    private function isGeneralDocument($fileArr)
    {
        $fileNameSuffix = $this->fileNameSuffix($fileArr['name']);
        if ($fileNameSuffix == 'csv') {
            return true;
        }


        $type = $fileArr['type'];
        if (in_array($type, ['text/plain'])) {
            return true;
        }


        $tmp_name = $fileArr['tmp_name'];
        $file     = fopen($tmp_name, "rb");
        $bin      = fread($file, 2); //只读2字节
        fclose($file);
        $strInfo  = @unpack("C2chars", $bin);
        $typeCode = intval($strInfo['chars1'] . $strInfo['chars2']);
        $fileType = '';
        $isOK     = false;
        switch ($typeCode) {
            case 7790:
                //exe
                $isOK = false;
                break;
            case 208207:
                //doc/ppt/xls
                $isOK = true;
                break;
            case 8075:
                //docx/pptx/xlsx
                $isOK = true;
                break;
            case 3780:
                //pdf
                $isOK = true;
                break;
            default:
                $isOK = false;
                break;
        }
        return $isOK;
    }


    /**
     * @param string $fileName files['file']['name']
     * @return bool|string
     */
    private function fileNameSuffix($fileName)
    {
        return substr(strrchr($fileName, '.'), 1);
    }


    public function upload()
    {
        //代码拷贝：
        //\catalog\controller\account\customer_order.php

        // exif_imagetype()


        $this->load->language('account/ticket');
        $this->load->model('account/ticket');
        $json = array();

        // 获取当前登录用户
        if ($this->customer->isLogged()) {
            // 检查文件名以及文件类型
            if (isset($this->request->files['file']['tmp_name'])) {

                //判断文件类型
                $isGeneralDocument = $this->isGeneralDocument($this->request->files['file']);
                $isImg             = $this->isImg($this->request->files['file']);
                if (!$isGeneralDocument && !$isImg) {
                    $json['error'] = $this->language->get('error_filetype');
                }

                if ($this->request->files['file']['error'] != UPLOAD_ERR_OK) {
                    $json['error'] = $this->language->get('error_upload_' . $this->request->files['file']['error']);
                }
            } else {
                $json['error'] = $this->language->get('error_upload');
            }

            // 检查文件短期之内是否重复上传(首次提交文件后5s之内不能提交文件)
//            $files = glob(DIR_UPLOAD . '*.tmp');
//            foreach ($files as $file) {
//                if (is_file($file) && (filectime($file) < (time() - 5))) {
//                    unlink($file);
//                }
//
//                if (is_file($file)) {
//                    $json['error'] = $this->language->get('error_install');
//                    break;
//                }
//            }


            $fileName = html_entity_decode($this->request->files['file']['name']);
            //文件后缀名
            $fileNameSuffix = $this->fileNameSuffix($fileName);

            // 获取登录用户信息
            $customer_id = $this->customer->getId();
            // 上传订单文件，以用户ID进行分类
            if (!isset($json['error'])) {
                // 复制文件到Upload文件夹下
//                session()->set('install', token(10));
//                $file                           = DIR_UPLOAD . session('install') . '.tmp';
//                move_uploaded_file($this->request->files['file']['tmp_name'], $file);
                // 复制上传的文件到orderCSV路径下
                $dateTime    = date("Y-m-d_His");
                $fileNameNew = str_replace(['.' . $fileNameSuffix, '%'], '', $fileName) . $dateTime . "." . $fileNameSuffix;

                StorageCloud::image()->writeFile(request()->filesBag->get('file'), 'tickets/' . $customer_id, $fileNameNew);

                $json['text'] = $this->language->get('text_upload');
                $json['name'] = $fileNameNew;
                $json['url']  = '/image/tickets/' . $customer_id . '/' . $fileNameNew;
                $json['path']  = StorageCloud::image()->getUrl('tickets/' . $customer_id . '/' . $fileNameNew);
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function checkRmaId()
    {
        $this->load->model('account/ticket');
        $this->load->language('account/ticket');
        if ((request()->isMethod('POST')) && isset($this->request->post['rma_id'])) {
            $rma_id = $this->request->post['rma_id'];
            $ticketType = $this->request->post['ticket_type'];
            $json['success'] = true;
            if ($this->customer->isPartner()) {
                $sellerId = intval($this->customer->getId());
                $rmaData = $this->model_account_ticket->getSellerRamOrderInfoByRmaid($sellerId, $rma_id);
                if (!$rmaData) {
                    $json['success'] = false;
                    $json['msg'] = $this->language->get('error_rma_invalid');
                }
            } elseif ($ticketType == 4) {
                //验证 rma_id 属于当前用户
                $result = $this->model_account_ticket->getRamOrderInfoByRmaid($rma_id);
                if (!$result) {
                    $json['success'] = false;
                    $json['msg'] = $this->language->get('error_rma_invalid');
                }
            }  else {
                $status_reshipment = $this->model_account_ticket->checkRmaId($rma_id);
                if (!empty($status_reshipment)) {
                    if ($status_reshipment != 1) {
                        $json['success'] = false;
                        $json['msg'] = "Cannot submit a reshipment ticket, this RMA ID is not the one approved by seller for reshipment.";
                    }
                } else {
                    $json['success'] = false;
                    $json['msg'] = "Cannot submit a reshipment ticket, this RMA ID is not the one approved by seller for reshipment.";
                }
            }
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function checkSalesOrderId(){
        $this->load->model('account/ticket');
        $this->load->language('account/ticket');
        if(!empty($this->request->request['salesOrderId'])){
            $salesOrderId = $this->request->request['salesOrderId'];
            $ticketType = $this->request->request['ticket_type'];
            $order_status = $this->model_account_ticket->getOrderStatusBySaleOrderId($salesOrderId,$this->customer->getId());
            if(!empty($order_status)) {
                if ($order_status == 32 && $ticketType != 19) {
                    //101301需求，类型19下不限制订单状态
                    $json['success'] = false;
                    $json['msg'] = $this->language->get('tip_sales_order_id_msg');
                } else {
                    $json['success'] = true;
                }
            }else{
                $json['success'] = false;
                $json['msg'] = 'Sales Order ID is invalid.';
            }
        }else{
            $json['success'] = false;
            $json['msg'] = 'Sales Order ID is invalid.';
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function checkSellerItemCode()
    {
        $this->load->model('account/ticket');
        $itemCode = $this->request->post['item_code'];
        $customer_id = intval($this->customer->getId());
        if (!empty($itemCode)) {
            $itemCodeStatus = $this->model_account_ticket->checkSellerItemCode($customer_id, $itemCode);
            $json['success'] = true;
            if (!$itemCodeStatus) {
                $json['success'] = false;
                $json['msg'] = 'Item Code is invalid.';
            } elseif ($itemCodeStatus->instock_qty < 1) {
                //库存小于0
                $json['success'] = false;
                $json['msg'] = 'This item code is currently out of stock.';
            }
        } else {
            $json['success'] = false;
            $json['msg'] = 'Item Code is invalid.';
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    // 前端ajax获取用户理赔单数据
    public function getSafeguardClaimList()
    {
        $safeguardClaimNo = $this->request->post('safeguard_claim_no', '');
        if (!$safeguardClaimNo) {
            return $this->json([]);
        }
        $list = app(SafeguardClaimRepository::class)->getListByClaimNo(customer()->getId(), $safeguardClaimNo);
        return $this->json($list->pluck('claim_no')->toArray());
    }

    // 前端校验理赔单号是否存在
    public function ajaxCheckSafeguardNo()
    {
        $safeguardClaimNo = $this->request->post('safeguard_claim_no', '');
        return $this->json($this->checkSafeguardNo($safeguardClaimNo));
    }

    /**
     * @param string $safeguardClaimNo
     * @return array [success:bool,msg:null|string]
     */
    private function checkSafeguardNo(string $safeguardClaimNo): array
    {
        $res = ['success' => true];
        if ($safeguardClaimNo) {
            $safeguardExists = SafeguardClaim::query()->where('buyer_id', customer()->getId())->where('claim_no', $safeguardClaimNo)->exists();
            if (!$safeguardExists) {
                $res['success'] = false;
                $res['msg'] = 'Claim Application ID is invalid';
            }
        }
        return $res;
    }
}
