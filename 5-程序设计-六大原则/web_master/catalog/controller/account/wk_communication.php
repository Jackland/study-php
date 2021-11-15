<?php

use App\Models\Message\MsgReceive;
use App\Components\Storage\StorageCloud;

/**
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 * @property ModelAccountCustomerpartnerBuyerGroup $model_Account_Customerpartner_BuyerGroup
 * @property ModelAccountCustomerpartnerProductGroup $model_Account_Customerpartner_ProductGroup
 * @property ModelExtensionModuleWkcontact $model_extension_module_wk_contact
 */
class ControllerAccountWkCommunication extends Controller
{
    private $error = array();
    private $buyer_id = 0;
	public function index() {
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/wk_communication', '', true));

            $this->response->redirect($this->url->link('account/login', '', true));
        }

        $this->load->language('account/account');
        $this->load->language('account/wk_communication');
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_account'),
            'href' => $this->url->link('account/account', '', true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('title_communication'),
            'href' => $this->url->link('account/wk_communication', '', true),
            'separator' => $this->language->get('text_separator')
        );
        $extension = $this->config->get('module_wk_communication_type');
        $extensions = explode(',', $extension);
        $data['extension'] = $extensions;
        $data['max'] = $this->config->get('module_wk_communication_max');
        $data['size'] = $this->config->get('module_wk_communication_size');
        $data['size_mb'] = round($data['size'] / 1024, 2) . 'MB';
        $data['type'] = explode(",", $this->config->get('module_wk_communication_type'));

        $this->load->model('extension/module/wk_contact');
        $this->load->model('account/customerpartner');
        $this->document->setTitle($this->language->get('title_communication'));
        // $this->document->addStyle('catalog/view/theme/default/stylesheet/MP/MPSellerBuyer.css');
        // 本页面 主要js使用的angularJS,因为要使用弹窗,则layer弹窗JS要早于angularJS引入
        $this->document->addScript("catalog/view/javascript/layer/layer.js");
        $this->document->addScript("catalog/view/javascript/wk_communication/angular.min.js");
        $this->document->addScript("catalog/view/javascript/wk_communication/ng-infinite-scroll.js");
        $this->document->addScript("catalog/view/javascript/wk_communication/angular-sanitize.js");

//	 $this->document->addScript("https://ajax.googleapis.com/ajax/libs/angularjs/1.4.7/angular-route.js");
        $this->document->addScript('catalog/view/javascript/wk_communication/wk_communication.js?v=' . APP_VERSION);
        $this->document->addScript('catalog/view/javascript/summernote/summernote.js');
        $this->document->addStyle('catalog/view/javascript/summernote/summernote.css');
        if (isset($this->session->data['error_warning'])) {
            $data['error_warning'] = session('error_warning');
            $this->session->remove('error_warning');
        } else {
            $data['error_warning'] = '';
        }
        $data['action'] = $this->url->link('account/wk_communication/reply', '', true);
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        $data['separate_view'] = false;

        $data['separate_column_left'] = '';

        // Buyer Groups
        $this->load->model('Account/Customerpartner/BuyerGroup');
        $this->load->model('Account/Customerpartner/ProductGroup');
        $data['buyer_groups'] = $this->model_Account_Customerpartner_BuyerGroup->getGroupsForSelect($this->customer->getId());
        $data['is_default_buyer_group'] = 0;
        $buyerGroupIds = [];
        foreach ($data['buyer_groups'] as $buyer_group) {
            $buyerGroupIds[] = $buyer_group->buyer_group_id;
            if ($buyer_group->is_default == 1) {
                $data['is_default_buyer_group'] = $buyer_group->buyer_group_id;
            }
        }
        $productGroupObjs = $this->model_Account_Customerpartner_ProductGroup->getGroupsAndNumByBuyerGroups($this->customer->getId(), $buyerGroupIds);
        $PGBGArr = [];
        foreach ($productGroupObjs as $productGroupObj) {
            $PGBGArr[$productGroupObj->buyer_group_id] = $productGroupObj;
        }
        foreach ($data['buyer_groups'] as &$buyer_group) {
            if (isset($PGBGArr[$buyer_group->buyer_group_id])) {
                $buyer_group->product_group_name = $PGBGArr[$buyer_group->buyer_group_id]->name;
                $buyer_group->product_group_num = $PGBGArr[$buyer_group->buyer_group_id]->total - 1;
            }
        }

        if ($this->customer->isPartner()) {
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
            $data['isPartner'] = true;
            $partner = $this->model_account_customerpartner->getProfile();
            $data['storeName'] = $partner['screenname'];
        } else {
            $data['isPartner'] = false;
        }

        $this->response->setOutput($this->load->view('account/wk_communication', $data));
    }

    public function messages()
    {
        if (isset($this->request->get['placeholder_name'])) {
            if ($this->request->get['placeholder_name'] == 'inbox') {
                $placeholder_id = 1;
            } elseif ($this->request->get['placeholder_name'] == 'sent') {
                $placeholder_id = 2;
            } elseif ($this->request->get['placeholder_name'] == 'trash') {
                $placeholder_id = 0;
            }
        } else {
            $placeholder_id = 1;
        }

        if (isset($this->request->get['page'])) {
            $page_num = $this->request->get['page'];
        } else {
            $page_num = 1;
        }

        if (isset($this->request->get['limit'])) {
            $page_limit = $this->request->get['limit'];
        } else {
            $page_limit = 15;
        }
        $filter_data = array(
            'customer_id' => $this->customer->getId(),
            'placeholder_id' => $placeholder_id,
            'start' => ($page_num - 1) * $page_limit,
            'limit' => $page_limit,
        );
        if (isset($this->request->get['keyword']) && $this->request->get['keyword']!='') {
            $filter_data['keyword']=$this->request->get['keyword'];
        }
        $this->load->model('extension/module/wk_contact');
        $messages = $this->listMessages($filter_data);
        $placeholder_count = $this->countMessages($filter_data);
        $placeholder_totals = [];
        foreach ($placeholder_count as $k => $v) {
            $placeholder_totals[$v['name']] = $v['total'];
        }
        foreach ($messages as $index => $message) {
            $messages[$index]['reply'] = $this->model_extension_module_wk_contact->countThreads($message['message_id']);
        }

        $message_count = $placeholder_count[$placeholder_id]['total'];
        $total_pages = ceil($message_count / $page_limit);
        $pagination_results = sprintf($this->language->get('text_pagination'), ($message_count) ? (($page_num - 1) * $page_limit) + 1 : 0, ((($page_num - 1) * $page_limit) > ($message_count - $page_limit)) ? $message_count : ((($page_num - 1) * $page_limit) + $page_limit), $message_count, $total_pages);

        $data = array(
            'total' => $placeholder_totals,
            'messages' => $messages,
            'total_pages' => $total_pages < 1 ? 1 : $total_pages,
            'page_num' => $page_num,
            'pagination_results' => $pagination_results,
        );
        if (isset($this->request->get['placeholder_name'])) {
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($data));
        } else {
            return $data;
        }
    }
	public function getQueryinfo() {
        $json = array();
        if (request()->isMethod('POST')) {
            $this->load->model('extension/module/wk_contact');
            $message_id = $this->request->post['message_id'];
            $json = $this->model_extension_module_wk_contact->getQueryinfo($message_id);
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
	public function info() {
        $message_id = $this->request->get['message_id'];
        $this->load->model('extension/module/wk_contact');
        $query_info = $this->model_extension_module_wk_contact->getQueryinfo($message_id);
        $this->setSellerEstablishTitle($query_info);
        list($query_info['message_from_id'], $query_info['message_from_name']) = $this->model_extension_module_wk_contact->getCustomerNameById($query_info['message_from_id']);
        list($query_info['message_to_id'], $query_info['message_to_name']) = $this->model_extension_module_wk_contact->getCustomerNameById($query_info['message_to_id']);
        if(strpos($query_info['message_subject'],'BID') !== false && strpos($query_info['message_body'],'Agreement ID:') !== false){
            $data['no_reply'] = true;
            $data['no_reply_hint'] = "Please do not reply to this message.";
        }

        $attachment = $this->model_extension_module_wk_contact->getAttachment($message_id);
        $data['query_info'] = $query_info;
        if (!empty($attachment)) {
            $data['attachment'] = $attachment;
        }
        $threads = $this->model_extension_module_wk_contact->getThreadMessages($message_id);
        if (!empty($threads))
            foreach ($threads as $thread) {
                $threadMsg = $this->model_extension_module_wk_contact->getQueryinfo($thread['message_id']);
                $this->setSellerEstablishTitle($threadMsg);
                list($threadMsg['message_from_id'], $threadMsg['message_from_name']) = $this->model_extension_module_wk_contact->getCustomerNameById($threadMsg['message_from_id']);
                list($threadMsg['message_to_id'], $threadMsg['message_to_name']) = $this->model_extension_module_wk_contact->getCustomerNameById($threadMsg['message_to_id']);

                $data['threads']['query_info'][] = $threadMsg;
                $data['threads']['attachment'][] = $this->model_extension_module_wk_contact->getAttachment($thread['message_id']);
            }
        //已读
        $this->model_extension_module_wk_contact->read($message_id);
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($data));
    }

    public function download()
    {

        $this->load->model('extension/module/wk_contact');
        if (isset($this->request->get['attachment_id'])) {
            $attachment_id = $this->request->get['attachment_id'];
        } else {
            $attachment_id = 0;
        }

        $download_info = $this->model_extension_module_wk_contact->getDownload($attachment_id);

        if ($download_info) {
            $file = 'download/attachment/' . $download_info['filename'];
            $mask = basename($download_info['maskname']);

            if (!headers_sent()) {
                if (file_exists(DIR_STORAGE . $file)) {
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename="' . ($mask ? $mask : basename(DIR_STORAGE . $file)) . '"');
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                    header('Pragma: public');
                    header('Content-Length: ' . filesize(DIR_STORAGE . $file));

                    if (ob_get_level()) {
                        ob_end_clean();
                    }

                    readfile(DIR_STORAGE . $file, 'rb');

                    exit();
                } elseif (StorageCloud::storage()->fileExists($file)) {
                    return StorageCloud::storage()->browserDownload($file, $mask ?: $download_info['filename']);
                } else {
                    exit('Error: Could not find file ' . $file . '!');
                }
            } else {
                exit('Error: Headers already sent out!');
            }
        } else {
            $this->response->redirect($this->url->link('account/wk_communication', '', true));
        }
    }

    public function listMessages($filter_data = [])
    {
        $messages = $this->model_extension_module_wk_contact->getQuery($filter_data);
        foreach ($messages as $k => $v) {
            $this->setSellerEstablishTitle($messages[$k]);
        }
        return $messages;
    }

    public function countMessages($filter_data = [])
    {
        $param = ['1' => 'inbox',
            '2' => 'sent',
            '0' => 'trash'];
        $result = array();
        foreach ($param as $placeholder_id => $placeholder_name) {
            $filter_data['placeholder_id'] = $placeholder_id;
            $total = $this->model_extension_module_wk_contact->countMessages($filter_data);
            $result[$placeholder_id] = [
                'name' => $placeholder_name,
                'total' => $total
            ];
        }
        return $result;
    }

    public function reply()
    {
        $this->load->model('extension/module/wk_contact');
        $this->load->language('account/wk_communication');
        if (request()->isMethod('POST') && $this->validate()) {
            try {
                $demo = is_dir(DIR_DOWNLOAD . 'attachment') ? '' : mkdir(DIR_DOWNLOAD . 'attachment');
                $files = array();
                if (isset($this->request->files['file'])) {
                    $files = $this->request->files['file'];
                }
                $message_from_id = $this->customer->getId();
                $message_to_id = $this->request->post['message_to_id'];
                $message_id = $this->communication->insertQuery(
                    $this->request->post['subject'],
                    $this->request->post['message'],
                    $message_from_id,
                    $message_to_id,
                    $files);
                //更新建立联系标识
                $this->updateEstablishFlag($message_id);
                $this->communication->updateReply($message_id, $this->request->post['parent_message']);
                $json['success'] = $this->language->get('send_success');
                $json['message_id'] = $message_id;
            } catch (Exception $e) {
                $this->log->write('站内信msg发送失败：' . $e->getMessage());
            }
            if (!empty($this->buyer_id)) {
                $json['jump_url'] = $this->url->link('account/customerpartner/delicacymanagement&buyer_id=' . $this->buyer_id);
            }
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
        } else {
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($this->error));
        }
    }

    public function sendSMTPMail()
    {
        if ($this->validate()) {
            $this->load->model('extension/module/wk_contact');
            $files = array();
            if (isset($this->request->files['file'])) {
                $files = $this->request->files['file'];
            }
            $json = $this->model_extension_module_wk_contact->sendMyMail(
                $this->request->post['subject'],
                $this->request->post['message'],
                $this->request->post['message_to_id'],
                $files
            );
        } else {
            $json = $this->error;
        }
        if (!empty($this->buyer_id)) {
            $json['jump_url'] = $this->url->link('account/customerpartner/delicacymanagement&buyer_id=' . $this->buyer_id);
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function establishReplySendMail()
    {
        $message_id = $this->request->get['message_id'];
        $this->load->model('extension/module/wk_contact');
        $query_info = $this->model_extension_module_wk_contact->getQueryinfo($message_id);
        $this->request->post['subject'] = $query_info['message_subject'];
        $this->request->post['message'] = $query_info['message_body'];
        $this->request->post['message_to_id'] = $query_info['message_to_id'];
        $this->sendSMTPMail();
    }

    public function establishReplyNewSendMail()
    {
        $message_id = $this->request->get['message_id'];
        $this->load->model('message/message');

        /** @var MsgReceive $msgReceive */
        $msgReceive = MsgReceive::queryRead()->with(['msg', 'msg.content'])->where('msg_id', $message_id)->first();

        $this->request->post['subject'] = $msgReceive->msg->title;
        $this->request->post['message'] = $msgReceive->msg->content->content;
        $this->request->post['message_to_id'] = $msgReceive->receiver_id;
        $this->sendSMTPMail();
    }

	public function establishReply(){
        $message_id = $this->request->get['message_id'];
        $this->load->model('extension/module/wk_contact');
        $query_info = $this->model_extension_module_wk_contact->getQueryinfo($message_id);
        $this->request->post['message_to_id'] = $query_info['message_from_id'];
        $this->request->post['parent_message'] = $query_info['message_id'];
        if (isset($this->request->get['isRefine']) && $this->request->get['isRefine'] == 1) {
            $this->buyer_id = $query_info['message_from_id'];
        }
        $this->load->model('account/customerpartner');
//        $partner = $this->model_account_customerpartner->getProfile();
        //同意
        if (isset($this->request->get['returnType']) && $this->request->get['returnType'] == 1) {
            $this->model_extension_module_wk_contact->addBuyerToSeller($query_info['message_from'], $this->customer->getId());
            // 添加进分组
            if (($this->request->post['buyer_group_id'] ?? 0) && $query_info['message_from_id']) {
                $this->load->model('Account/Customerpartner/BuyerGroup');
                $this->model_Account_Customerpartner_BuyerGroup->updateGroupLinkByBuyer(
                    $this->customer->getId(),
                    $query_info['message_from_id'],
                    $this->request->post['buyer_group_id']
                );
            }
        }
        //更改建立联系的subject和body
        $this->changeEstablishMessage();
        //更新建立联系标识
        $this->updateEstablishFlag($query_info['message_id']);
        //已读
        $this->model_extension_module_wk_contact->read($message_id);
        $this->reply();
    }

	public function validate() {
        $this->load->language('account/wk_communication');
        if (empty($this->request->post)) {
            //附件太大导致所有参数为空
            $this->error['error'] = $this->language->get('error_file_too_big');
            return false;
        }
        if (!isset($this->request->post['message_to_id']) || !$this->request->post['message_to_id']) {
            $this->error['error'] = $this->language->get('error_something_wrong');
        }
        if (!isset($this->request->post['subject']) || !$this->request->post['subject']) {
            $this->error['error'] = $this->language->get('error_subject_empty');
        }
        if (!isset($this->request->post['message'])
            || !$this->request->post['message']
            || str_contains('&lt;p&gt;&lt;br&gt;&lt;/p&gt;', $this->request->post['message'])
        ) {
            $this->error['error'] = $this->language->get('error_message_empty');
        }

        return !$this->error;
    }

	public function delete() {
        $this->load->model('extension/module/wk_contact');
        $message_id = $this->request->get['message_id'];
        $customer_id = $this->customer->getId();
        foreach ($message_id as $key => $value) {
            $this->model_extension_module_wk_contact->deleteQuery($message_id[$key], $customer_id);
        }
        $this->load->language('account/wk_communication');
        $json['success'] = $this->language->get('delete_success');
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));

    }

	public function getMessageinfodata(){
        $this->load->language('account/wk_communication');
        $lang = array(
            'text_expand',
            'text_from',
            'text_date',
            'text_subject',
            'text_me'
        );
        $data['from_me'] = $this->customer->getEmail();
        foreach ($lang as $value) {
            $data[$value] = $this->language->get($value);
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($data));
    }
	public function upload() {

        if ($this->request->files['file']['name']) {
            if (!$this->request->files['file']['error']) {
                $name = md5(time() . rand(100, 200));
                $ext = pathinfo($this->request->files['file']['name'], PATHINFO_EXTENSION);
                $filename = $name . ($ext ? '.' . $ext : '');
                return StorageCloud::image()->writeFile(request()->filesBag->get('file'), 'attachment', $filename);
            } else {
                echo $message = 'Ooops!  Your upload triggered the following error:  ' . $this->request->files['file']['error'];
            }
        }
    }

//route=account/wk_communication/sendUnReplyMsg
    public function sendUnReplyMsg()
    {
        $isDebug = defined('UNREPLY_DEBUG') && UNREPLY_DEBUG;
        $endDate = new DateTime();
        $startDate = new DateTime();
        $startDate->modify('-1 day');
        $msg_sql = "SELECT  m.`message_id`,m.`user_id`,m.message_date, t.message_id AS t_msgid,t.parent_message_id AS t_parent_msgid FROM oc_wk_communication_message m
LEFT JOIN `oc_wk_communication_thread` t ON m.`message_id`=t.parent_message_id
WHERE m.message_date>='" . $startDate->format('Y-m-d H:i:s') . "'  and m.message_date <= '" . $endDate->format('Y-m-d H:i:s') . "'
ORDER BY m.`message_date`   ";
        $msg_rows = $this->db->query($msg_sql)->rows;
        $sellerid_rows = $this->db->query('SELECT customer_id FROM oc_customerpartner_to_customer')->rows;
        $seller_ids = array();
        foreach ($sellerid_rows as $row) {
            $seller_ids[] = $row['customer_id'];
        }
        $msg_b2s = array();
        if (!empty($msg_rows)) {
            foreach ($msg_rows as $k => $row) {
                if (preg_match('/\d+_\d+/', $row['user_id'])) {
                    $arr = explode('_', $row['user_id']);
                    $msg_rows[$k]['user_id_from'] = $arr[0];
                    $msg_rows[$k]['user_id_to'] = $arr[1];
//                    if($row['user_id']!='6_34') continue;
                    if (!in_array($arr[0], $seller_ids) && in_array($arr[1], $seller_ids)) {
                        //buyer发给seller  只要seller回复了一封 就不监控
                        if (!isset($msg_b2s[$row['user_id']])) {
                            if (is_null($row['t_msgid'])) {
                                $msg_b2s[$row['user_id']] = $msg_rows[$k];
                            }
                        } else {
                            if (!is_null($row['t_msgid'])) {
                                $msg_b2s[$row['user_id']]['is_reply'] = true;
                            }
                        }
                    }
                }
            }
            foreach ($msg_b2s as $k => $v) {
                if (isset($v['is_reply'])) {
                    unset($msg_b2s[$k]);
                }
            }
            if (empty($msg_b2s)) {
                return;
            }


            $customer_rows = $this->db->query(" SELECT cus.firstname,cus.customer_id,CONCAT(cus.`firstname`,' ',cus.`lastname`) AS custoemr_name,cus.`email` ,  c2c.is_partner
FROM oc_customer cus  LEFT JOIN  oc_customerpartner_to_customer c2c
ON c2c.`customer_id`=cus.`customer_id`")->rows;
            $customers = array();
            $managers = array();
            $sendUnReplyMsgToSelf = configDB('sendUnReplyMsgToSelf');
            $sendUnReplyMsgCCManager = configDB('sendUnReplyMsgCCManager');
            $sendUnReplyMsgCCArr = configDB('sendUnReplyMsgCC');
            $cc = (is_array($sendUnReplyMsgCCArr) && count($sendUnReplyMsgCCArr) > 0) ? $sendUnReplyMsgCCArr : array();
            if ($isDebug) {
                $cc = array();
            }

            foreach ($customer_rows as $row) {
                $customers[$row['customer_id']] = $row;
                //经理
                if ($row['customer_id'] == 159 || $row['customer_id'] == 158) {
                    $managers[$row['customer_id']]['email'] = $row['email'];
                    $managers[$row['customer_id']]['name'] = $row['custoemr_name'];
                    $managers[$row['customer_id']]['cc'] = $cc;
                    if ($row['customer_id'] == 159) {
                        $managers[$row['customer_id']]['cc'][] = $sendUnReplyMsgCCManager;
                    }
                }
            }
            //自营
            $managers['-1']['email'] = $sendUnReplyMsgToSelf;
            $managers['-1']['name'] = '内部店铺';
            $managers['-1']['cc'] = $cc;
            foreach ($msg_b2s as $k => $row) {
                $q = $this->db->query("SELECT b2s.`buyer_id` as manager_id, b2s.seller_id  FROM `oc_buyer_to_seller` b2s
    WHERE b2s.`buyer_id` IN ( " . implode(',', array_keys($managers)) . ") and b2s.seller_id={$row['user_id_to']}")->rows;
                if (empty($q)) {
                    //自营发给开发组
                    $managers['-1']['msg'][] = $row;
                } else {
                    foreach ($q as $it) {
                        //发给对应经理
                        $managers[$it['manager_id']]['msg'][] = $row;
                    }

                }
            }
            foreach ($managers as $row) {
                $title = "昨天(" . $row['name'] . ")seller未回复buyer的站内信";
                if (isset($row['msg'])) {
                    $html = "<br><h3>$title 如下：</h3></a><hr>
<table   border='1' cellspacing='0' cellpadding='10' >
<tr><th align='left'>From:</th>
 <th align='left'>To:</th>
 <th align='left'>Date:</th>
 </tr> ";
                    foreach ($row['msg'] as $msg) {
                        $cus_from = $customers[$msg['user_id_from']];
                        $cus_to = $customers[$msg['user_id_to']];
                        $html .= '
<tr><td align=\'left\'>' . $cus_from['custoemr_name'] . '</td>
<td align=\'left\'>' . $cus_to['custoemr_name'] . '</td>
<td align=\'left\'>' . $msg['message_date'] . '</td>
</tr>';
                    }
                    $html .= '</table><br> ';
                    $email = $row['email'];
                    if ($isDebug && isset($_GET['to'])) {
                        $email = $_GET['to'];
                    }
                    $this->sendMyMail($title, $email, $html, $row['cc']);
                }
            }

        }
        return 'success';
    }

    public function sendMyMail($subject, $to, $body, $cc = array())
    {
        require_once DIR_SYSTEM . "library/phpmailer/PHPMailer.php";
        require_once DIR_SYSTEM . "library/phpmailer/Exception.php";
        require_once DIR_SYSTEM . "library/phpmailer/SMTP.php";
        require_once DIR_SYSTEM . "library/phpmailer/OAuth.php";
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);                              // Passing `true` enables exceptions
        //Server settings
        $mail->SMTPDebug = 0;                                 // Enable verbose debug output
        $mail->isSMTP();                                      // Set mailer to use SMTP
        $mail->CharSet = 'UTF-8';
        $mail->Host = $this->config->get('config_mail_smtp_hostname');  // Specify main and backup SMTP servers
        $mail->SMTPAuth = true;                               // Enable SMTP authentication
        $mail->Username = $this->config->get("config_mail_smtp_username");                 // SMTP username
        $mail->Password = $this->config->get('config_mail_smtp_password');                           // SMTP password
        $mail->SMTPSecure = 'ssl';                            // Enable TLS encryption, `ssl` also accepted
        $mail->Port = $this->config->get('config_mail_smtp_port');                                    // TCP port to connect to

        $mail->setFrom($this->config->get("config_mail_smtp_username"));
        $mail->FromName = $this->config->get('config_name');
        $mail->isHTML(true);                                  // Set email format to HTML
        $mail->Subject = $subject;
        $mail->Body = $body;
        //收件人
        $mail->addAddress($to);
        if (!empty($cc)) {
            foreach ($cc as $each) {
                $mail->addCC($each);
            }
        }
        $mail->send();
    }

    public function contactUnread(){
        if ($this->customer->isLogged()) {
            $period = $this->config->get('module_wk_communication_period');
            if (is_null($period)) {
                $period = 60;//轮训间隔 s
            }
            $this->load->model('extension/module/wk_contact');
            $unread_num = (int)$this->model_extension_module_wk_contact->countUnread();
            $contact_data['c_i'] = md5(
                'c_b_i'.    // 前缀
                date('Y-m-d'). // 日期
                (string)($this->customer->getId()?:0). // 用户id
                (string)$unread_num  // 未读数量
            );
            $contact_data['contact_period'] = $period * 1000;
            $contact_data['contact_action'] = $this->url->link('account/wk_communication', '', true);
            $contact_data['contact_unread_action'] = $this->url->link('account/wk_communication/countUnread', '', true);
            return $this->load->view('common/contact_unread', $contact_data);
        }
    }

    public function countUnread(){
        $this->load->model('extension/module/wk_contact');
        $unread_num = $this->model_extension_module_wk_contact->countUnread();
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput((int)$unread_num);
    }

    //已读
    public function readAll(){
        $keyword = $this->request->get['keyword'];
        $customer_id = $this->customer->getId();
        $this->load->model('extension/module/wk_contact');
        $this->model_extension_module_wk_contact->readAll($customer_id, $keyword);
    }

    /**
     * 更新建立联系标识
     * @param int $message_id
     */
    public function updateEstablishFlag($message_id): void
    {
        if (isset($this->request->get['returnType'])) {
            if ($this->request->get['returnType'] == 1) {
                $this->model_extension_module_wk_contact->updateShowFlag($message_id, 101);
            } else if ($this->request->get['returnType'] == 0) {
                $this->model_extension_module_wk_contact->updateShowFlag($message_id, 102);
            }
        }
    }

    /**
     * 更改建立联系的subject和body
     * @throws Exception
     * @since 2020-3-13 15:41:46 建立建立的时候 不再添加企点QQ的二维码图片 lester.you
     */
    public function changeEstablishMessage()
    {
        $this->load->model('account/customerpartner');
        $partner = $this->model_account_customerpartner->getProfile();
        if (isset($this->request->get['returnType'])) {
            //同意
            if ($this->request->get['returnType'] == 1) {
                $this->request->post['subject'] = $partner['screenname'] . ' has approved your application of establishing relationship.';
                //拒绝
            } else if ($this->request->get['returnType'] == 0) {
                $this->request->post['subject'] = $partner['screenname'] . ' has rejected your application of establishing relationship.';
            }
        }
    }

    /**
     * 建立联系的反馈   在seller发件箱的标题展示为 You have approved the...
     * @param $msg_row
     * @return mixed
     */
    public function setSellerEstablishTitle(&$msg_row)
    {
        $this->load->model('extension/module/wk_contact');
        $establishTitle = $this->model_extension_module_wk_contact->getSellerEstablishTitle($msg_row);
        if (!is_null($establishTitle)) {
            $msg_row['message_subject'] = $establishTitle;
        }
    }

}

?>
